<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>FeliCa カードリーダー</title>
    @vite('resources/js/rcs300-felica.js')
    <style>
        :root {
            color-scheme: light dark;
            --accent: #2563eb;
            --ok: #16a34a;
            --ng: #dc2626;
            --warn: #b45309;
            --idle: #9ca3af;
            --line: #8885;
            --soft: #8881;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            background: Canvas;
            color: CanvasText;
            font-family: system-ui, -apple-system, "Segoe UI", "Hiragino Sans", "Noto Sans JP", sans-serif;
            line-height: 1.6;
        }
        main { max-width: 880px; margin: 0 auto; padding: 24px 16px 48px; }
        h1 { font-size: 1.5rem; margin: 0 0 4px; }
        h2 { font-size: 1.1rem; margin: 0 0 8px; }
        h3 { font-size: 1rem; margin: 16px 0 6px; }
        .lead { margin: 0 0 20px; opacity: .8; }
        .mono { font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; }
        .alert { border: 1px solid var(--ng); border-radius: 8px; padding: 12px 14px; margin-bottom: 16px; color: var(--ng); }
        .controls { display: flex; flex-wrap: wrap; align-items: center; gap: 8px 16px; margin-bottom: 16px; }
        button {
            font: inherit; padding: 10px 16px; border-radius: 8px;
            border: 1px solid var(--accent); background: var(--accent); color: #fff; cursor: pointer;
        }
        button.secondary { background: transparent; color: var(--accent); }
        button.small { padding: 2px 10px; font-size: .8rem; }
        button:disabled { opacity: .45; cursor: not-allowed; }
        label.option { display: inline-flex; align-items: center; gap: 6px; font-size: .9rem; }
        .panel { border: 1px solid var(--line); border-radius: 12px; padding: 16px; margin-bottom: 16px; }
        .status-row { display: flex; align-items: center; gap: 8px; font-weight: 600; }
        .dot { width: 10px; height: 10px; border-radius: 50%; background: var(--idle); flex: none; }
        .dot.ready { background: var(--accent); }
        .dot.ok { background: var(--ok); }
        .dot.error { background: var(--ng); }
        .progress { min-height: 1.4em; font-size: .85rem; opacity: .75; }
        .label { margin-top: 14px; font-size: .85rem; opacity: .7; }
        .big { font-size: clamp(1.25rem, 6vw, 2rem); letter-spacing: .06em; word-break: break-all; }
        .muted { font-size: .85rem; opacity: .7; }
        .kv { display: grid; grid-template-columns: max-content 1fr; gap: 2px 16px; margin: 0; font-size: .9rem; }
        .kv dt { opacity: .7; }
        .kv dd { margin: 0; word-break: break-all; }
        .warnings { color: var(--warn); font-size: .9rem; padding-left: 1.2em; }
        .candidates { list-style: none; padding: 0; margin: 0; }
        .candidates li { padding: 8px 10px; border: 1px solid var(--line); border-radius: 8px; margin-bottom: 6px; }
        .candidates .value { font-weight: 700; font-size: 1.1rem; }
        .candidates code { display: block; font-size: .75rem; opacity: .8; word-break: break-all; margin-top: 4px; }
        details { border: 1px solid var(--line); border-radius: 8px; padding: 8px 12px; margin: 8px 0; }
        details > summary { cursor: pointer; font-weight: 600; }
        details details { background: var(--soft); }
        .scroll { overflow-x: auto; }
        table.dump { border-collapse: collapse; font-size: .8rem; margin-top: 6px; white-space: nowrap; }
        table.dump th, table.dump td { padding: 2px 10px 2px 0; text-align: left; vertical-align: top; }
        table.dump th { font-weight: 600; opacity: .7; border-bottom: 1px solid var(--line); }
        .sjis { font-size: .9rem; margin-top: 6px; word-break: break-all; }
        .log { list-style: none; margin: 0; padding: 0; max-height: 240px; overflow-y: auto; font-size: .9rem; }
        .log li { padding: 6px 0; border-bottom: 1px solid var(--line); }
        .log li.error { color: var(--ng); }
        .log time { opacity: .6; margin-right: 8px; }
    </style>
