/* Digital LEAP offline layer.
 *
 * Holds three things for the student shell:
 *   1. drafts    — the logbook form is saved locally as it is typed
 *   2. outbox    — operations queued while offline, each with a UUID so a
 *                  retry can never be applied twice
 *   3. sync      — sequential send with exponential backoff, then a single
 *                  notification and a quiet snapshot check
 *
 * IndexedDB is used when available; localStorage carries the same shapes
 * when it is not. Nothing here runs on the server or leaves the device
 * except the sync POST to our own index.php.
 */
'use strict';

(function () {
    var DB_NAME = 'leap-offline';
    var DB_VERSION = 1;
    var DRAFT_STORE = 'drafts';
    var OUTBOX_STORE = 'outbox';
    var LS_PREFIX = 'leap.offline.';

    var dbPromise = null;
    var memory = { drafts: {}, outbox: {} };
    var hasIdb = typeof window.indexedDB !== 'undefined';

    function uuid() {
        if (window.crypto && typeof window.crypto.randomUUID === 'function') {
            return window.crypto.randomUUID();
        }
        var bytes = new Uint8Array(16);
        if (window.crypto && window.crypto.getRandomValues) {
            window.crypto.getRandomValues(bytes);
        } else {
            for (var i = 0; i < 16; i++) { bytes[i] = Math.floor(Math.random() * 256); }
        }
        bytes[6] = (bytes[6] & 0x0f) | 0x40;
        bytes[8] = (bytes[8] & 0x3f) | 0x80;
        var hex = Array.prototype.map.call(bytes, function (b) { return ('0' + b.toString(16)).slice(-2); }).join('');
        return hex.slice(0, 8) + '-' + hex.slice(8, 12) + '-' + hex.slice(12, 16) + '-' + hex.slice(16, 20) + '-' + hex.slice(20);
    }

    function openDb() {
        if (!hasIdb) { return Promise.reject(new Error('no indexeddb')); }
        if (dbPromise) { return dbPromise; }
        dbPromise = new Promise(function (resolve, reject) {
            var request = window.indexedDB.open(DB_NAME, DB_VERSION);
            request.onupgradeneeded = function () {
                var db = request.result;
                if (!db.objectStoreNames.contains(DRAFT_STORE)) { db.createObjectStore(DRAFT_STORE, { keyPath: 'key' }); }
                if (!db.objectStoreNames.contains(OUTBOX_STORE)) { db.createObjectStore(OUTBOX_STORE, { keyPath: 'op_id' }); }
            };
            request.onsuccess = function () { resolve(request.result); };
            request.onerror = function () { reject(request.error); };
        });
        return dbPromise;
    }

    function idbPut(store, value) {
        return openDb().then(function (db) {
            return new Promise(function (resolve, reject) {
                var tx = db.transaction(store, 'readwrite');
                tx.objectStore(store).put(value);
                tx.oncomplete = function () { resolve(value); };
                tx.onerror = function () { reject(tx.error); };
            });
        });
    }

    function idbGet(store, key) {
        return openDb().then(function (db) {
            return new Promise(function (resolve, reject) {
                var tx = db.transaction(store, 'readonly');
                var req = tx.objectStore(store).get(key);
                req.onsuccess = function () { resolve(req.result || null); };
                req.onerror = function () { reject(req.error); };
            });
        });
    }

    function idbAll(store) {
        return openDb().then(function (db) {
            return new Promise(function (resolve, reject) {
                var tx = db.transaction(store, 'readonly');
                var req = tx.objectStore(store).getAll();
                req.onsuccess = function () { resolve(req.result || []); };
                req.onerror = function () { reject(req.error); };
            });
        });
    }

    function idbDelete(store, key) {
        return openDb().then(function (db) {
            return new Promise(function (resolve, reject) {
                var tx = db.transaction(store, 'readwrite');
                tx.objectStore(store).delete(key);
                tx.oncomplete = function () { resolve(); };
                tx.onerror = function () { reject(tx.error); };
            });
        });
    }

    function lsGet(key) {
        try { return JSON.parse(window.localStorage.getItem(LS_PREFIX + key) || 'null'); } catch (error) { return null; }
    }
    function lsSet(key, value) {
        try { window.localStorage.setItem(LS_PREFIX + key, JSON.stringify(value)); return true; } catch (error) { return false; }
    }
    function lsDelete(key) {
        try { window.localStorage.removeItem(LS_PREFIX + key); } catch (error) { /* ignore */ }
    }

    function storePut(store, value) {
        var mirror = store === DRAFT_STORE ? memory.drafts : null;
        if (mirror) {
            mirror[value.key] = value;
            lsSet('draft.' + value.key, value);
        }
        return idbPut(store, value).catch(function () { return value; });
    }

    function getDraft(key) {
        return idbGet(DRAFT_STORE, key).catch(function () { return lsGet('draft.' + key); })
            .then(function (row) { return row || lsGet('draft.' + key); });
    }

    function deleteDraft(key) {
        delete memory.drafts[key];
        lsDelete('draft.' + key);
        return idbDelete(DRAFT_STORE, key).catch(function () { /* ignore */ });
    }

    function lsOutboxRows() {
        var rows = [];
        try {
            for (var i = 0; i < window.localStorage.length; i++) {
                var name = window.localStorage.key(i);
                if (name && name.indexOf(LS_PREFIX + 'outbox.') === 0) {
                    var row = JSON.parse(window.localStorage.getItem(name) || 'null');
                    if (row && row.op_id) { rows.push(row); }
                }
            }
        } catch (error) { /* private mode */ }
        return rows;
    }

    function outboxAll() {
        return idbAll(OUTBOX_STORE).catch(function () {
            return lsOutboxRows();
        }).then(function (rows) {
            var seen = {};
            rows.forEach(function (row) { seen[row.op_id] = true; });
            // IndexedDB may be unavailable on this device; merge anything only
            // localStorage knows about so nothing queued is ever lost.
            lsOutboxRows().forEach(function (row) { if (!seen[row.op_id]) { rows.push(row); } });
            rows.sort(function (a, b) { return (a.created_at || 0) - (b.created_at || 0); });
            memory.outbox = {};
            rows.forEach(function (row) { memory.outbox[row.op_id] = row; });
            return rows;
        });
    }

    function enqueue(op) {
        memory.outbox[op.op_id] = op;
        lsSet('outbox.' + op.op_id, op);
        return idbPut(OUTBOX_STORE, op).catch(function () { return op; }).then(function () {
            paintStrip();
            document.dispatchEvent(new CustomEvent('leap-sync'));
            return op;
        });
    }

    function updateOp(op) {
        memory.outbox[op.op_id] = op;
        lsSet('outbox.' + op.op_id, op);
        return idbPut(OUTBOX_STORE, op).catch(function () { return op; }).then(function () { paintStrip(); });
    }

    function removeOp(opId) {
        delete memory.outbox[opId];
        lsDelete('outbox.' + opId);
        return idbDelete(OUTBOX_STORE, opId).catch(function () { /* ignore */ });
    }

    function pendingCount() {
        var n = 0;
        Object.keys(memory.outbox).forEach(function (k) {
            var state = memory.outbox[k].state;
            if (state === 'waiting' || state === 'sending' || state === 'failed') { n++; }
        });
        return n;
    }

    function csrf() {
        var meta = document.querySelector('meta[name="csrf-token"]');
        return meta ? meta.getAttribute('content') || '' : '';
    }

    var flushInFlight = false;
    var retryTimer = null;

    function sendOp(op) {
        var body = new FormData();
        body.append('_csrf', csrf());
        body.append('op_id', op.op_id);
        body.append('payload', JSON.stringify({ kind: op.kind, submit: !!op.submit, fields: op.fields }));
        return fetch(window.location.pathname + '?r=api/sync', {
            method: 'POST',
            body: body,
            credentials: 'same-origin',
            headers: { 'X-Requested-With': 'leap-offline' },
        }).then(function (response) {
            return response.json().catch(function () { return { ok: false, error: 'Unreadable response (' + response.status + ').' }; })
                .then(function (json) { return { status: response.status, json: json }; });
        });
    }

    function flush() {
        if (flushInFlight) { return Promise.resolve(); }
        flushInFlight = true;
        var applied = 0;

        function next() {
            return outboxAll().then(function (rows) {
                var op = null;
                for (var i = 0; i < rows.length; i++) {
                    if (rows[i].state === 'waiting') { op = rows[i]; break; }
                }
                if (!op) { return finish(); }
                if (!navigator.onLine) { return finish(); }

                op.state = 'sending';
                op.attempts = (op.attempts || 0) + 1;
                return updateOp(op).then(function () {
                    return sendOp(op).then(function (result) {
                        if (result.status === 200 && result.json.ok) {
                            applied++;
                            return removeOp(op.op_id).then(next);
                        }
                        if (result.status === 422) {
                            op.state = 'failed';
                            op.error = (result.json.errors && result.json.errors.length)
                                ? result.json.errors.join(' ')
                                : (result.json.error || 'The server rejected this entry.');
                            return updateOp(op).then(finish);
                        }
                        if (result.status === 419) {
                            op.state = 'failed';
                            op.error = 'Your session expired. Sign in again, then press Retry.';
                            return updateOp(op).then(finish);
                        }
                        if (result.status >= 400 && result.status < 500) {
                            op.state = 'failed';
                            op.error = result.json.error || ('The server refused this entry (' + result.status + ').');
                            return updateOp(op).then(finish);
                        }
                        return retryLater(op).then(finish);
                    }, function () {
                        return retryLater(op).then(finish);
                    });
                });
            });
        }

        function retryLater(op) {
            var delay = Math.min(60000, Math.pow(2, Math.min(op.attempts || 1, 6)) * 1000);
            op.state = 'waiting';
            op.next_attempt_at = Date.now() + delay;
            return updateOp(op).then(function () {
                if (retryTimer) { window.clearTimeout(retryTimer); }
                retryTimer = window.setTimeout(function () { flush(); }, delay);
            });
        }

        function finish() {
            flushInFlight = false;
            if (applied > 0) {
                notify(applied === 1
                    ? 'One change saved while offline has been sent.'
                    : applied + ' changes saved while offline have been sent.');
                document.dispatchEvent(new CustomEvent('leap-sync'));
                refreshSnapshot();
            }
            return Promise.resolve(applied);
        }

        return next().catch(function () { flushInFlight = false; });
    }

    function notify(message) {
        var stack = document.querySelector('[data-toasts]');
        if (!stack) { return; }
        var node = document.createElement('div');
        node.className = 'toast';
        node.textContent = message;
        stack.appendChild(node);
        window.setTimeout(function () { if (node.parentNode) { node.parentNode.removeChild(node); } }, 5200);
    }

    /* --- strip ---------------------------------------------------------- */

    function labelFor(op) {
        if (op.label) { return op.label; }
        var week = op.fields && op.fields.week_no ? 'Week ' + op.fields.week_no : 'Logbook';
        return week;
    }

    function paintStrip() {
        var strip = document.querySelector('[data-sync-strip]');
        if (!strip) { return; }
        var rows = Object.keys(memory.outbox).map(function (k) { return memory.outbox[k]; });
        rows.sort(function (a, b) { return (a.created_at || 0) - (b.created_at || 0); });
        if (!rows.length) {
            strip.hidden = true;
            strip.innerHTML = '';
            return;
        }
        var failed = rows.some(function (op) { return op.state === 'failed'; });
        strip.hidden = false;
        strip.setAttribute('data-tone', failed ? 'warn' : 'info');

        var head = document.createElement('span');
        var waiting = rows.length;
        head.textContent = failed
            ? waiting + (waiting === 1 ? ' change needs attention' : ' changes need attention')
            : waiting + (waiting === 1 ? ' change waiting to send' : ' changes waiting to send');

        var list = document.createElement('span');
        list.className = 'sync-items';
        rows.slice(0, 4).forEach(function (op) {
            var item = document.createElement('span');
            item.className = 'sync-item';
            var name = document.createElement('span');
            name.textContent = labelFor(op);
            var state = document.createElement('em');
            state.textContent = op.state === 'failed' ? 'Failed' : (op.state === 'sending' ? 'Sending…' : 'Waiting');
            item.appendChild(name);
            item.appendChild(state);
            if (op.state === 'failed' && op.error) { item.title = op.error; }
            list.appendChild(item);
        });
        if (rows.length > 4) {
            var more = document.createElement('span');
            more.className = 'sync-item';
            more.textContent = '+' + (rows.length - 4) + ' more';
            list.appendChild(more);
        }

        strip.innerHTML = '';
        strip.appendChild(head);
        strip.appendChild(list);
        if (failed) {
            var retry = document.createElement('button');
            retry.type = 'button';
            retry.textContent = 'Retry';
            retry.addEventListener('click', function () {
                Object.keys(memory.outbox).forEach(function (key) {
                    if (memory.outbox[key].state === 'failed') {
                        memory.outbox[key].state = 'waiting';
                        memory.outbox[key].attempts = 0;
                        updateOp(memory.outbox[key]);
                    }
                });
                flush();
            });
            strip.appendChild(retry);
        }
    }

    /* --- drafts --------------------------------------------------------- */

    var DRAFT_FIELDS = ['week_no', 'entry_id', 'activities', 'technical_skills', 'challenges', 'meeting_notes', 'reflection'];

    function formValues(form) {
        var fields = {};
        DRAFT_FIELDS.forEach(function (name) {
            var el = form.querySelector('[name="' + name + '"]');
            if (el) { fields[name] = el.value; }
        });
        fields.day_date = [];
        fields.day_tasks = [];
        fields.day_hours = [];
        form.querySelectorAll('[name="day_date[]"]').forEach(function (el) { fields.day_date.push(el.value); });
        form.querySelectorAll('[name="day_tasks[]"]').forEach(function (el) { fields.day_tasks.push(el.value); });
        form.querySelectorAll('[name="day_hours[]"]').forEach(function (el) { fields.day_hours.push(el.value); });
        return fields;
    }

    function draftKey(form) {
        var entry = form.querySelector('[name="entry_id"]');
        if (entry && entry.value) { return 'logbook.' + entry.value; }
        var week = form.querySelector('[name="week_no"]');
        return 'logbook.new.' + (week ? week.value : '0');
    }

    function sameValues(a, b) {
        try { return JSON.stringify(a) === JSON.stringify(b); } catch (error) { return false; }
    }

    function hasContent(fields) {
        return (fields.activities || '').trim() !== ''
            || (fields.day_tasks || []).some(function (t) { return (t || '').trim() !== ''; });
    }

    function initLogbookDraft() {
        // The action attribute is URL-encoded (student%2Flogbook%2Fsave), so it
        // cannot be matched with a slash selector; the view marks the form.
        var form = document.querySelector('form[data-draft-form]');
        if (!form) { return; }

        var key = draftKey(form);
        var dirty = false;
        var timer = null;

        function save() {
            var fields = formValues(form);
            if (!hasContent(fields)) { return; }
            dirty = true;
            storePut(DRAFT_STORE, { key: key, fields: fields, updated_at: Date.now() });
        }

        function schedule() {
            if (timer) { window.clearTimeout(timer); }
            timer = window.setTimeout(save, 600);
        }

        form.addEventListener('input', schedule);
        form.addEventListener('change', schedule);

        getDraft(key).then(function (row) {
            if (!row || !row.fields) { return; }
            var current = formValues(form);
            if (!hasContent(row.fields) || sameValues(row.fields, current)) {
                if (sameValues(row.fields, current)) { deleteDraft(key); }
                return;
            }
            showRestoreBar(row);
        });

        function showRestoreBar(row) {
            if (document.querySelector('[data-draft-bar]')) { return; }
            var bar = document.createElement('div');
            bar.className = 'draft-bar';
            bar.setAttribute('data-draft-bar', '');
            bar.setAttribute('role', 'status');

            var text = document.createElement('span');
            var when = new Date(row.updated_at || Date.now());
            text.innerHTML = '';
            var strong = document.createElement('strong');
            strong.textContent = 'Unsaved draft found';
            var detail = document.createElement('span');
            detail.textContent = ' Saved on this device ' + when.toLocaleString('en-GB', { dateStyle: 'medium', timeStyle: 'short' }) + '. Restore it, or keep what the page shows.';
            text.appendChild(strong);
            text.appendChild(detail);

            var restore = document.createElement('button');
            restore.type = 'button';
            restore.className = 'btn btn-sm';
            restore.textContent = 'Restore draft';
            restore.addEventListener('click', function () {
                applyValues(form, row.fields);
                dirty = true;
                bar.remove();
                notify('Draft restored.');
            });

            var discard = document.createElement('button');
            discard.type = 'button';
            discard.className = 'btn btn-sm btn-quiet';
            discard.textContent = 'Discard';
            discard.addEventListener('click', function () {
                deleteDraft(key);
                bar.remove();
            });

            bar.appendChild(text);
            bar.appendChild(restore);
            bar.appendChild(discard);
            form.parentNode.insertBefore(bar, form);
        }

        form.addEventListener('submit', function (event) {
            save();
            // Queue when the device is offline, or when the connection is up but
            // this app's server is not: a POST to a dead server would lose the
            // page and everything typed into it.
            if (navigator.onLine && window.LeapServerReachable !== false) { return; }

            event.preventDefault();
            if (form.getAttribute('data-queued') === '1') {
                notify('This entry is already saved on this device and will send when you are online.');
                return;
            }
            form.setAttribute('data-queued', '1');
            var submitter = event.submitter;
            var submitting = !!(submitter && submitter.name === 'submit');
            var fields = formValues(form);
            var op = {
                op_id: uuid(),
                kind: 'logbook.entry',
                submit: submitting,
                label: 'Week ' + (fields.week_no || '') + ' logbook',
                fields: fields,
                state: 'waiting',
                attempts: 0,
                created_at: Date.now(),
            };
            enqueue(op).then(function () {
                notify('Saved on this device. It will send as soon as you are back online.');
                paintStrip();
            });
        });

        window.addEventListener('beforeunload', function () {
            if (dirty) { save(); }
        });
    }

    function applyValues(form, fields) {
        DRAFT_FIELDS.forEach(function (name) {
            var el = form.querySelector('[name="' + name + '"]');
            if (el && typeof fields[name] === 'string') { el.value = fields[name]; }
        });
        ['day_date', 'day_tasks', 'day_hours'].forEach(function (group) {
            form.querySelectorAll('[name="' + group + '[]"]').forEach(function (el, index) {
                var value = (fields[group] || [])[index];
                if (typeof value === 'string') { el.value = value; }
            });
        });
        form.querySelectorAll('input[type="date"]').forEach(function (el) {
            el.dispatchEvent(new Event('change', { bubbles: true }));
        });
    }

    /* --- reconnect snapshot --------------------------------------------- */

    function hashText(text) {
        var hash = 5381;
        for (var i = 0; i < text.length; i++) { hash = ((hash << 5) + hash + text.charCodeAt(i)) >>> 0; }
        return hash;
    }

    var snapshotBusy = false;

    function refreshSnapshot() {
        if (snapshotBusy || !navigator.onLine) { return Promise.resolve(false); }
        var main = document.querySelector('#main');
        if (!main) { return Promise.resolve(false); }
        if (document.querySelector('[data-draft-bar]')) { return Promise.resolve(false); }
        snapshotBusy = true;
        // The unique query string keeps the service worker's exact-URL cache
        // from answering this with the stale copy, which would defeat the check.
        var probe = new URL(window.location.href);
        probe.searchParams.set('_snap', String(Date.now()));
        return fetch(probe.toString(), { credentials: 'same-origin', cache: 'no-store' })
            .then(function (response) { return response.ok ? response.text() : ''; })
            .then(function (html) {
                snapshotBusy = false;
                if (!html) { return false; }
                var doc = new DOMParser().parseFromString(html, 'text/html');
                var next = doc.querySelector('#main');
                if (!next) { return false; }
                if (hashText(next.innerHTML.trim()) === hashText(main.innerHTML.trim())) { return false; }
                notifyWithAction('Reconnected. This page has newer data.', 'Reload', function () { window.location.reload(); });
                return true;
            })
            .catch(function () { snapshotBusy = false; return false; });
    }

    function notifyWithAction(message, actionLabel, action) {
        var stack = document.querySelector('[data-toasts]');
        if (!stack) { return; }
        var node = document.createElement('div');
        node.className = 'toast';
        var text = document.createElement('span');
        text.textContent = message;
        var button = document.createElement('button');
        button.type = 'button';
        button.className = 'toast-action';
        button.textContent = actionLabel;
        button.addEventListener('click', function () {
            if (node.parentNode) { node.parentNode.removeChild(node); }
            action();
        });
        node.appendChild(text);
        node.appendChild(button);
        stack.appendChild(node);
        window.setTimeout(function () { if (node.parentNode) { node.parentNode.removeChild(node); } }, 9000);
    }

    /* --- boot ------------------------------------------------------------ */

    function boot() {
        return outboxAll().catch(function () { return []; }).then(function () {
            paintStrip();
            document.addEventListener('leap-sync', function () {
                if (window.LeapPaintNet) { window.LeapPaintNet(); }
            });
            window.addEventListener('online', function () {
                flush();
            });
            if (navigator.onLine && pendingCount() > 0) { flush(); }
        });
    }

    window.LeapOffline = {
        ready: boot(),
        enqueue: enqueue,
        flush: flush,
        pendingCount: pendingCount,
        paintStrip: paintStrip,
        refreshSnapshot: refreshSnapshot,
        getDraft: getDraft,
        deleteDraft: deleteDraft,
        uuid: uuid,
        initLogbookDraft: initLogbookDraft,
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () {
            initLogbookDraft();
            if (window.LeapPaintNet) { window.LeapPaintNet(); }
        });
    } else {
        initLogbookDraft();
    }
})();
