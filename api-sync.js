(function() {
    'use strict';
    var API = '/api/index.php';
    var BOOT_TIMEOUT_MS = 8000;
    var OUTBOX_KEY = 'zohra_outbox';
    var MAX_OUTBOX = 200;

    var _mem = {};
    var _ready = false;
    var _user = null;
    var _pending = 0;
    var _queue = [];
    var _flushTimer = null;

    var origGetItem = Storage.prototype.getItem;
    var origSetItem = Storage.prototype.setItem;
    var origRemoveItem = Storage.prototype.removeItem;

    function realGet(k) {
        try { return origGetItem.call(localStorage, k); } catch (e) { return null; }
    }
    function realSet(k, v) {
        try { origSetItem.call(localStorage, k, v); } catch (e) {}
    }
    function realDel(k) {
        try { origRemoveItem.call(localStorage, k); } catch (e) {}
    }

    // ---------- outbox (failed writes retried later, stored in REAL localStorage) ----------
    function readOutbox() {
        try {
            var raw = realGet(OUTBOX_KEY);
            var arr = raw ? JSON.parse(raw) : [];
            return Array.isArray(arr) ? arr : [];
        } catch (e) { return []; }
    }
    function writeOutbox(arr) {
        try {
            if (arr.length > MAX_OUTBOX) arr = arr.slice(arr.length - MAX_OUTBOX);
            realSet(OUTBOX_KEY, JSON.stringify(arr));
        } catch (e) {}
    }
    function enqueueOutbox(entry) {
        var box = readOutbox();
        // coalesce: keep only latest write per key
        var found = false;
        for (var i = 0; i < box.length; i++) {
            if (box[i].key === entry.key) { box[i] = entry; found = true; break; }
        }
        if (!found) box.push(entry);
        writeOutbox(box);
    }
    function dequeueOutbox(key) {
        var box = readOutbox();
        var next = [];
        for (var i = 0; i < box.length; i++) {
            if (box[i].key !== key) next.push(box[i]);
        }
        writeOutbox(next);
    }
    // Never let a server refresh wipe a local edit that hasn't reached MySQL yet
    function isPending(key) {
        var box = readOutbox();
        for (var i = 0; i < box.length; i++) {
            if (box[i].key === key) return true;
        }
        return false;
    }
    function storeServerValue(key, value) {
        if (isPending(key)) return false;
        _mem[key] = value;
        return true;
    }
    function storeServerValuePersist(key, value) {
        if (isPending(key)) return false;
        _mem[key] = value;
        realSet(key, value);
        return true;
    }

    // ---------- xhr with response handling ----------
    function xhrGet(action, params, cb) {
        _pending++;
        var url = API + '?action=' + encodeURIComponent(action);
        if (params) {
            for (var k in params) {
                if (params.hasOwnProperty(k) && params[k] !== undefined && params[k] !== null) {
                    url += '&' + encodeURIComponent(k) + '=' + encodeURIComponent(params[k]);
                }
            }
        }
        var xhr = new XMLHttpRequest();
        var done = false;
        function finish(resp, status) {
            if (done) return;
            done = true;
            try { clearTimeout(timer); } catch (e) {}
            _pending--;
            cb(resp, status || 0);
            checkReady();
        }
        var timer = setTimeout(function() {
            try { xhr.abort(); } catch (e) {}
            finish(null, 0);
        }, 12000);
        try {
            xhr.open('GET', url, true);
            xhr.withCredentials = true;
            xhr.setRequestHeader('Content-Type', 'application/json');
            xhr.onreadystatechange = function() {
                if (xhr.readyState === 4) {
                    if (xhr.status === 200) {
                        try { finish(JSON.parse(xhr.responseText), 200); }
                        catch (e) { finish(null, 200); }
                    } else {
                        finish(null, xhr.status || 0);
                    }
                }
            };
            xhr.onerror = function() { finish(null, 0); };
            xhr.send();
        } catch (e) { finish(null, 0); }
    }

    function xhrPost(action, data, cb) {
        var url = API + '?action=' + encodeURIComponent(action);
        var xhr = new XMLHttpRequest();
        var done = false;
        function finish(err, resp) {
            if (done) return;
            done = true;
            try { clearTimeout(timer); } catch (e) {}
            if (cb) cb(err, resp);
            else if (err) console.error('[api-sync] POST failed:', action, err);
        }
        var timer = setTimeout(function() {
            try { xhr.abort(); } catch (e) {}
            finish('timeout');
        }, 12000);
        try {
            xhr.open('POST', url, true);
            xhr.withCredentials = true;
            xhr.setRequestHeader('Content-Type', 'application/json');
            xhr.onreadystatechange = function() {
                if (xhr.readyState === 4) {
                    if (xhr.status === 200) {
                        var parsed = null;
                        try { parsed = JSON.parse(xhr.responseText); } catch (e) { parsed = null; }
                        if (parsed && parsed.success) finish(null, parsed);
                        else finish((parsed && parsed.message) || ('http-' + xhr.status));
                    } else {
                        finish('http-' + xhr.status);
                    }
                }
            };
            xhr.onerror = function() { finish('network'); };
            xhr.send(JSON.stringify(data));
        } catch (e) { finish('exception'); }
    }

    function xhrDelete(action, data, cb) {
        var url = API + '?action=' + encodeURIComponent(action);
        var xhr = new XMLHttpRequest();
        var done = false;
        function finish(err, resp) {
            if (done) return;
            done = true;
            try { clearTimeout(timer); } catch (e) {}
            if (cb) cb(err, resp);
            else if (err) console.error('[api-sync] DELETE failed:', action, err);
        }
        var timer = setTimeout(function() {
            try { xhr.abort(); } catch (e) {}
            finish('timeout');
        }, 12000);
        try {
            xhr.open('DELETE', url, true);
            xhr.withCredentials = true;
            xhr.setRequestHeader('Content-Type', 'application/json');
            xhr.onreadystatechange = function() {
                if (xhr.readyState === 4) {
                    if (xhr.status === 200) {
                        var parsed = null;
                        try { parsed = JSON.parse(xhr.responseText); } catch (e) { parsed = null; }
                        if (parsed && parsed.success) finish(null, parsed);
                        else finish((parsed && parsed.message) || ('http-' + xhr.status));
                    } else {
                        finish('http-' + xhr.status);
                    }
                }
            };
            xhr.onerror = function() { finish('network'); };
            xhr.send(JSON.stringify(data || {}));
        } catch (e) { finish('exception'); }
    }

    function checkReady() {
        if (_pending === 0 && !_ready) {
            _ready = true;
            for (var i = 0; i < _queue.length; i++) {
                try { _queue[i](); } catch (e) { console.error('[api-sync] ready cb error', e); }
            }
            _queue = [];
            scheduleFlush(2000);
        }
    }

    // boot timeout: never leave dashboard hanging if API is slow/down.
    // NOTE: does not touch _pending (late responses still decrement it).
    setTimeout(function() {
        if (!_ready) {
            console.warn('[api-sync] boot timeout, proceeding with local data');
            _ready = true;
            for (var i = 0; i < _queue.length; i++) {
                try { _queue[i](); } catch (e) {}
            }
            _queue = [];
            scheduleFlush(2000);
        }
    }, BOOT_TIMEOUT_MS);

    function waitForReady(cb) {
        if (_ready) { try { cb(); } catch (e) {} return; }
        _queue.push(cb);
    }

    function phoneFromPrefixedKey(key, prefix) {
        if (key.indexOf(prefix) !== 0) return '';
        return key.slice(prefix.length);
    }

    function buildPayload(key, value) {
        var data;
        try { data = JSON.parse(value); } catch (e) { return null; }

        var writeMap = {
            'zohra_appointments': 'appointments',
            'zohra_mealplans': 'mealplans',
            'zohra_explans': 'plans',
            'zohra_msgs': 'messages',
            'zohra_users': 'patients'
        };

        if (key.indexOf('zohra_progress_') === 0) {
            var pPhone = phoneFromPrefixedKey(key, 'zohra_progress_');
            if (!pPhone) return null;
            return { action: 'progress', body: { phone: pPhone, data: data } };
        }
        if (key.indexOf('zohra_tests_') === 0) {
            var tPhone = phoneFromPrefixedKey(key, 'zohra_tests_');
            if (!tPhone) return null;
            return { action: 'tests', body: { phone: tPhone, data: data } };
        }
        if (key.indexOf('zohra_water_') === 0) {
            var parts = key.split('_');
            if (parts.length < 4) return null;
            var wPhone = parts[2];
            var wDate = parts.slice(3).join('_');
            if (!wPhone || !wDate) return null;
            if (!data || typeof data.count === 'undefined') return null;
            return { action: 'water', body: { phone: wPhone, date: wDate, count: data.count, glasses: data.glasses || [] } };
        }
        if (key.indexOf('zohra_seen_') === 0 ||
            key.indexOf('zohra_doctor_seen_') === 0 ||
            key.indexOf('zohra_cleared_') === 0) {
            var owner = (_user && _user.phone) || '';
            if (!owner) return null;
            var entries = {};
            entries[key] = value;
            return { action: 'notifications', body: { phone: owner, entries: entries } };
        }
        if (writeMap[key]) {
            if (!Array.isArray(data)) return null;
            return { action: writeMap[key], body: { data: data } };
        }
        return null;
    }

    function postPayload(payload, key, value) {
        xhrPost(payload.action, payload.body, function(err) {
            if (err) {
                enqueueOutbox({ key: key, value: value, ts: Date.now(), attempts: 1 });
                scheduleFlush(5000);
            } else {
                dequeueOutbox(key);
            }
        });
    }

    function syncToApi(key, value) {
        if (!key || key.indexOf('zohra_') !== 0) return;
        if (key === 'zohra_user' || key === OUTBOX_KEY || key === 'zohra_unread_doctor') return;
        if (!_ready || !_user) {
            enqueueOutbox({ key: key, value: value, ts: Date.now(), attempts: 0 });
            return;
        }
        var payload;
        try { payload = buildPayload(key, value); } catch (e) { payload = null; }
        if (!payload) return;
        postPayload(payload, key, value);
    }

    function syncRemoveToApi(key) {
        if (!key || key.indexOf('zohra_') !== 0) return;
        if (!_ready || !_user) return;
        if (key.indexOf('zohra_water_') === 0) {
            var parts = key.split('_');
            if (parts.length < 4) return;
            var wPhone = parts[2];
            var wDate = parts.slice(3).join('_');
            // water DELETE via POST fallback (some hosts strip DELETE bodies)
            xhrPost('water/delete', { phone: wPhone, date: wDate }, function(err) {
                if (err) console.error('[api-sync] water delete failed', err);
            });
        }
    }

    function scheduleFlush(ms) {
        if (_flushTimer) return;
        _flushTimer = setTimeout(function() {
            _flushTimer = null;
            flushOutbox();
        }, ms || 5000);
    }

    function flushOutbox() {
        if (!_ready || !_user) return;
        var box = readOutbox();
        if (!box.length) return;
        var entry = box[0];
        var payload;
        try { payload = buildPayload(entry.key, entry.value); } catch (e) { payload = null; }
        if (!payload) { dequeueOutbox(entry.key); scheduleFlush(1000); return; }
        postPayloadWithDone(payload, entry);
    }

    function postPayloadWithDone(payload, entry) {
        xhrPost(payload.action, payload.body, function(err) {
            if (err) {
                entry.attempts = (entry.attempts || 0) + 1;
                var box = readOutbox();
                for (var i = 0; i < box.length; i++) {
                    if (box[i].key === entry.key) { box[i] = entry; break; }
                }
                writeOutbox(box);
                scheduleFlush(Math.min(60000, 5000 * entry.attempts));
            } else {
                dequeueOutbox(entry.key);
                if (readOutbox().length) scheduleFlush(1000);
            }
        });
    }

    // ---------- doctor sync: group per-patient rows coming from bulk GETs ----------
    function phoneOfRow(r) {
        if (!r) return '';
        return r.patient_phone || r.patientPhone || r.phone || '';
    }
    // Last doctor bulk-sync stats (inspect via window.__apiSyncDoctorSync).
    // If progressKeys/testKeys stay 0 while the user has data, the bulk GET
    // is failing — check login session, API reachability, and OPcache.
    var _docSync = { at: 0, progressRows: 0, progressKeys: 0, testRows: 0, testKeys: 0, waterKeys: 0 };
    function storeGroupedRows(rows, prefix, storeFn) {
        if (!Array.isArray(rows)) return 0;
        var groups = {};
        for (var i = 0; i < rows.length; i++) {
            var ph = phoneOfRow(rows[i]);
            if (!ph) continue;
            if (!groups[ph]) groups[ph] = [];
            groups[ph].push(rows[i]);
        }
        var n = 0, skipped = 0;
        for (var p in groups) {
            if (groups.hasOwnProperty(p)) {
                try { if (storeFn(prefix + p, JSON.stringify(groups[p]))) n++; else skipped++; } catch (e) {}
            }
        }
        // skipped > 0 means the outbox holds an unsynced local edit for that
        // patient, so the server value was kept aside to avoid wiping it.
        _docSync.skippedPending = skipped;
        _docSync.at = Date.now();
        if (prefix.indexOf('progress') >= 0) { _docSync.progressRows = rows.length; _docSync.progressKeys = n; }
        else { _docSync.testRows = rows.length; _docSync.testKeys = n; }
        return n;
    }
    function storeBulkWater(data, storeFn) {
        if (!data) return 0;
        var n = 0;
        // new bulk shape: { phone: { date: {count, glasses} } }
        for (var phone in data) {
            if (!data.hasOwnProperty(phone)) continue;
            var byDate = data[phone];
            if (!byDate || typeof byDate !== 'object') continue;
            // distinguish per-phone shape { date: {...} } from nested bulk:
            // bulk value is an object whose values look like {count,...}
            for (var dateKey in byDate) {
                if (byDate.hasOwnProperty(dateKey)) {
                    try { storeFn('zohra_water_' + phone + '_' + dateKey, JSON.stringify(byDate[dateKey])); n++; } catch (e) {}
                }
            }
        }
        _docSync.at = Date.now();
        _docSync.waterKeys = n;
        return n;
    }
    function getKnownPatientPhones() {
        var phones = [];
        try {
            var raw = _mem['zohra_users'] || realGet('zohra_users') || '[]';
            var list = JSON.parse(raw);
            for (var i = 0; i < list.length; i++) {
                if (list[i] && list[i].role === 'user' && list[i].phone) phones.push(list[i].phone);
            }
        } catch (e) {}
        return phones;
    }
    // Fallback when bulk water endpoint is unavailable: one GET per patient.
    // Fire-and-forget so boot/refresh never waits on N requests.
    function loadDoctorWatersPerPatient(storeFn) {
        var phones = getKnownPatientPhones();
        // retry shortly if patients haven't arrived yet
        if (!phones.length) {
            setTimeout(function() {
                var retry = getKnownPatientPhones();
                for (var j = 0; j < retry.length; j++) {
                    (function(ph) {
                        xhrGet('water', { phone: ph }, function(resp) {
                            if (resp && resp.success && resp.data) {
                                for (var dateKey in resp.data) {
                                    if (resp.data.hasOwnProperty(dateKey)) {
                                        try { storeFn('zohra_water_' + ph + '_' + dateKey, JSON.stringify(resp.data[dateKey])); } catch (e) {}
                                    }
                                }
                            }
                        });
                    })(retry[j]);
                }
            }, 3000);
            return;
        }
        for (var i = 0; i < phones.length; i++) {
            (function(ph) {
                xhrGet('water', { phone: ph }, function(resp) {
                    if (resp && resp.success && resp.data) {
                        for (var dateKey in resp.data) {
                            if (resp.data.hasOwnProperty(dateKey)) {
                                try { storeFn('zohra_water_' + ph + '_' + dateKey, JSON.stringify(resp.data[dateKey])); } catch (e) {}
                            }
                        }
                    }
                });
            })(phones[i]);
        }
    }

    function loadFromApi() {
        xhrGet('auth/me', null, function(meResp, httpStatus) {
            var role = null;
            var phone = null;

            if (meResp && meResp.success && meResp.user) {
                _user = meResp.user;
                _mem['zohra_user'] = JSON.stringify(_user);
                role = _user.role;
                phone = _user.phone;
            } else if ((meResp && meResp.loggedOut) || httpStatus === 401) {
                // Definitive "no session": explicit flag from auth/me (or a
                // legacy 401). NOT a network blip: those report status 0
                // with a null body. Drop the stale local user and bounce to
                // login instead of running desynced with a parked outbox.
                try { origRemoveItem.call(localStorage, 'zohra_user'); } catch (e) {}
                try { delete _mem['zohra_user']; } catch (e) {}
                try {
                    var __p = '';
                    try { __p = window.location.pathname || ''; } catch (e2) {}
                    if (__p.indexOf('login.html') < 0 && __p.indexOf('register.html') < 0) {
                        window.location.href = 'login.html';
                    }
                } catch (e) {}
                return;
            }

            var left = 0;
            function done() { left--; if (left <= 0) checkReady(); }
            function load(action, key, params, cb) {
                left++;
                xhrGet(action, params, function(resp) {
                    if (resp && resp.success) {
                        try {
                            if (cb) { cb(resp); }
                            else if (key) { storeServerValue(key, JSON.stringify(resp.data)); }
                        } catch (e) {}
                    }
                    done();
                });
            }

            load('patients', 'zohra_users');
            load('appointments', 'zohra_appointments');

            if (role === 'doctor' || role === 'receptionist') {
                load('mealplans', 'zohra_mealplans');
                load('plans', 'zohra_explans');
                load('messages', 'zohra_msgs');
                // Patient-generated data: bulk GET (no phone) returns ALL rows,
                // grouped per patient so this device sees other devices' updates.
                load('progress', null, null, function(resp) {
                    if (resp && resp.success && resp.data) {
                        storeGroupedRows(resp.data, 'zohra_progress_', storeServerValue);
                    }
                });
                load('tests', null, null, function(resp) {
                    if (resp && resp.success && resp.data) {
                        storeGroupedRows(resp.data, 'zohra_tests_', storeServerValue);
                    }
                });
                load('water', null, null, function(resp) {
                    if (resp && resp.success && resp.data) {
                        storeBulkWater(resp.data, storeServerValue);
                    } else {
                        loadDoctorWatersPerPatient(storeServerValue);
                    }
                });
                if (phone) load('notifications', null, { phone: phone }, function(resp) {
                    if (resp && resp.success && resp.data) {
                        for (var nKey in resp.data) {
                            if (resp.data.hasOwnProperty(nKey)) {
                                storeServerValue(nKey, JSON.stringify(resp.data[nKey]));
                            }
                        }
                    }
                });
            }

            if (role === 'user') {
                load('mealplans', 'zohra_mealplans');
                load('plans', 'zohra_explans');
                load('messages', 'zohra_msgs');
                load('progress', 'zohra_progress_' + phone, { phone: phone });
                load('tests', 'zohra_tests_' + phone, { phone: phone });
                load('water', null, { phone: phone }, function(resp) {
                    if (resp && resp.success && resp.data) {
                        for (var dateKey in resp.data) {
                            if (resp.data.hasOwnProperty(dateKey)) {
                                storeServerValue('zohra_water_' + phone + '_' + dateKey, JSON.stringify(resp.data[dateKey]));
                            }
                        }
                    }
                });
                load('notifications', null, { phone: phone }, function(resp) {
                    if (resp && resp.success && resp.data) {
                        for (var nKey in resp.data) {
                            if (resp.data.hasOwnProperty(nKey)) {
                                storeServerValue(nKey, JSON.stringify(resp.data[nKey]));
                            }
                        }
                    }
                });
            }

            if (left === 0) checkReady();
        });
    }

    // ---------- Storage monkey-patch (kept for compatibility, fixed `this`) ----------
    Storage.prototype.getItem = function(key) {
        if (this !== localStorage) return origGetItem.call(this, key);
        if (key && _mem.hasOwnProperty(key)) return _mem[key];
        return origGetItem.call(localStorage, key);
    };

    Storage.prototype.setItem = function(key, value) {
        if (this !== localStorage) { origSetItem.call(this, key, value); return; }
        if (typeof value !== 'string') { try { value = String(value); } catch (e) { value = ''; } }
        _mem[key] = value;
        origSetItem.call(localStorage, key, value);
        if (key && key.indexOf('zohra_') === 0) syncToApi(key, value);
    };

    Storage.prototype.removeItem = function(key) {
        if (this !== localStorage) { origRemoveItem.call(this, key); return; }
        delete _mem[key];
        origRemoveItem.call(localStorage, key);
        if (key && key.indexOf('zohra_') === 0) syncRemoveToApi(key);
    };

    function refreshFromApi(cb) {
        if (!_user || !_user.phone) { if (cb) cb(); return; }
        // push pending writes first so refresh never wipes unsynced local edits
        flushOutbox();
        var role = _user.role;
        var phone = _user.phone;
        var left = 0;
        function done() { left--; if (left <= 0 && cb) cb(); }
        function load(action, key, params, customCb) {
            left++;
            xhrGet(action, params, function(resp) {
                if (resp && resp.success) {
                    try {
                        if (customCb) { customCb(resp); }
                        else if (key) {
                            storeServerValuePersist(key, JSON.stringify(resp.data));
                        }
                    } catch (e) {}
                }
                done();
            });
        }

        load('patients', 'zohra_users');
        load('appointments', 'zohra_appointments');
        load('mealplans', 'zohra_mealplans');
        load('plans', 'zohra_explans');
        load('messages', 'zohra_msgs');
        load('notifications', null, { phone: phone }, function(resp) {
            if (resp && resp.success && resp.data) {
                for (var nKey in resp.data) {
                    if (resp.data.hasOwnProperty(nKey)) {
                        storeServerValuePersist(nKey, JSON.stringify(resp.data[nKey]));
                    }
                }
            }
        });

        if (role === 'user') {
            load('progress', 'zohra_progress_' + phone, { phone: phone });
            load('tests', 'zohra_tests_' + phone, { phone: phone });
            load('water', null, { phone: phone }, function(resp) {
                if (resp && resp.success && resp.data) {
                    for (var dateKey in resp.data) {
                        if (resp.data.hasOwnProperty(dateKey)) {
                            storeServerValuePersist('zohra_water_' + phone + '_' + dateKey, JSON.stringify(resp.data[dateKey]));
                        }
                    }
                }
            });
        }

        if (role === 'doctor' || role === 'receptionist') {
            load('progress', null, null, function(resp) {
                if (resp && resp.success && resp.data) {
                    storeGroupedRows(resp.data, 'zohra_progress_', storeServerValuePersist);
                }
            });
            load('tests', null, null, function(resp) {
                if (resp && resp.success && resp.data) {
                    storeGroupedRows(resp.data, 'zohra_tests_', storeServerValuePersist);
                }
            });
            load('water', null, null, function(resp) {
                if (resp && resp.success && resp.data) {
                    storeBulkWater(resp.data, storeServerValuePersist);
                } else {
                    loadDoctorWatersPerPatient(storeServerValuePersist);
                }
            });
        }

        if (left === 0 && cb) cb();
    }

    // multi-tab: same browser, other tab wrote -> update _mem so getItem stays fresh
    try {
        window.addEventListener('storage', function(e) {
            if (!e || !e.key) return;
            if (e.key.indexOf('zohra_') !== 0) return;
            if (e.key === OUTBOX_KEY) return;
            if (e.newValue === null || typeof e.newValue === 'undefined') delete _mem[e.key];
            else _mem[e.key] = e.newValue;
        });
    } catch (e) {}
    try {
        document.addEventListener('visibilitychange', function() {
            if (!document.hidden && _ready && _user) refreshFromApi();
        });
        window.addEventListener('online', function() { flushOutbox(); });
    } catch (e) {}

    setInterval(function() { flushOutbox(); }, 15000);

    window.__apiSyncDoctorSync = function() { return _docSync; };
    window.__apiSyncReady = waitForReady;
    window.__apiSyncRefresh = refreshFromApi;
    window.__apiSyncFlush = flushOutbox;
    window.__apiSyncStatus = function() {
        return { ready: _ready, user: _user ? _user.phone : null, pending: _pending, outbox: readOutbox().length };
    };
    loadFromApi();
})();
