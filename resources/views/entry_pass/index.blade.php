@extends('layouts.app')
@section('title', '入校証の照合・登録')
@push('styles')
    <style>
        .back {
            display: grid; place-content: center; place-items: center; background-color: var(--color-main); width: 100%; height: 500px; gap: 30px;
        }
        .felica-btn {
            color: var(--color-main); background-color: var(--color-sub); padding: 20px 40px; border-radius: 20px; font-size: 1.5em; box-shadow: 4px 4px 10px rgba(0, 0, 0, 0.15);
        }
        button.felica-btn {
            cursor: pointer;
        }
        button.felica-btn:disabled {
            opacity: 0.5; cursor: not-allowed;
        }

        /* =========================================================
           画面の切り替え
           JS は .back の data-screen を書き換えるだけで、どの画面を見せるかは CSS が決める。
           表示中の画面は display: contents にして、中身を .back のグリッドにそのまま並べる。
        ========================================================== */
        .screen {
            display: none;
        }
        .back[data-screen="start"] > .screen-start,
        .back[data-screen="connecting"] > .screen-connecting,
        .back[data-screen="ready"] > .screen-ready,
        .back[data-screen="ask"] > .screen-ask,
        .back[data-screen="link"] > .screen-link,
        .back[data-screen="greeting"] > .screen-greeting {
            display: contents;
        }

        /* 接続中・待機中はゆっくり点滅させる */
        .screen-connecting .felica-btn,
        .screen-ready .felica-btn {
            animation: felica-pulse 1.6s ease-in-out infinite;
        }
        @keyframes felica-pulse {
            50% { opacity: 0.6; }
        }
        @media (prefers-reduced-motion: reduce) {
            .screen-connecting .felica-btn,
            .screen-ready .felica-btn {
                animation: none;
            }
        }

        /* 画面の下に出す補足（空のときは場所を取らない） */
        .screen-note {
            color: var(--color-sub); text-align: center; font-size: 0.9em; min-height: 1.5em; max-width: 32em;
        }
        .screen-note:empty {
            visibility: hidden;
        }

        .text-btn {
            color: var(--color-sub); text-decoration: underline; cursor: pointer; font-size: 0.9em;
        }

        .student-name {
            background-color: var(--color-sub); color: var(--color-main); padding: 40px 20px;
        }
        .student-name > div {
            display: grid;
            gap: 5px;
        }
        .student-name label {
            font-size: 0.9em;
        }

        .search-container {
            position: relative;
            width: 100%;
        }
        .search-container input {
            padding: 5px 10px;
            outline: none;
            border: 1px solid var(--color-main);
            width: 100%;
        }
        .search-container ul {
            position: absolute;
            width: 100%;
            top: 100%;
            left: 0;
            background-color: var(--color-sub);
            max-height: 150px;
            overflow-y: auto;
            display: none;
            z-index: 10;
            box-shadow: 4px 4px 10px rgba(0, 0, 0, 0.15);
        }
        /* 検索欄（または候補）にフォーカスがあり、候補が1件以上あるときだけ一覧を開く */
        .search-container:focus-within ul:has(> li) {
            display: block;
        }
        .search-container li {
            padding: 15px 20px;
            cursor: pointer;
            background-color: var(--color-sub);
            display: flex;
            align-items: flex-end;
            gap: 7px;
        }
        .search-container li:hover,
        .search-container li:focus {
            filter: brightness(0.95);
            outline: none;
        }
        .search-container li span:nth-child(2) {
            font-size: 0.9em;
            opacity: 0.7;
        }
        .search-container li.empty {
            cursor: default;
            opacity: 0.7;
        }
        .search-container li.empty:hover {
            filter: none;
        }

        /* =========================================================
           ログ
        ========================================================== */
        .log-panel {
            margin-top: 20px; padding: 16px 20px; background-color: var(--color-sub); color: var(--color-font);
        }
        .log-panel h2 {
            font-size: 1em; margin-bottom: 8px; opacity: 0.6; font-weight: bold;
        }
        .log-list {
            max-height: 240px; overflow-y: auto; font-size: 0.9em;
        }
        .log-list:empty::before {
            content: 'まだログはありません'; opacity: 0.6;
        }
        .log-list li {
            padding: 6px 0; border-bottom: 1px solid color-mix(in srgb, var(--color-main) 25%, transparent);
        }
        .log-list li.error {
            color: color-mix(in srgb, var(--color-accent) 55%, var(--color-font));
        }
        .log-list time {
            font-family: ui-monospace, monospace; opacity: 0.6; margin-right: 8px;
        }
    </style>
