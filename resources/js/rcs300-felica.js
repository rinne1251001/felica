/*
 * RC-S300（WebUSB）+ FeliCa 読み取りライブラリ
 *
 *  - RC-S300 との通信: CCID PC_to_RDR_Escape(0x6B) / RDR_to_PC_Escape(0x83)
 *  - FeliCa コマンド: Polling / Request System Code / Search Service Code / Read Without Encryption
 *  ※ RC-S300 のコマンド体系は公開仕様ではなく、コミュニティの解析結果に基づく
 *  ※ 読めるのは「認証不要」のサービスだけ（暗号化された領域は鍵が無いと読めない）
 */
(function (global) {
    'use strict';

    const sleep = (ms) => new Promise((resolve) => setTimeout(resolve, ms));
    const toHex = (bytes, sep = '') =>
        Array.from(bytes, (b) => b.toString(16).padStart(2, '0')).join(sep).toUpperCase();
    const fromHex = (hex) => Uint8Array.from(hex.match(/.{2}/g) ?? [], (h) => parseInt(h, 16));
    const hex4 = (n) => n.toString(16).toUpperCase().padStart(4, '0');
    const hex2 = (n) => n.toString(16).toUpperCase().padStart(2, '0');

    // 代表的なシステムコード（表示用のラベル）
    const SYSTEM_NAMES = {
        0x0003: '交通系IC（Suica・PASMO 等）',
        0xfe00: '共通領域（大学生協・電子マネー・学生証 等）',
        0x88b4: 'FeliCa Lite / Lite-S',
        0x12fc: 'NFC Forum Type 3（NDEF）',
    };

    // サービス属性（下位 6 ビット、最下位ビットは「認証不要」フラグ）
    const SERVICE_TYPES = {
        0x08: ['ランダム', '読み書き'],
        0x0a: ['ランダム', '読み取り専用'],
        0x0c: ['サイクリック', '読み書き'],
        0x0e: ['サイクリック', '読み取り専用'],
        0x10: ['パース', 'ダイレクト'],
        0x12: ['パース', 'キャッシュバック'],
        0x14: ['パース', 'デクリメント'],
        0x16: ['パース', '読み取り専用'],
    };

    // FeliCa Lite / Lite-S のブロック構成（サービス検索に非対応のため既知の番号を読む）
    const LITE_SERVICE = 0x000b; // 読み取り専用・認証不要
    const LITE_BLOCKS = [
        ...Array.from({ length: 14 }, (_, i) => [i, `S_PAD${i}`]),
        [0x0e, 'REG'], [0x82, 'ID'], [0x83, 'D_ID'], [0x84, 'SER_C'], [0x85, 'SYS_C'],
        [0x86, 'CKV'], [0x88, 'MC'], [0x90, 'WCNT'], [0x92, 'STATE'], [0xa0, 'CRC_CHECK'],
    ];

    function describeService(code) {
        const attribute = code & 0x3f;
        const [kind, access] = SERVICE_TYPES[attribute & 0x3e] ?? ['不明', '不明'];
        return {
            code: hex4(code),
            codeNum: code,
            number: code >> 6,
            attribute,
            kind,
            access,
            requiresAuth: (attribute & 0x01) === 0,
            status: 'pending', // read | auth | overlap | unreadable
            blocks: [],        // [{ no, label?, hex }]
        };
    }

    class ScanCancelled extends Error { }

    // =====================================================================
    // RC-S300 ドライバ
    // =====================================================================
    class RCS300Reader {
        static FILTERS = [
            { vendorId: 0x054c, productId: 0x0dc8 }, // RC-S300/S（業務用）
            { vendorId: 0x054c, productId: 0x0dc9 }, // RC-S300/P（個人用）
        ];

        static CMD = {
            START_TRANSPARENT: [0xff, 0x50, 0x00, 0x00, 0x02, 0x81, 0x00, 0x00],
            END_TRANSPARENT: [0xff, 0x50, 0x00, 0x00, 0x02, 0x82, 0x00, 0x00],
            RF_OFF: [0xff, 0x50, 0x00, 0x00, 0x02, 0x83, 0x00, 0x00],
            RF_ON: [0xff, 0x50, 0x00, 0x00, 0x02, 0x84, 0x00, 0x00],
        };

        // Chrome が WebUSB からの claim を禁止しているクラス
        static PROTECTED_CLASSES = new Set([0x01, 0x03, 0x08, 0x0b, 0x0e, 0x10, 0xe0]);

        constructor(device) {
            this.device = device;
            this.seq = 0;
            this.interfaceNumber = null;
            this.epIn = null;
            this.epOut = null;
            this.queue = Promise.resolve();
            this.closed = false;
        }

        // ---------------------------------------------------------------
        // 接続・切断
        // ---------------------------------------------------------------
        async open() {
            const d = this.device;
            if (!d.opened) await d.open();
            if (d.configuration === null) await d.selectConfiguration(1);

            const target = this.#findBulkInterface();
            if (!target) throw new Error('RC-S300 の通信用インターフェイスが見つかりません。');

            await d.claimInterface(target.interfaceNumber);
            this.interfaceNumber = target.interfaceNumber;
            this.epIn = target.epIn.endpointNumber;
            this.epOut = target.epOut.endpointNumber;

            await this.transceive(RCS300Reader.CMD.END_TRANSPARENT);
            await this.transceive(RCS300Reader.CMD.START_TRANSPARENT);
            await this.transceive(RCS300Reader.CMD.RF_OFF);
            await sleep(50);
            await this.transceive(RCS300Reader.CMD.RF_ON);
            await sleep(50);
        }

        async close() {
            if (this.closed) return;
            this.closed = true;
            try {
                await this.transceive(RCS300Reader.CMD.RF_OFF);
                await this.transceive(RCS300Reader.CMD.END_TRANSPARENT);
            } catch (_) { /* 抜去済みなど */ }
            try {
                if (this.interfaceNumber !== null) await this.device.releaseInterface(this.interfaceNumber);
            } catch (_) { /* noop */ }
            try {
                if (this.device.opened) await this.device.close();
            } catch (_) { /* noop */ }
        }

        // ---------------------------------------------------------------
        // FeliCa コマンド
        // ---------------------------------------------------------------

        /** FeliCa コマンドを送り、カードの応答パケット（先頭が長さバイト）を返す。無応答なら null */
        async felicaCommand(cmd, timeoutMs = 100) {
            const res = await this.transceive(this.#buildCommunicateThruEx(cmd, timeoutMs));
            if (res.length < 2) return null;
            const sw = (res[res.length - 2] << 8) | res[res.length - 1];
            if (sw !== 0x9000) return null;

            const pkt = RCS300Reader.parseTlv(res, 0, res.length - 2).get(0x97);
            if (!pkt || pkt.length < 2 || pkt[0] !== pkt.length) return null;
            return pkt;
        }

        /** Polling。systemCode=0xFFFF はワイルドカード、requestCode=0x01 でシステムコードも返す */
        async polling(systemCode = 0xffff, requestCode = 0x01) {
            const pkt = await this.felicaCommand([
                0x00, (systemCode >> 8) & 0xff, systemCode & 0xff, requestCode, 0x00,
            ]);
            if (!pkt || pkt[1] !== 0x01 || pkt.length < 18) return null;

            const idmBytes = pkt.slice(2, 10);
            const pmmBytes = pkt.slice(10, 18);
            return {
                idm: toHex(idmBytes),
                pmm: toHex(pmmBytes),
                idmBytes,
                pmmBytes,
                systemCode: requestCode === 0x01 && pkt.length >= 20 ? (pkt[18] << 8) | pkt[19] : null,
            };
        }

        /** 監視ループ用（IDm だけ欲しいとき） */
        async pollFelica() {
            const p = await this.polling(0xffff, 0x01);
            return p ? { idm: p.idm, pmm: p.pmm } : null;
        }

        /** Request System Code: カード内の全システムコード */
        async requestSystemCode(idmBytes) {
            const pkt = await this.felicaCommand([0x0c, ...idmBytes]);
            if (!pkt || pkt[1] !== 0x0d || pkt.length < 11) return null;
            const n = pkt[10];
            const codes = [];
            for (let i = 0; i < n && 12 + i * 2 < pkt.length; i++) {
                codes.push((pkt[11 + i * 2] << 8) | pkt[12 + i * 2]);
            }
            return codes;
        }

        /** Search Service Code: index 番目のエリア/サービス（2 バイト=サービス、4 バイト=エリア） */
        async searchServiceCode(idmBytes, index) {
            const pkt = await this.felicaCommand([0x0a, ...idmBytes, index & 0xff, (index >> 8) & 0xff]);
            if (!pkt || pkt[1] !== 0x0b) return null;
            const d = pkt.slice(10);
            if (d.length >= 4) return { type: 'area', code: d[0] | (d[1] << 8), end: d[2] | (d[3] << 8) };
            if (d.length >= 2) {
                const code = d[0] | (d[1] << 8);
                return code === 0xffff ? { type: 'end' } : { type: 'service', code };
            }
            return null;
        }

        /** Read Without Encryption: 1 ブロック（16 バイト）読む */
        async readBlock(idmBytes, serviceCode, blockNo) {
            const pkt = await this.felicaCommand([
                0x06, ...idmBytes,
                0x01, serviceCode & 0xff, (serviceCode >> 8) & 0xff, // サービス数・サービスコード（LE）
                0x01, 0x80, blockNo & 0xff,                         // ブロック数・ブロックリスト（2 バイト形式）
            ]);
            if (!pkt || pkt[1] !== 0x07 || pkt.length < 12) return { ok: false, status: null };
            if (pkt[10] !== 0x00) return { ok: false, status: [pkt[10], pkt[11]] };
            if (pkt.length < 29) return { ok: false, status: null };
            return { ok: true, data: pkt.slice(13, 29) };
        }

        // ---------------------------------------------------------------
        // カード全体の読み取り
        // ---------------------------------------------------------------

        /**
         * カード内の「認証なしで読める」データをすべて読み出す。
         * @param {object} opts
         *   maxBlocks   1 サービスあたりの最大ブロック数（最大 256）
         *   maxSearch   1 システムあたりのサービス検索上限
         *   onProgress  (message) => void
         *   isCancelled () => boolean
         */
        async scanCard({ maxBlocks = 64, maxSearch = 512, onProgress = () => { }, isCancelled = () => false } = {}) {
            const checkCancel = () => { if (isCancelled()) throw new ScanCancelled('読み取りを中止しました。'); };

            const first = await this.polling(0xffff, 0x01);
            if (!first) return null;

            const result = {
                scannedAt: new Date().toISOString(),
                idm: first.idm,
                pmm: first.pmm,
                icType: hex2(first.pmmBytes[1]),
                complete: true,
                warnings: [],
                systems: [],
            };

            try {
                onProgress('システムコード一覧を取得中…');
                let codes = await this.requestSystemCode(first.idmBytes);
                if (!codes || codes.length === 0) {
                    codes = first.systemCode !== null ? [first.systemCode] : [];
                    result.warnings.push('Request System Code に応答しないカードのため、Polling で得たシステムのみ読み取りました。');
                }

                for (const declared of codes) {
                    checkCancel();
                    await this.#scanSystem(declared, result, { maxBlocks, maxSearch, onProgress, checkCancel });
                }
            } catch (e) {
                // 途中まで読めた結果は捨てずに返す（USB 切断などは監視ループ側でも検出される）
                result.complete = false;
                result.warnings.push(e instanceof ScanCancelled
                    ? e.message
                    : `読み取り中にエラーが発生しました: ${e.message}`);
            }
            return result;
        }

        async #scanSystem(declared, result, { maxBlocks, maxSearch, onProgress, checkCancel }) {
            // システムごとに IDm が異なるため、そのシステムコードで Polling し直す
            let sc = declared;
            let p = await this.polling(sc, 0x00);
            if (!p) {
                const swapped = ((sc & 0xff) << 8) | (sc >> 8);
                if (swapped !== sc && (p = await this.polling(swapped, 0x00))) sc = swapped;
            }

            const sys = {
                systemCode: hex4(sc),
                name: SYSTEM_NAMES[sc] ?? null,
                idm: p?.idm ?? null,
                pmm: p?.pmm ?? null,
                note: null,
                error: null,
                areas: [],
                services: [],
            };
            result.systems.push(sys);

            if (!p) {
                sys.error = 'このシステムが Polling に応答しませんでした。';
                result.complete = false;
                return;
            }

            const isLite = sc === 0x88b4 || p.pmmBytes[1] === 0xf0 || p.pmmBytes[1] === 0xf1;
            if (isLite) {
                await this.#scanLite(p.idmBytes, sys, onProgress, checkCancel);
                return;
            }

            // --- エリア・サービスの列挙 ---
            for (let i = 0; i < maxSearch; i++) {
                checkCancel();
                onProgress(`システム ${sys.systemCode}: サービスを検索中（${i}）…`);
                const r = await this.searchServiceCode(p.idmBytes, i);
                if (!r) {
                    if (i === 0) {
                        sys.error = 'Search Service Code に応答しませんでした（このシステムはサービス一覧を公開していません）。';
                    } else {
                        sys.error = `サービス検索が途中（${i}件目）で途切れました。カードが離れた可能性があります。`;
                        result.complete = false;
                    }
                    break;
                }
                if (r.type === 'end') break;
                if (r.type === 'area') {
                    sys.areas.push({ code: hex4(r.code), end: hex4(r.end) });
                } else {
                    sys.services.push(describeService(r.code));
                }
                if (i === maxSearch - 1) {
                    result.warnings.push(`システム ${sys.systemCode}: サービス数が上限（${maxSearch}）に達したため打ち切りました。`);
                }
            }

            // --- 認証不要のサービスを読む ---
            const readNumbers = new Set();
            const limit = Math.min(maxBlocks, 256);
            for (const svc of sys.services) {
                if (svc.requiresAuth) { svc.status = 'auth'; continue; }
                // 同じサービス番号（＝同じデータ）を別属性で公開している「オーバーラップサービス」は 1 回だけ読む
                if (readNumbers.has(svc.number)) { svc.status = 'overlap'; continue; }

                for (let b = 0; b < limit; b++) {
                    checkCancel();
                    onProgress(`システム ${sys.systemCode} / サービス ${svc.code}: ブロック ${b} を読み取り中…`);
                    const rb = await this.readBlock(p.idmBytes, svc.codeNum, b);
                    if (!rb.ok) {
                        if (b === 0) {
                            svc.readError = rb.status
                                ? `ステータスフラグ ${hex2(rb.status[0])} ${hex2(rb.status[1])}`
                                : 'カードが応答しませんでした';
                        } else if (!rb.status) {
                            result.complete = false;
                            svc.readError = `ブロック ${b} で応答が途切れました`;
                        }
                        break;
                    }
                    svc.blocks.push({ no: b, hex: toHex(rb.data) });
                }
                if (svc.blocks.length === limit) svc.truncated = true;
                svc.status = svc.blocks.length > 0 ? 'read' : 'unreadable';
                if (svc.blocks.length > 0) readNumbers.add(svc.number);
            }
        }

        async #scanLite(idmBytes, sys, onProgress, checkCancel) {
            sys.note = 'FeliCa Lite / Lite-S はサービス検索に非対応のため、既知のブロック構成で読み取りました。';
            const svc = describeService(LITE_SERVICE);
            sys.services.push(svc);
            for (const [no, label] of LITE_BLOCKS) {
                checkCancel();
                onProgress(`FeliCa Lite: ブロック ${label} を読み取り中…`);
                const rb = await this.readBlock(idmBytes, LITE_SERVICE, no);
                if (rb.ok) svc.blocks.push({ no, label, hex: toHex(rb.data) });
            }
            svc.status = svc.blocks.length > 0 ? 'read' : 'unreadable';
        }

        /** STUDENT_ID_RULE で指定した位置だけを読む（全読み取りより高速） */
        async readField(rule) {
            const p = await this.polling(rule.systemCode, 0x00);
            if (!p) return null;
            const blockCount = Math.ceil((rule.offset + rule.length) / 16);
            const bytes = [];
            for (let i = 0; i < blockCount; i++) {
                const rb = await this.readBlock(p.idmBytes, rule.serviceCode, rule.block + i);
                if (!rb.ok) return null;
                bytes.push(...rb.data);
            }
            return FelicaAnalyzer.decodeField(bytes.slice(rule.offset, rule.offset + rule.length), rule.encoding);
        }

        // ---------------------------------------------------------------
        // 低レベル
        // ---------------------------------------------------------------

        /** RC-S300 にコマンドを送り、応答のデータ部（ヘッダー除去済み）を返す */
        transceive(payload) {
            const run = async () => {
                this.seq = (this.seq + 1) & 0xff;
                const seq = this.seq;
                const len = payload.length;

                const packet = new Uint8Array(10 + len);
                packet[0] = 0x6b;               // PC_to_RDR_Escape
                packet[1] = len & 0xff;         // データ長（リトルエンディアン 4 バイト）
                packet[2] = (len >> 8) & 0xff;
                packet[3] = (len >> 16) & 0xff;
                packet[4] = (len >> 24) & 0xff;
                packet[5] = 0x00;               // スロット番号
                packet[6] = seq;                // シーケンス番号（応答にそのまま返る）
                packet.set(payload, 10);

                const out = await this.device.transferOut(this.epOut, packet);
                if (out.status !== 'ok') throw new Error(`transferOut 失敗: ${out.status}`);

                // 1 ブロック読み取りの応答は最大 55 バイトなので 64 バイトで足りる
                const result = await this.device.transferIn(this.epIn, 64);
                if (result.status !== 'ok' || !result.data) throw new Error(`transferIn 失敗: ${result.status}`);

                const bytes = new Uint8Array(result.data.buffer, result.data.byteOffset, result.data.byteLength);
                if (bytes.length < 10 || bytes[0] !== 0x83) throw new Error('RC-S300 から想定外の応答を受信しました。');
                if (bytes[6] !== seq) throw new Error('応答のシーケンス番号が一致しません。');

                const dataLen = bytes[1] | (bytes[2] << 8) | (bytes[3] << 16) | (bytes[4] << 24);
                return bytes.slice(10, 10 + dataLen);
            };

            const p = this.queue.then(run, run);
            this.queue = p.catch(() => { });
            return p;
        }

        #buildCommunicateThruEx(felicaCmd, timeoutMs) {
            const timeoutUs = timeoutMs * 1000;
            const cmdLen = felicaCmd.length + 1; // FeliCa パケットは先頭に「長さ」バイトが付く
            const req = [
                0x5f, 0x46, 0x04,
                timeoutUs & 0xff, (timeoutUs >> 8) & 0xff, (timeoutUs >> 16) & 0xff, (timeoutUs >> 24) & 0xff,
                0x95, 0x82, (cmdLen >> 8) & 0xff, cmdLen & 0xff,
                cmdLen, ...felicaCmd,
            ];
            return [
                0xff, 0x50, 0x00, 0x01, 0x00,
                (req.length >> 8) & 0xff, req.length & 0xff,
                ...req,
                0x00, 0x00, 0x00,
            ];
        }

        #findBulkInterface() {
            const candidates = [];
            for (const iface of this.device.configuration.interfaces) {
                const alt = iface.alternate ?? iface.alternates[0];
                if (!alt || RCS300Reader.PROTECTED_CLASSES.has(alt.interfaceClass)) continue;
                const epIn = alt.endpoints.find((e) => e.direction === 'in' && e.type === 'bulk');
                const epOut = alt.endpoints.find((e) => e.direction === 'out' && e.type === 'bulk');
                if (epIn && epOut) {
                    candidates.push({ interfaceNumber: iface.interfaceNumber, cls: alt.interfaceClass, epIn, epOut });
                }
            }
            candidates.sort((a, b) => (b.cls === 0xff) - (a.cls === 0xff));
            return candidates[0] ?? null;
        }

        static parseTlv(bytes, start, end) {
            const map = new Map();
            let i = start;
            while (i + 2 <= end) {
                const tag = bytes[i++];
                let len = bytes[i++];
                if (len === 0x81) {
                    len = bytes[i++];
                } else if (len === 0x82) {
                    len = (bytes[i] << 8) | bytes[i + 1];
                    i += 2;
                }
                if (i + len > end) break;
                map.set(tag, bytes.slice(i, i + len));
                i += len;
            }
            return map;
        }
    }

    // =====================================================================
    // 読み取り結果の解析（文字列化・学籍番号候補の抽出）
    // =====================================================================
    const sjisDecoder = (() => {
        try { return new TextDecoder('shift_jis'); } catch (_) { return null; }
    })();

    const FelicaAnalyzer = {
        toHex,
        fromHex,
        hex4,

        /** 1 バイト → 表示用 ASCII（印字不可は「.」） */
        asciiOf(bytes) {
            return Array.from(bytes, (b) => (b >= 0x20 && b <= 0x7e ? String.fromCharCode(b) : '.')).join('');
        },

        /** Shift_JIS として解釈（制御文字・不正バイトは「·」に置換）。文字として読めなければ null */
        sjisOf(bytes) {
            if (!sjisDecoder) return null;
            const text = sjisDecoder.decode(Uint8Array.from(bytes))
                .replace(/[\u0000-\u001f\u007f\ufffd\uf8f0-\uf8ff]/g, '·')
                .replace(/·{2,}/g, '··')
                .replace(/^[·\s]+|[·\s]+$/g, '');
            const meaningful = text.replace(/[·\s]/g, '');
            return meaningful.length >= 2 ? text : null;
        },

        /** 指定エンコーディングで値を取り出す */
        decodeField(bytes, encoding = 'ascii') {
            const u8 = Uint8Array.from(bytes);
            let s;
            switch (encoding) {
                case 'shift_jis':
                    s = sjisDecoder ? sjisDecoder.decode(u8) : FelicaAnalyzer.asciiOf(u8);
                    break;
                case 'bcd':
                    s = Array.from(u8, (b) => `${b >> 4}${b & 0x0f}`).join('');
                    break;
                case 'hex':
                    s = toHex(u8);
                    break;
                default:
                    s = Array.from(u8, (b) => (b >= 0x20 && b <= 0x7e ? String.fromCharCode(b) : '')).join('');
            }
            return s.replace(/[\u0000-\u001f\u007f\ufffd]/g, '').trim();
        },

        /** サービスのブロックを連結し、各バイトの位置（ブロック番号・ブロック内オフセット）も返す */
        flatten(service) {
            const bytes = [];
            const pos = [];
            for (const blk of service.blocks) {
                fromHex(blk.hex).forEach((b, i) => {
                    bytes.push(b);
                    pos.push({ block: blk.no, offset: i });
                });
            }
            return { bytes, pos };
        },

        /**
         * 文字列として読める英数字の並び（学籍番号などの候補）を探す。
         * 5〜20 文字の英数字で、数字を 4 文字以上含むもの。
         */
        findCandidates(scan) {
            const out = [];
            for (const sys of scan?.systems ?? []) {
                for (const svc of sys.services) {
                    if (!svc.blocks.length) continue;
                    const { bytes, pos } = FelicaAnalyzer.flatten(svc);
                    const ascii = bytes.map((b) => (b >= 0x20 && b <= 0x7e ? String.fromCharCode(b) : '\u0000')).join('');
                    for (const m of ascii.matchAll(/[0-9A-Za-z]{5,20}/g)) {
                        const digits = (m[0].match(/[0-9]/g) ?? []).length;
                        if (digits < 4) continue;
                        const at = pos[m.index];
                        out.push({
                            value: m[0],
                            systemCode: sys.systemCode,
                            serviceCode: svc.code,
                            block: at.block,
                            offset: at.offset,
                            length: m[0].length,
                            rule: `{ systemCode: 0x${sys.systemCode}, serviceCode: 0x${svc.code}, `
                                + `block: ${at.block}, offset: ${at.offset}, length: ${m[0].length}, encoding: 'ascii' }`,
                        });
                    }
                }
            }
            return out;
        },

        /** 全読み取り結果から STUDENT_ID_RULE の位置の値を取り出す */
        extractField(scan, rule) {
            const sys = scan?.systems.find((s) => s.systemCode === hex4(rule.systemCode));
            const svc = sys?.services.find((s) => s.code === hex4(rule.serviceCode));
            if (!svc) return null;
            const bytes = [];
            const blockCount = Math.ceil((rule.offset + rule.length) / 16);
            for (let i = 0; i < blockCount; i++) {
                const blk = svc.blocks.find((b) => b.no === rule.block + i);
                if (!blk) return null;
                bytes.push(...fromHex(blk.hex));
            }
            return FelicaAnalyzer.decodeField(bytes.slice(rule.offset, rule.offset + rule.length), rule.encoding);
        },
    };

    global.RCS300Reader = RCS300Reader;
    global.FelicaAnalyzer = FelicaAnalyzer;
})(typeof window !== 'undefined' ? window : globalThis);