</head>
<body>
<main id="app" data-submit-url="{{ route('felica.store', [], false) }}">
    <h1>FeliCa カードリーダー</h1>
    <p class="lead">Sony PaSoRi RC-S300 を WebUSB で接続し、カードの IDm・学籍番号・カード内データを表示します。</p>

    <div id="support-warning" class="alert" hidden></div>

    <div class="controls">
        <button id="btn-connect" type="button">リーダーを接続して読み取り開始</button>
        <button id="btn-disconnect" type="button" class="secondary" disabled>切断</button>
        <label class="option">
            <input id="opt-full-scan" type="checkbox" checked>
            タッチ時にカード内の全データを読み取る（調査用・数秒かかります）
        </label>
    </div>

    <section class="panel">
        <div class="status-row">
            <span id="status-dot" class="dot"></span>
            <span id="status-text">未接続</span>
        </div>
        <div id="progress" class="progress"></div>

        <div class="label">IDm</div>
        <div id="idm" class="big mono">----------------</div>

        <div id="student-box" hidden>
            <div class="label">学籍番号</div>
            <div id="student-id" class="big mono">-</div>
        </div>

        <div id="device-info" class="muted"></div>
    </section>

    <section id="card-panel" class="panel" hidden>
        <h2>カード情報</h2>
        <dl id="card-summary" class="kv"></dl>
        <ul id="card-warnings" class="warnings"></ul>

        <h3>学籍番号などの候補（文字列として読めた英数字）</h3>
        <p class="muted">
            学籍番号に当たるものを見つけたら「設定をコピー」を押し、画面下部スクリプトの
            <span class="mono">STUDENT_ID_RULE</span> に貼り付けてください（前後の余分な文字は offset / length で調整）。
        </p>
        <ul id="candidates" class="candidates"></ul>

        <h3>カード内の全データ</h3>
        <div id="systems"></div>

        <button id="btn-download" type="button" class="secondary small">読み取り結果を JSON で保存</button>
    </section>

    <section class="panel">
        <h2>ログ</h2>
        <ul id="log" class="log"></ul>
    </section>
</main>