@endpush

@section('content')

    <main class="main">
        <div class="content-wrapper">
            <div>
                <div class="back" id="entry-pass" data-screen="start"
                     data-touch-url="{{ route('entry-pass.touch', [], false) }}"
                     data-search-url="{{ route('entry-pass.students.search', [], false) }}"
                     data-link-url="{{ route('entry-pass.link', [], false) }}">

                    {{-- 1. 読み取り開始 --}}
                    <div class="screen screen-start">
                        <div><button type="button" class="felica-btn" id="btn-start">読み取り開始</button></div>
                        <div><p class="screen-note" id="start-note"></p></div>
                    </div>

                    {{-- 2. 接続中 --}}
                    <div class="screen screen-connecting">
                        <div><p class="felica-btn">接続中・・・</p></div>
                        <div><p class="screen-note" id="connect-progress"></p></div>
                    </div>

                    {{-- 3. カード待ち --}}
                    <div class="screen screen-ready">
                        <div><p class="felica-btn">入校証をタッチしてください</p></div>
                        <div><p class="screen-note" id="ready-note"></p></div>
                    </div>

                    {{-- 4. 生徒が決まっていない入校証 --}}
                    <div class="screen screen-ask">
                        <div><p style="color: var(--color-sub); text-align: center; font-size: 1.1em;">まだ登録のない入校証です<br>続けて生徒の登録をしますか？</p></div>
                        <div style="display: flex; gap: 20px;"><button type="button" class="felica-btn" id="btn-register">登録する</button><button type="button" class="felica-btn" id="btn-skip">登録しない</button></div>
                    </div>

                    {{-- 5. 生徒紐づけ --}}
                    <div class="screen screen-link">
                        <div><p style="color: var(--color-sub); text-align: center; font-size: 1.1em;">生徒紐づけ</p></div>
                        <div style="display: flex; gap: 20px;">
                            <form class="student-name" id="link-form">
                                <div>
                                    <label for="student-search">名前</label>
                                    <div class="search-container">
                                        <input type="text" id="student-search" placeholder="名前または学籍番号で検索" autocomplete="off">
                                        <ul id="student-results" role="listbox" aria-label="検索結果"></ul>
                                    </div>
                                </div>
                            </form>
                        </div>
                        <div><button type="button" class="text-btn" id="btn-cancel-link">登録をやめる</button></div>
                    </div>

                    {{-- 6. あいさつ --}}
                    <div class="screen screen-greeting">
                        <div></div>
                        <div style="background-color: var(--color-sub); color: var(--color-main); padding: 25px 40px; display: grid; gap: 10px; place-items: center;">
                            <p style="font-size: 1.3em;"><span id="greet-name"></span>さんこんにちは！</p>
                            <p>IDm：<span id="greet-idm"></span></p>
                        </div>
                    </div>
                </div>
            </div>

            {{-- ログ --}}
            <section class="log-panel" aria-label="ログ">
                <h2>ログ</h2>
                <ul class="log-list" id="log"></ul>
            </section>
        </div>
    </main>

@endsection
@push('scripts')
    @vite('resources/js/rcs300-felica.js')
    {{-- 上の行で読み込むライブラリは module として後から実行されるので、こちらも module にして順番を合わせる --}}
    <script type="module">
    (() => {
        'use strict';

        const REQUIRED_LIB_VERSION = 3;
        const POLL_INTERVAL_MS = 250;  // ポーリング間隔
        const REMOVE_THRESHOLD = 2;    // 連続でカード無しがこの回数続いたら「離れた」とみなす
        const MAX_ERRORS = 3;          // 連続エラーがこの回数に達したら停止
        const GREETING_MS = 5000;      // あいさつを表示しておく時間
        const SEARCH_DELAY_MS = 250;   // 入力が止まってから検索するまでの時間
        const SONY_VENDOR_ID = 0x054c;

        const $ = (id) => document.getElementById(id);
        const back = $('entry-pass');
        const urls = {
            touch: back.dataset.touchUrl,
            search: back.dataset.searchUrl,
            link: back.dataset.linkUrl,
        };
        const CSRF_TOKEN = document.querySelector('meta[name="csrf-token"]').content;
        const ui = {
            start: $('btn-start'),
            startNote: $('start-note'),
            progress: $('connect-progress'),
            readyNote: $('ready-note'),
            register: $('btn-register'),
            skip: $('btn-skip'),
            form: $('link-form'),
            search: $('student-search'),
            results: $('student-results'),
            cancelLink: $('btn-cancel-link'),
            greetName: $('greet-name'),
            greetIdm: $('greet-idm'),
            log: $('log'),
        };

        const sleep = (ms) => new Promise((resolve) => setTimeout(resolve, ms));
        const hex4 = (n) => n.toString(16).toUpperCase().padStart(4, '0');

        // 画面の切り替え（見た目は CSS が担当）
        const showScreen = (name) => { back.dataset.screen = name; };
        const currentScreen = () => back.dataset.screen;

        function addLog(text, type = 'info') {
            const li = document.createElement('li');
            if (type === 'error') li.className = 'error';
            const time = document.createElement('time');
            time.textContent = new Date().toLocaleTimeString();
            li.append(time, text); // 文字列は textContent として入るので HTML として解釈されない
            ui.log.prepend(li);
            while (ui.log.children.length > 100) ui.log.lastElementChild.remove();
        }

        // =====================================================================
        // サーバーとのやり取り（Fetch API）
        // =====================================================================
        async function postJson(url, body, signal) {
            const res = await fetch(url, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json', // バリデーションエラーを 422 JSON で受け取る
                    'X-CSRF-TOKEN': CSRF_TOKEN,
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify(body),
                signal,
            });
            const json = await res.json().catch(() => ({}));
            if (res.status === 419) throw new Error('セッションの有効期限が切れました。ページを再読み込みしてください。');
            if (!res.ok) throw new Error(json.message ?? `HTTP ${res.status}`);
            return json;
        }

        // =====================================================================
        // 照合（タッチされたとき）
        // =====================================================================
        let pendingIdm = null; // 生徒を登録しようとしている入校証の IDm
        let greetTimer = null;

        async function handleTouch(idm) {
            ui.readyNote.textContent = '';
            addLog(`タッチ: IDm ${idm}`);
            try {
                const res = await postJson(urls.touch, { idm });
                if (res.status === 'linked') {
                    greet(res.student, res.idm);
                } else if (res.status === 'unlinked') {
                    pendingIdm = res.idm;
                    addLog(res.message);
                    showScreen('ask');
                } else {
                    ui.readyNote.textContent = res.message;
                    addLog(`${res.message}（IDm ${res.idm}）`, 'error');
                    showScreen('ready');
                }
            } catch (e) {
                ui.readyNote.textContent = '照合できませんでした。もう一度タッチしてください。';
                addLog(`照合に失敗しました: ${e.message}`, 'error');
                showScreen('ready');
            }
        }

        function greet(student, idm) {
            ui.greetName.textContent = student.name;
            ui.greetIdm.textContent = idm;
            showScreen('greeting');
            addLog(`${student.name}（${student.student_number}）さんを確認しました。`);
            clearTimeout(greetTimer);
            greetTimer = setTimeout(() => {
                if (currentScreen() === 'greeting') showScreen('ready');
            }, GREETING_MS);
        }

        function finishRegistration(message) {
            if (message) addLog(message);
            pendingIdm = null;
            ui.search.value = '';
            ui.results.replaceChildren();
            showScreen('ready');
        }

        ui.register.addEventListener('click', () => {
            ui.search.value = '';
            ui.results.replaceChildren();
            showScreen('link');
            ui.search.focus();
        });
        ui.skip.addEventListener('click', () => finishRegistration(`生徒は登録しませんでした（IDm ${pendingIdm}）。`));
        ui.cancelLink.addEventListener('click', () => finishRegistration(`生徒の登録をやめました（IDm ${pendingIdm}）。`));

        // =====================================================================
        // 生徒の検索（Ajax）と登録
        // =====================================================================
        let searchTimer = null;
        let searchAbort = null;
        let linking = false;

        ui.form.addEventListener('submit', (e) => e.preventDefault()); // Enter でページが送信されないように
        ui.search.addEventListener('input', () => {
            clearTimeout(searchTimer);
            searchTimer = setTimeout(searchStudents, SEARCH_DELAY_MS);
        });

        async function searchStudents() {
            const q = ui.search.value.trim();
            searchAbort?.abort();
            if (q === '') {
                ui.results.replaceChildren();
                return;
            }
            searchAbort = new AbortController();
            try {
                const res = await postJson(urls.search, { q }, searchAbort.signal);
                renderResults(res.students);
            } catch (e) {
                if (e.name !== 'AbortError') addLog(`生徒の検索に失敗しました: ${e.message}`, 'error');
            }
        }

        function renderResults(students) {
            if (students.length === 0) {
                const li = document.createElement('li');
                li.className = 'empty';
                li.textContent = '該当する生徒がいません';
                ui.results.replaceChildren(li);
                return;
            }
            ui.results.replaceChildren(...students.map((s) => {
                const li = document.createElement('li');
                li.tabIndex = 0; // 押したときにフォーカスが検索欄の外へ出て、一覧が閉じてしまわないように
                li.setAttribute('role', 'option');
                li.dataset.studentId = s.id;
                const name = document.createElement('span');
                name.textContent = s.name;
                const number = document.createElement('span');
                number.textContent = s.student_number;
                li.append(name, number);
                return li;
            }));
        }

        ui.results.addEventListener('click', (e) => {
            const li = e.target.closest('li[data-student-id]');
            if (li) linkStudent(li);
        });
        ui.results.addEventListener('keydown', (e) => {
            const li = e.target.closest('li[data-student-id]');
            if (!li) return;
            if (e.key === 'Enter') linkStudent(li);
            if (e.key === 'ArrowDown') { e.preventDefault(); li.nextElementSibling?.focus(); }
            if (e.key === 'ArrowUp') { e.preventDefault(); (li.previousElementSibling ?? ui.search).focus(); }
        });
        ui.search.addEventListener('keydown', (e) => {
            if (e.key === 'ArrowDown') {
                e.preventDefault();
                ui.results.querySelector('li[data-student-id]')?.focus();
            }
        });

        async function linkStudent(li) {
            if (linking || !pendingIdm) return;
            linking = true;
            try {
                const idm = pendingIdm;
                const res = await postJson(urls.link, { idm, student_id: Number(li.dataset.studentId) });
                addLog(res.message);
                pendingIdm = null;
                ui.search.value = '';
                ui.results.replaceChildren();
                greet(res.student, res.idm);
            } catch (e) {
                addLog(`生徒の登録に失敗しました: ${e.message}`, 'error');
            } finally {
                linking = false;
            }
        }

        // =====================================================================
        // リーダーの接続とポーリング
        // =====================================================================
        let reader = null;
        let running = false;

        const isRcs300 = (d) => window.RCS300Reader.FILTERS.some(
            (f) => f.vendorId === d.vendorId && f.productId === d.productId);

        function explainError(e) {
            const claimFailed = e?.name === 'ReaderTimeoutError' && /インターフェイス/.test(e.step ?? '');
            if (claimFailed || (e?.name === 'NetworkError' && /claim/i.test(e.message ?? ''))) {
                return `RC-S300 の通信用インターフェイスを確保できませんでした（${e.message}）。`
                    + 'ソニーの「NFCポートソフトウェア」をインストールし、Chrome を再起動してからリーダーを挿し直してください。';
            }
            switch (e?.name) {
                case 'NotFoundError':
                    return '機器が選択されませんでした。';
                case 'SecurityError':
                    return `USB へのアクセスが拒否されました（${e.message}）。`;
                case 'NetworkError':
                    return `USB 通信に失敗しました（${e.message}）。別のタブやアプリがリーダーを使っていないか確認してください。`;
                case 'ReaderTimeoutError':
                    return `${e.message}。リーダーを挿し直し、ページを再読み込みしてから接続し直してください。`;
                default:
                    return e?.message ?? String(e);
            }
        }

        async function pickDevice() {
            // 一度許可した RC-S300 が挿さっていれば、選択画面を出さずにそれを使う
            const paired = (await navigator.usb.getDevices()).filter(isRcs300);
            if (paired.length === 1) return paired[0];
            ui.progress.textContent = 'ブラウザの選択画面で RC-S300 を選んで「接続」を押してください。';
            return navigator.usb.requestDevice({ filters: [{ vendorId: SONY_VENDOR_ID }] });
        }

        async function connect() {
            ui.start.disabled = true;
            ui.startNote.textContent = '';
            ui.progress.textContent = '';
            showScreen('connecting');
            try {
                const device = await pickDevice();
                if (!isRcs300(device)) {
                    throw new Error(`選択された機器は RC-S300 ではありません（PID 0x${hex4(device.productId)}）。`);
                }
                reader = new window.RCS300Reader(device);
                await reader.open({
                    onStep: (message) => {
                        ui.progress.textContent = message;
                        console.info('[RC-S300]', message);
                    },
                });
                addLog('リーダーに接続しました。');
                running = true;
                showScreen('ready');
                pollLoop().finally(teardown);
            } catch (e) {
                console.error(e);
                addLog(explainError(e), 'error');
                await teardown();
                if (e?.name !== 'NotFoundError') ui.startNote.textContent = '接続できませんでした。下のログを確認してください。';
            }
        }

        async function pollLoop() {
            let presentIdm = null;
            let missCount = 0;
            let errorCount = 0;

            while (running) {
                try {
                    const card = await reader.pollFelica();
                    errorCount = 0;

                    if (card) {
                        missCount = 0;
                        // 同じカードを置きっぱなしにしている間は、1回だけ処理する
                        if (card.idm !== presentIdm) {
                            presentIdm = card.idm;
                            if (['ask', 'link'].includes(currentScreen())) {
                                // 生徒の登録中は、別のカードがタッチされても画面を切り替えない
                                addLog(`登録中のため、タッチを無視しました（IDm ${card.idm}）。`);
                            } else {
                                await handleTouch(card.idm);
                            }
                        }
                    } else if (presentIdm !== null && ++missCount >= REMOVE_THRESHOLD) {
                        presentIdm = null;
                    }
                } catch (e) {
                    console.error(e);
                    if (reader?.broken || ++errorCount >= MAX_ERRORS) {
                        addLog(`読み取りを停止しました: ${explainError(e)}`, 'error');
                        ui.startNote.textContent = '読み取りが止まりました。もう一度「読み取り開始」を押してください。';
                        break;
                    }
                }
                await sleep(POLL_INTERVAL_MS);
            }
        }

        async function teardown() {
            running = false;
            const r = reader;
            reader = null;
            if (r) await r.close();
            pendingIdm = null;
            clearTimeout(greetTimer);
            ui.start.disabled = false;
            showScreen('start');
        }

        // =====================================================================
        // 初期化
        // =====================================================================
        function unavailable(message) {
            ui.startNote.textContent = message;
            ui.start.disabled = true;
            addLog(message, 'error');
        }

        if (!window.isSecureContext) {
            unavailable('WebUSB は HTTPS（または localhost）でのみ利用できます。');
            return;
        }
        if (!('usb' in navigator)) {
            unavailable('このブラウザは WebUSB に対応していません。Chrome か Edge を使用してください。');
            return;
        }
        if (!window.RCS300Reader) {
            unavailable('rcs300-felica.js を読み込めませんでした。npm run dev または npm run build を実行してください。');
            return;
        }
        if ((window.RCS300Reader.VERSION ?? 1) < REQUIRED_LIB_VERSION) {
            unavailable('rcs300-felica.js が古いビルドのままです。npm run build をやり直すか、npm run dev を起動してください。');
            return;
        }

        ui.start.addEventListener('click', connect); // requestDevice はユーザー操作から呼ぶ必要がある

        navigator.usb.addEventListener('disconnect', (event) => {
            if (reader && event.device === reader.device) {
                running = false;
                addLog('リーダーが取り外されました。', 'error');
                ui.startNote.textContent = 'リーダーが取り外されました。挿し直してから「読み取り開始」を押してください。';
            }
        });

        window.addEventListener('pagehide', () => { running = false; });
    })();
    </script>
@endpush