<script type="module">
(() => {
    'use strict';

    // =====================================================================
    // 学籍番号の位置（カードを調べて分かったら設定する）
    //   null のままなら「全データ表示＋候補表示」のみ。
    //   例: const STUDENT_ID_RULE = {
    //           systemCode: 0xFE00, serviceCode: 0x1A8B,
    //           block: 0, offset: 2, length: 8, encoding: 'ascii',
    //       };
    //   encoding: 'ascii' | 'shift_jis' | 'bcd' | 'hex'
    // =====================================================================
    const STUDENT_ID_RULE = null;

    // =====================================================================
    // 設定
    // =====================================================================
    const app = document.getElementById('app');
    const SUBMIT_URL = app.dataset.submitUrl;
    const CSRF_TOKEN = document.querySelector('meta[name="csrf-token"]').content;

    const POLL_INTERVAL_MS = 250; // ポーリング間隔
    const REMOVE_THRESHOLD = 2;   // 連続でカード無しがこの回数続いたら「離れた」とみなす
    const MAX_ERRORS = 3;         // 連続エラーがこの回数に達したら停止
    const MAX_BLOCKS = 64;        // 1 サービスあたり読み取る最大ブロック数

    const { toHex, fromHex, asciiOf, sjisOf } = window.FelicaAnalyzer ?? {};
    const sleep = (ms) => new Promise((resolve) => setTimeout(resolve, ms));
    const hex2 = (n) => n.toString(16).toUpperCase().padStart(2, '0');

    // =====================================================================
    // UI ヘルパー（カードの中身は信頼できないデータなので textContent だけで描画する）
    // =====================================================================
    const $ = (id) => document.getElementById(id);
    const ui = {
        warning: $('support-warning'),
        connect: $('btn-connect'),
        disconnect: $('btn-disconnect'),
        fullScan: $('opt-full-scan'),
        dot: $('status-dot'),
        status: $('status-text'),
        progress: $('progress'),
        idm: $('idm'),
        studentBox: $('student-box'),
        studentId: $('student-id'),
        device: $('device-info'),
        cardPanel: $('card-panel'),
        summary: $('card-summary'),
        warnings: $('card-warnings'),
        candidates: $('candidates'),
        systems: $('systems'),
        download: $('btn-download'),
        log: $('log'),
    };

    function h(tag, props = {}, ...children) {
        const el = document.createElement(tag);
        for (const [k, v] of Object.entries(props)) {
            if (k === 'class') el.className = v;
            else el[k] = v;
        }
        for (const c of children.flat()) {
            if (c === null || c === undefined || c === false) continue;
            el.append(typeof c === 'object' ? c : document.createTextNode(String(c)));
        }
        return el;
    }

    function setStatus(text, state = 'idle') {
        ui.status.textContent = text;
        ui.dot.className = `dot ${state}`;
    }

    function addLog(text, type = 'info') {
        const li = h('li', { class: type === 'error' ? 'error' : '' },
            h('time', { class: 'mono' }, new Date().toLocaleTimeString()), text);
        ui.log.prepend(li);
        while (ui.log.children.length > 100) ui.log.lastElementChild.remove();
    }

    function setConnected(connected) {
        ui.connect.disabled = connected;
        ui.disconnect.disabled = !connected;
    }

    function explainError(e) {
        switch (e?.name) {
            case 'NotFoundError':
                return 'デバイスが選択されませんでした。';
            case 'SecurityError':
                return `USB へのアクセスが拒否されました（${e.message}）。他のドライバ/アプリが使用中、`
                    + 'または Permissions-Policy で usb が禁止されている可能性があります。';
            case 'NetworkError':
                return `USB 通信に失敗しました（${e.message}）。他のタブやアプリがリーダーを使っていないか、`
                    + 'ケーブルが抜けていないか確認してください。';
            case 'InvalidStateError':
                return `デバイスの状態が不正です（${e.message}）。リーダーを挿し直して再接続してください。`;
            default:
                return e?.message ?? String(e);
        }
    }

    // =====================================================================
    // 読み取り結果の表示
    // =====================================================================
    let lastScan = null;

    function renderStudentId(value) {
        if (!STUDENT_ID_RULE) return;
        ui.studentBox.hidden = false;
        ui.studentId.textContent = value || '（読み取れませんでした）';
    }

    function renderScan(scan) {
        ui.cardPanel.hidden = false;

        // --- 概要 ---
        const readable = scan.systems.flatMap((s) => s.services).filter((s) => s.status === 'read');
        const summary = [
            ['IDm', scan.idm],
            ['PMm', scan.pmm],
            ['IC 種別コード', `0x${scan.icType}`],
            ['システム', scan.systems.map((s) => s.systemCode).join(', ') || '-'],
            ['読めたサービス', `${readable.length} 件（${readable.reduce((n, s) => n + s.blocks.length, 0)} ブロック）`],
            ['読み取り日時', new Date(scan.scannedAt).toLocaleString()],
            ['状態', scan.complete ? '完了' : '一部のみ（カードが離れた・中止など）'],
        ];
        ui.summary.replaceChildren(...summary.flatMap(([k, v]) => [h('dt', {}, k), h('dd', { class: 'mono' }, v)]));
        ui.warnings.replaceChildren(...scan.warnings.map((w) => h('li', {}, w)));

        // --- 学籍番号などの候補 ---
        const cands = window.FelicaAnalyzer.findCandidates(scan);
        ui.candidates.replaceChildren(
            ...(cands.length
                ? cands.map((c) => {
                    const copy = h('button', { type: 'button', class: 'secondary small' }, '設定をコピー');
                    copy.addEventListener('click', async () => {
                        try {
                            await navigator.clipboard.writeText(c.rule);
                            copy.textContent = 'コピーしました';
                        } catch (_) {
                            copy.textContent = 'コピーできませんでした';
                        }
                    });
                    return h('li', {},
                        h('span', { class: 'value mono' }, c.value), ' ', copy,
                        h('div', { class: 'muted' },
                            `システム ${c.systemCode} / サービス ${c.serviceCode} / ブロック ${c.block} / `
                            + `オフセット ${c.offset} / 長さ ${c.length}`),
                        h('code', { class: 'mono' }, c.rule));
                })
                : [h('li', { class: 'muted' },
                    '文字列として読める英数字は見つかりませんでした。数値（BCD・バイナリ）で記録されているか、'
                    + '暗号化された領域にある可能性があります。下の 16 進ダンプを確認してください。')]),
        );

        // --- システムごとの全データ ---
        ui.systems.replaceChildren(...scan.systems.map(renderSystem));
        if (scan.systems.length === 0) {
            ui.systems.append(h('p', { class: 'muted' }, 'システムを取得できませんでした。'));
        }
    }

    function renderSystem(sys) {
        const body = [
            h('div', { class: 'muted mono' }, `IDm ${sys.idm ?? '-'} / PMm ${sys.pmm ?? '-'}`),
        ];
        if (sys.note) body.push(h('p', { class: 'muted' }, sys.note));
        if (sys.error) body.push(h('p', { class: 'warnings' }, sys.error));
        if (sys.areas.length) {
            body.push(h('div', { class: 'muted' },
                'エリア: ', h('span', { class: 'mono' }, sys.areas.map((a) => `${a.code}–${a.end}`).join(', '))));
        }

        const read = sys.services.filter((s) => s.status === 'read');
        const unreadable = sys.services.filter((s) => s.status === 'unreadable');
        const auth = sys.services.filter((s) => s.status === 'auth');
        const overlap = sys.services.filter((s) => s.status === 'overlap');

        body.push(...read.map(renderService));

        if (unreadable.length) {
            body.push(h('div', { class: 'muted' }, '認証不要だが読めなかったサービス: ',
                h('span', { class: 'mono' }, unreadable.map((s) => `${s.code}（${s.readError ?? '不明'}）`).join(', '))));
        }
        if (auth.length) {
            body.push(h('div', { class: 'muted' }, '認証が必要なため読めないサービス（鍵が必要）: ',
                h('span', { class: 'mono' }, auth.map((s) => s.code).join(', '))));
        }
        if (overlap.length) {
            body.push(h('div', { class: 'muted' }, '同じデータを別属性で公開しているため省略: ',
                h('span', { class: 'mono' }, overlap.map((s) => s.code).join(', '))));
        }
        if (!sys.services.length && !sys.error) {
            body.push(h('p', { class: 'muted' }, 'サービスは見つかりませんでした。'));
        }

        const title = `システム ${sys.systemCode}${sys.name ? `（${sys.name}）` : ''}`
            + ` — 読めたサービス ${read.length} / 全 ${sys.services.length}`;
        return h('details', { open: true }, h('summary', {}, title), ...body);
    }

    function renderService(svc) {
        const rows = svc.blocks.map((blk) => {
            const bytes = fromHex(blk.hex);
            const no = blk.label ? `${hex2(blk.no)} ${blk.label}` : hex2(blk.no);
            return h('tr', {},
                h('td', { class: 'mono' }, no),
                h('td', { class: 'mono' }, toHex(bytes, ' ')),
                h('td', { class: 'mono' }, asciiOf(bytes)));
        });
        const table = h('div', { class: 'scroll' },
            h('table', { class: 'dump' },
                h('thead', {}, h('tr', {}, h('th', {}, 'ブロック'), h('th', {}, '16進'), h('th', {}, 'ASCII'))),
                h('tbody', {}, ...rows)));

        const allBytes = svc.blocks.flatMap((b) => Array.from(fromHex(b.hex)));
        const text = sjisOf(allBytes);

        const title = `サービス ${svc.code} — ${svc.kind}・${svc.access}・認証不要 — `
            + `${svc.blocks.length} ブロック${svc.truncated ? `（上限 ${MAX_BLOCKS} で打ち切り）` : ''}`;
        return h('details', { open: true },
            h('summary', { class: 'mono' }, title),
            table,
            text ? h('div', { class: 'sjis' }, 'Shift_JIS として表示: ', h('span', { class: 'mono' }, text)) : null,
            svc.readError ? h('div', { class: 'warnings' }, svc.readError) : null);
    }

    ui.download.addEventListener('click', () => {
        if (!lastScan) return;
        const blob = new Blob([JSON.stringify(lastScan, null, 2)], { type: 'application/json' });
        const a = document.createElement('a');
        a.href = URL.createObjectURL(blob);
        a.download = `felica-${lastScan.idm}-${lastScan.scannedAt.replace(/[:.]/g, '-')}.json`;
        a.click();
        setTimeout(() => URL.revokeObjectURL(a.href), 1000);
    });

    // =====================================================================
    // サーバー送信（Fetch API）
    // =====================================================================
    async function submitCard(idm, studentId) {
        const res = await fetch(SUBMIT_URL, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': CSRF_TOKEN,
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify({ idm, student_id: studentId || null }),
        });

        const json = await res.json().catch(() => ({}));
        if (res.status === 419) {
            throw new Error('セッションの有効期限が切れました。ページを再読み込みしてください。');
        }
        if (!res.ok) {
            throw new Error(json.message ?? `HTTP ${res.status}`);
        }
        return json;
    }

    // =====================================================================
    // カードがタッチされたときの処理
    // =====================================================================
    async function handleTouch(card) {
        ui.idm.textContent = card.idm.match(/.{2}/g).join(' ');
        addLog(`検出 IDm=${card.idm} PMm=${card.pmm}`);
        let studentId = null;

        if (ui.fullScan.checked) {
            setStatus('カード内のデータを読み取り中…カードを離さないでください', 'ok');
            const scan = await reader.scanCard({
                maxBlocks: MAX_BLOCKS,
                onProgress: (message) => { ui.progress.textContent = message; },
                isCancelled: () => !running,
            });
            ui.progress.textContent = '';
            if (scan) {
                lastScan = scan;
                if (STUDENT_ID_RULE) studentId = window.FelicaAnalyzer.extractField(scan, STUDENT_ID_RULE);
                renderScan(scan);
                addLog(scan.complete
                    ? '全データの読み取りが完了しました。'
                    : '一部のデータだけ読み取れました（詳細はカード情報欄）。', scan.complete ? 'info' : 'error');
            }
        } else if (STUDENT_ID_RULE) {
            studentId = await reader.readField(STUDENT_ID_RULE);
        }

        renderStudentId(studentId);
        if (studentId) addLog(`学籍番号 ${studentId}`);
        setStatus('読み取り完了。次のカードをかざしてください', 'ok');

        submitCard(card.idm, studentId) // await しない＝送信中もポーリングを継続
            .then((json) => addLog(`✔ ${json.message ?? '送信しました'}`))
            .catch((e) => addLog(`✖ 送信失敗: ${e.message}`, 'error'));
    }

    // =====================================================================
    // 接続・ポーリング制御
    // =====================================================================
    let reader = null;
    let running = false;
    let loopPromise = null;

    async function pickDevice() {
        const paired = (await navigator.usb.getDevices()).filter((d) =>
            window.RCS300Reader.FILTERS.some((f) => f.vendorId === d.vendorId && f.productId === d.productId));
        if (paired.length === 1) return paired[0];
        return navigator.usb.requestDevice({ filters: window.RCS300Reader.FILTERS });
    }

    async function pollLoop() {
        let presentIdm = null;
        let missCount = 0;
        let errorCount = 0;

        setStatus('カードをかざしてください', 'ready');

        while (running) {
            try {
                const card = await reader.pollFelica();
                errorCount = 0;

                if (card) {
                    missCount = 0;
                    // 同じカードを置きっぱなしにしている間は再処理しない（タッチ単位で 1 回）
                    if (card.idm !== presentIdm) {
                        presentIdm = card.idm;
                        await handleTouch(card); // 全データ読み取りでリーダーを使うので待つ
                    }
                } else if (presentIdm !== null && ++missCount >= REMOVE_THRESHOLD) {
                    presentIdm = null;
                    setStatus('カードをかざしてください', 'ready');
                }
            } catch (e) {
                console.error(e);
                if (++errorCount >= MAX_ERRORS) {
                    addLog(`読み取りを停止しました: ${explainError(e)}`, 'error');
                    setStatus('エラーにより停止しました', 'error');
                    running = false;
                    break;
                }
            }
            await sleep(POLL_INTERVAL_MS);
        }
    }

    async function teardown() {
        const r = reader;
        reader = null;
        if (r) await r.close();
        setConnected(false);
        ui.device.textContent = '';
        ui.progress.textContent = '';
    }

    async function connect() {
        setConnected(true);
        setStatus('接続中…');

        try {
            const device = await pickDevice();
            reader = new window.RCS300Reader(device);
            await reader.open();

            ui.device.textContent = `${device.manufacturerName ?? 'Sony'} ${device.productName ?? 'RC-S300'}`
                + ` (VID 0x${device.vendorId.toString(16).padStart(4, '0')}`
                + ` / PID 0x${device.productId.toString(16).padStart(4, '0')})`;
            addLog('リーダーに接続しました。');

            running = true;
            loopPromise = pollLoop().finally(teardown);
        } catch (e) {
            console.error(e);
            addLog(explainError(e), 'error');
            setStatus('未接続', e?.name === 'NotFoundError' ? 'idle' : 'error');
            await teardown();
        }
    }

    async function disconnect() {
        ui.disconnect.disabled = true;
        running = false;
        if (loopPromise) await loopPromise;
        loopPromise = null;
        setStatus('未接続');
        addLog('切断しました。');
    }

    // =====================================================================
    // 初期化
    // =====================================================================
    function showWarning(message) {
        ui.warning.textContent = message;
        ui.warning.hidden = false;
        ui.connect.disabled = true;
    }

    if (STUDENT_ID_RULE) ui.studentBox.hidden = false;

    if (!window.isSecureContext) {
        showWarning('WebUSB は HTTPS（または localhost）でのみ利用できます。HTTPS でアクセスしてください。');
        return;
    }
    if (!('usb' in navigator)) {
        showWarning('このブラウザは WebUSB に対応していません。Chrome / Edge などの Chromium 系ブラウザを使用してください。');
        return;
    }
    if (!window.RCS300Reader) {
        showWarning('rcs300-felica.js を読み込めませんでした。npm run dev（開発時）または npm run build を実行したか確認してください。');
        return;
    }

    ui.connect.addEventListener('click', connect);
    ui.disconnect.addEventListener('click', disconnect);

    navigator.usb.addEventListener('disconnect', (event) => {
        if (reader && event.device === reader.device) {
            running = false;
            addLog('リーダーが取り外されました。', 'error');
            setStatus('リーダーが取り外されました', 'error');
        }
    });

    window.addEventListener('pagehide', () => { running = false; });
})();
</script>
</body>
</html>