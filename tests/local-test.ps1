<#
.SYNOPSIS
  Local end-to-end test for multi-device sync. One file, one command.
.DESCRIPTION
  Spins up an isolated stack (MariaDB 10.7 + PHP 8.1 serving this project),
  runs 26 API checks, then simulates 2 devices through the REAL api-sync.js,
  then tears everything down. Exit code 0 = all green.
 -prerequisites: Docker Desktop running, Node.js, Windows PowerShell 5.1+.
.USAGE
  powershell -NoProfile -ExecutionPolicy Bypass -File tests\local-test.ps1
  powershell -NoProfile -ExecutionPolicy Bypass -File tests\local-test.ps1 -HostPort 8091
.HOW IT WORKS
  - config.php hardcodes DB_HOST=localhost (socket), so both containers share
    MariaDB's unix socket via a Docker volume at /run/mysqld. No repo code is
    changed for testing. DB credentials are read from v2/config/.env
    (gitignored, never committed).
  - Test users use fixed phones/ids so reruns converge instead of duplicating.
  - Nothing touches production: own containers, network, database.
.GOTCHA (PowerShell)
  In double-quoted strings "$base?action=" parses $base?action as ONE variable
  (expands to empty). Always use braces: "${base}?action=".
#>
param([string]$HostPort = '8090')

$ErrorActionPreference = 'Stop'
$root = Split-Path $PSScriptRoot -Parent
$base = "http://127.0.0.1:${HostPort}"
$pass = 0; $fail = 0

function Check($name, $cond) {
    if ($cond) { Write-Output "PASS $name"; $script:pass++ }
    else { Write-Output "FAIL $name"; $script:fail++ }
}

function Api($action, $method, $body, $sess, $extra='') {
    if ($extra -ne '') { $extra = '&' + $extra }
    $uri = "${base}/api/index.php?action=${action}${extra}"
    try {
        if ($method -eq 'GET') {
            return Invoke-RestMethod -Uri $uri -Method GET -WebSession $sess
        }
        $json = $body | ConvertTo-Json -Depth 8 -Compress
        return Invoke-RestMethod -Uri $uri -Method $method -Body $json -ContentType 'application/json' -WebSession $sess
    } catch {
        # 4xx/5xx still carry a JSON body - surface it instead of crashing
        $resp = $_.Exception.Response
        if ($resp) {
            $reader = New-Object System.IO.StreamReader($resp.GetResponseStream())
            $txt = $reader.ReadToEnd()
            try { return $txt | ConvertFrom-Json } catch { return @{success=$false; message=$txt} }
        }
        return @{success=$false; message=$_.Exception.Message}
    }
}

function Stack-Up {
    Write-Output "Project: $root"
    try { docker ps 2>&1 | Out-Null } catch { Write-Output 'FAIL docker daemon not running - start Docker Desktop'; exit 1 }
    if ($LASTEXITCODE -ne 0) { Write-Output 'FAIL docker daemon not running - start Docker Desktop'; exit 1 }

    $envMap = @{}
    Get-Content (Join-Path $root 'v2\config\.env') | ForEach-Object {
        $line = $_.Trim()
        if ($line -ne '' -and -not $line.StartsWith('#') -and $line.Contains('=')) {
            $kv = $line.Split('=', 2)
            $envMap[$kv[0].Trim()] = $kv[1].Trim()
        }
    }
    $dbUser = $envMap['DB_USER']; $dbPass = $envMap['DB_PASS']
    if (-not $dbUser -or -not $dbPass) { Write-Output 'FAIL DB_USER/DB_PASS missing in v2/config/.env'; exit 1 }

    $ea = $ErrorActionPreference; $ErrorActionPreference = 'Continue'
    docker rm -f clinic-test-php clinic-test-db 2>&1 | Out-Null
    docker network create clinic-test 2>&1 | Out-Null
    docker volume create clinic-mysql-sock 2>&1 | Out-Null
    $ErrorActionPreference = $ea

    docker run -d --name clinic-test-db --network clinic-test `
        -v clinic-mysql-sock:/run/mysqld `
        -e MYSQL_ROOT_PASSWORD=testroot -e MYSQL_DATABASE=h421704_clinic `
        -e MYSQL_USER=$dbUser -e MYSQL_PASSWORD=$dbPass `
        mariadb:10-focal 2>&1 | Out-Null

    $deadline = (Get-Date).AddMinutes(2)
    while ($true) {
        docker exec clinic-test-db test -S /run/mysqld/mysqld.sock 2>&1 | Out-Null
        if ($LASTEXITCODE -eq 0) { break }
        if ((Get-Date) -gt $deadline) { Write-Output 'FAIL mariadb socket never appeared'; exit 1 }
        Start-Sleep -Seconds 3
    }
    Write-Output 'DB socket ready'

    docker run -d --name clinic-test-php --network clinic-test `
        -v clinic-mysql-sock:/run/mysqld -p "${HostPort}:8080" `
        -v "${root}:/app" -w /app php:8.1-cli sh -c "docker-php-ext-install pdo_mysql > /tmp/ext.log 2>&1 && echo 'pdo_mysql.default_socket=/run/mysqld/mysqld.sock' > /usr/local/etc/php/conf.d/zz-test-socket.ini && php -S 0.0.0.0:8080 router.php" 2>&1 | Out-Null

    $deadline = (Get-Date).AddMinutes(4)
    while ($true) {
        try {
            $r = Invoke-RestMethod -Uri "${base}/api/index.php?action=debug" -TimeoutSec 10
            if ($r.success -eq $true) { break }
        } catch {}
        if ((Get-Date) -gt $deadline) { Write-Output 'FAIL API never became healthy'; exit 1 }
        Start-Sleep -Seconds 5
    }
    Write-Output "STACK UP: $base (tables created, seed accounts present)"
}

function Stack-Down {
    $ea = $ErrorActionPreference; $ErrorActionPreference = 'Continue'
    docker rm -f clinic-test-php clinic-test-db 2>&1 | Out-Null
    docker network rm clinic-test 2>&1 | Out-Null
    docker volume rm clinic-mysql-sock 2>&1 | Out-Null
    $ErrorActionPreference = $ea
    Write-Output 'STACK DOWN'
}

# Single-quoted heredocs: NO expansion, so embedded JS is byte-safe.
$deviceEnvJs = @'
const fs = require('fs');
const BASE = (process.env.CLINIC_BASE || 'http://127.0.0.1:8090').replace(/\/$/, '');
const SYNC_SRC = require('path').join(process.env.CLINIC_ROOT, 'api-sync.js');
function Storage() {}
Storage.prototype.getItem = function(k) { return Object.prototype.hasOwnProperty.call(this._s, k) ? this._s[k] : null; };
Storage.prototype.setItem = function(k, v) { this._s[k] = String(v); };
Storage.prototype.removeItem = function(k) { delete this._s[k]; };
Object.defineProperty(Storage.prototype, 'length', { get() { return Object.keys(this._s).length; } });
Storage.prototype.key = function(i) { return Object.keys(this._s)[i] || null; };
const localStorage = new Storage();
localStorage._s = {};
global.Storage = Storage;
global.localStorage = localStorage;
global.window = global;
global.document = { hidden: false, addEventListener() {} };
global.addEventListener = () => {};
const jar = {};
function storeCookies(res) {
    const list = typeof res.headers.getSetCookie === 'function'
        ? res.headers.getSetCookie()
        : (res.headers.get('set-cookie') ? [res.headers.get('set-cookie')] : []);
    for (const raw of list) {
        const pair = raw.split(';')[0].split('=');
        if (pair.length >= 2) jar[pair[0].trim()] = pair.slice(1).join('=').trim();
    }
}
function cookieHeader() {
    return Object.entries(jar).map(([k, v]) => k + '=' + v).join('; ');
}
global.XMLHttpRequest = function() { this.headers = {}; };
global.XMLHttpRequest.prototype.open = function(m, url) {
    this._method = m;
    this._url = url.indexOf('/') === 0 ? BASE + url : url;
};
global.XMLHttpRequest.prototype.setRequestHeader = function(k, v) { this.headers[k] = v; };
global.XMLHttpRequest.prototype.abort = function() { this._aborted = true; };
global.XMLHttpRequest.prototype.send = function(body) {
    const self = this;
    const headers = Object.assign({ 'Content-Type': 'application/json' }, self.headers);
    const ck = cookieHeader();
    if (ck) headers['Cookie'] = ck;
    fetch(self._url, { method: self._method, headers, body: self._method === 'GET' ? undefined : body })
        .then(async (res) => {
            if (self._aborted) return;
            storeCookies(res);
            self.status = res.status;
            self.responseText = await res.text();
            self.readyState = 4;
            if (self.onreadystatechange) self.onreadystatechange();
        })
        .catch(() => {
            if (self._aborted) return;
            self.status = 0; self.readyState = 4;
            if (self.onreadystatechange) self.onreadystatechange();
            if (self.onerror) self.onerror();
        });
};
async function api(method, action, body, query = '') {
    const headers = { 'Content-Type': 'application/json' };
    const ck = cookieHeader();
    if (ck) headers['Cookie'] = ck;
    const res = await fetch(BASE + '/api/index.php?action=' + action + query, {
        method, headers, body: method === 'GET' ? undefined : JSON.stringify(body || {}),
    });
    storeCookies(res);
    return res.json();
}
function loadSync() { eval(fs.readFileSync(SYNC_SRC, 'utf8')); }
module.exports = { localStorage, api, loadSync };
'@

$device1Js = @'
const { localStorage, api, loadSync } = require('./device-env.js');
(async () => {
    let me = await api('POST', 'auth/register', { first: 'Sara', last: 'Ahmadi', phone: '09120000002', age: 28, gender: 'female', height: 165, weight: 65, password: 'testpass123' });
    if (!me.success && /already registered/.test(me.message || '')) {
        me = await api('POST', 'auth/login', { phone: '09120000002', password: 'testpass123' });
    }
    if (!me.success) { console.log('FAIL device1 auth'); process.exit(1); }
    localStorage.setItem('zohra_user', JSON.stringify(me.data));
    loadSync();
    window.__apiSyncReady(() => {
        localStorage.setItem('zohra_msgs', JSON.stringify([{ id: 9001, from: '09120000002', to: 'doctor', text: 'device1 hello' }]));
        localStorage.setItem('zohra_progress_09120000002', JSON.stringify([{ id: 9002, weight: 65, date: 'd1' }]));
        localStorage.setItem('zohra_water_09120000002_2026-09-10', JSON.stringify({ count: 5, glasses: [0, 1, 2, 3, 4] }));
        localStorage.setItem('zohra_seen_notifications_09120000002', JSON.stringify([42]));
        setTimeout(async () => {
            const st = window.__apiSyncStatus();
            console.log('device1 sync status: ' + JSON.stringify(st));
            const msgs = await api('GET', 'messages');
            const prog = await api('GET', 'progress', null, '&phone=09120000002');
            const water = await api('GET', 'water', null, '&phone=09120000002');
            const notif = await api('GET', 'notifications', null, '&phone=09120000002');
            const ok = st.outbox === 0
                && msgs.data.some(m => m.id === 9001)
                && prog.data.length >= 1
                && water.data['2026-09-10'] && water.data['2026-09-10'].count === 5
                && JSON.stringify(notif.data).includes('zohra_seen_notifications_09120000002');
            console.log(ok ? 'DEVICE1 OK: all writes reached MySQL' : 'DEVICE1 FAIL');
            process.exit(ok ? 0 : 1);
        }, 3000);
    });
    setTimeout(() => { console.log('TIMEOUT'); process.exit(2); }, 25000);
})();
'@

$device2Js = @'
const { localStorage, api, loadSync } = require('./device-env.js');
(async () => {
    const me = await api('POST', 'auth/login', { phone: '09120000002', password: 'testpass123' });
    if (!me.success) { console.log('FAIL device2 auth'); process.exit(1); }
    localStorage.setItem('zohra_user', JSON.stringify(me.data));
    loadSync();
    window.__apiSyncReady(() => {
        const results = [];
        const check = (n, c) => results.push((c ? 'PASS ' : 'FAIL ') + n);
        const msgs = JSON.parse(localStorage.getItem('zohra_msgs') || '[]');
        check('device2 sees device1 message', msgs.some(m => m.id === 9001 && m.text === 'device1 hello'));
        const prog = JSON.parse(localStorage.getItem('zohra_progress_09120000002') || '[]');
        check('device2 sees progress', prog.some(p => p.id === 9002));
        const water = JSON.parse(localStorage.getItem('zohra_water_09120000002_2026-09-10') || '{"count":0}');
        check('device2 sees water count=5', water.count === 5);
        const seen = JSON.parse(localStorage.getItem('zohra_seen_notifications_09120000002') || '[]');
        check('device2 sees seen-state', Array.isArray(seen) && seen.includes(42));
        console.log(results.join('\n'));
        let failed = false;
        for (const r of results) { if (r.indexOf('FAIL') === 0) failed = true; }
        process.exit(failed ? 1 : 0);
    });
    setTimeout(() => { console.log('TIMEOUT'); process.exit(2); }, 25000);
})();
'@

# ================= orchestra =================
Stack-Up
$suiteFailed = $false
$simDir = Join-Path ([System.IO.Path]::GetTempPath()) 'clinic-sim'
try {
    Write-Output '--- API e2e (31 checks) ---'
    $s1 = New-Object Microsoft.PowerShell.Commands.WebRequestSession
    $r = Api 'auth/register' 'POST' @{first='Ali';last='Rezaei';phone='09120000001';age=30;gender='male';height=180;weight=80;password='testpass123'} $s1
    if ($r.success -ne $true -and $r.message -match 'already registered') {
        $r = Api 'auth/login' 'POST' @{phone='09120000001';password='testpass123'} $s1
    }
    Check 'register/login success' ($r.success -eq $true -and $r.data.role -eq 'user')
    $r = Api 'auth/me' 'GET' $null $s1
    Check 'auth/me returns user' ($r.success -eq $true -and ($r.user.phone -eq '09120000001' -or $r.data.phone -eq '09120000001'))

    # logged-out must be a quiet 200 + flag (never a 401 that litters consoles
    # and trips HTTP clients); api-sync.js keys its login bounce off the flag
    $sFresh = New-Object Microsoft.PowerShell.Commands.WebRequestSession
    $r = Api 'auth/me' 'GET' $null $sFresh
    Check 'auth/me logged-out is quiet 200 + flag' ($r.success -eq $false -and $r.loggedOut -eq $true)

    $prog = @(@{id=1001;patient_phone='09120000001';weight=80;waist=90;note='w1';date='d1'},@{id=1002;patient_phone='09120000001';weight=79;waist=89;note='w2';date='d2'})
    $r = Api 'progress' 'POST' @{phone='09120000001';data=$prog} $s1
    Check 'progress POST ok' ($r.success -eq $true)
    $r = Api 'progress' 'GET' $null $s1 'phone=09120000001'
    Check 'progress round-trip 2 rows' ($r.success -eq $true -and $r.data.Count -eq 2)

    $img = 'data:image/jpeg;base64,/9j/4AAQAAAA'
    $r = Api 'tests' 'POST' @{phone='09120000001';data=@(@{id=2001;patient_phone='09120000001';title='blood';description='d';image=$img;status='pending';date='dd'})} $s1
    Check 'tests POST ok' ($r.success -eq $true)
    $r = Api 'tests' 'GET' $null $s1 'phone=09120000001'
    Check 'test image data-URL intact' ($r.data[0].image -eq $img)

    $r = Api 'water' 'POST' @{phone='09120000001';date='2026-09-10';count=3;glasses=@(0,1,2)} $s1
    Check 'water POST ok' ($r.success -eq $true)
    $r = Api 'water' 'GET' $null $s1 'phone=09120000001'
    Check 'water round-trip' ($r.success -eq $true -and $r.data.'2026-09-10'.count -eq 3)

    $msgs = @(@{id=3001;from='09120000001';to='doctor';text='salam';date='now'},@{id=3002;from='doctor';to='09120000001';text='hi';date='now';read=$true})
    $r = Api 'messages' 'POST' @{data=$msgs} $s1
    Check 'messages POST ok' ($r.success -eq $true)
    $r = Api 'messages' 'GET' $null $s1
    $m2 = @($r.data | Where-Object { $_.id -eq 3002 })
    Check 'read flag persisted as bool' ($m2.Count -eq 1 -and $m2[0].read -eq $true)

    $r = Api 'appointments' 'POST' @{data=@(@{id=4001;patientPhone='09120000001';patientName='Ali Rezaei';date='2026-09-11';time='10:00';type='visit';status='pending'})} $s1
    Check 'appointment POST ok' ($r.success -eq $true)
    $r = Api 'mealplans' 'POST' @{data=@(@{id=5001;patientPhone='09120000001';patientName='Ali Rezaei';title='Plan A';description='d';breakfast='eggs';calories=2000;duration=7})} $s1
    Check 'mealplan POST ok' ($r.success -eq $true)
    $r = Api 'plans' 'POST' @{data=@(@{id=6001;patientPhone='09120000001';patientName='Ali Rezaei';title='Ex A';description='d';sat='run';calories=300;weeks=4})} $s1
    Check 'explan POST ok' ($r.success -eq $true)

    # plan deletes must reach the server (upsert-merge alone resurrects them)
    $r = Api 'mealplans/delete' 'POST' @{id=5001} $s1
    Check 'mealplan delete ok' ($r.success -eq $true)
    $r = Api 'mealplans' 'GET' $null $s1
    Check 'mealplan stays deleted' ((@($r.data | Where-Object { $_.id -eq 5001 })).Count -eq 0)
    $r = Api 'plans/delete' 'POST' @{id=6001} $s1
    Check 'explan delete ok' ($r.success -eq $true)
    $r = Api 'plans' 'GET' $null $s1
    Check 'explan stays deleted' ((@($r.data | Where-Object { $_.id -eq 6001 })).Count -eq 0)

    $r = Api 'notifications' 'POST' @{phone='09120000001';entries=@{'zohra_seen_notifications_09120000001'='[1,2]'}} $s1
    Check 'notifications POST ok' ($r.success -eq $true)
    $r = Api 'notifications' 'GET' $null $s1 'phone=09120000001'
    Check 'notifications round-trip' ($r.data.'zohra_seen_notifications_09120000001' -ne $null)

    $s2 = New-Object Microsoft.PowerShell.Commands.WebRequestSession
    $r = Invoke-RestMethod -Uri "${base}/api/index.php?action=auth/login" -Method POST -Body (@{phone='09120000001';password='testpass123'} | ConvertTo-Json) -ContentType 'application/json' -WebSession $s2
    Check 'device2 login ok' ($r.success -eq $true)
    $r = Api 'messages' 'GET' $null $s2
    Check 'device2 sees 2 msgs' ((@($r.data)).Count -ge 2)
    $m2b = @($r.data | Where-Object { $_.id -eq 3002 })
    Check 'device2 sees read=true' ($m2b.Count -eq 1 -and $m2b[0].read -eq $true)
    $r = Api 'water' 'GET' $null $s2 'phone=09120000001'
    Check 'device2 sees water' ($r.data.'2026-09-10'.count -eq 3)
    $r = Api 'progress' 'GET' $null $s2 'phone=09120000001'
    Check 'device2 sees progress' ((@($r.data)).Count -eq 2)

    $sD = New-Object Microsoft.PowerShell.Commands.WebRequestSession
    $r = Invoke-RestMethod -Uri "${base}/api/index.php?action=auth/login" -Method POST -Body (@{phone='09000000000';password='zohrabi1366'} | ConvertTo-Json) -ContentType 'application/json' -WebSession $sD
    Check 'doctor login ok' ($r.success -eq $true -and $r.data.role -eq 'doctor')

    $r = Api 'water/delete' 'POST' @{phone='09120000001';date='2026-09-10'} $s1
    Check 'water/delete fallback ok' ($r.success -eq $true)
    $r = Api 'water' 'GET' $null $s1 'phone=09120000001'
    Check 'water row gone' ($r.data.'2026-09-10' -eq $null)

    $r = Api 'water' 'POST' @{phone='09120000001';date='2026-09-10';count=1;glasses=@()} $s1
    $r = Api 'patients/delete' 'POST' @{phone='09120000001'} $sD
    Check 'patients/delete fallback ok' ($r.success -eq $true)
    $r = Api 'water' 'GET' $null $sD 'phone=09120000001'
    $wkeys = if ($r.data) { @($r.data.PSObject.Properties).Count } else { 0 }
    Check 'cascade wiped water' ($wkeys -eq 0)
    $r = Api 'progress' 'GET' $null $sD 'phone=09120000001'
    Check 'cascade wiped progress' ((@($r.data)).Count -eq 0)
    Write-Output "=== API: $pass passed, $fail failed ==="
    if ($fail -gt 0) { $suiteFailed = $true }

    Write-Output '--- device 1 writes via real api-sync.js ---'
    New-Item -ItemType Directory -Path $simDir -Force | Out-Null
    Set-Content -LiteralPath (Join-Path $simDir 'device-env.js') -Value $deviceEnvJs -Encoding UTF8
    Set-Content -LiteralPath (Join-Path $simDir 'device1.js') -Value $device1Js -Encoding UTF8
    Set-Content -LiteralPath (Join-Path $simDir 'device2.js') -Value $device2Js -Encoding UTF8
    $env:CLINIC_BASE = $base
    $env:CLINIC_ROOT = $root
    node (Join-Path $simDir 'device1.js')
    if ($LASTEXITCODE -ne 0) { $suiteFailed = $true }

    Write-Output '--- device 2 reads via real api-sync.js ---'
    node (Join-Path $simDir 'device2.js')
    if ($LASTEXITCODE -ne 0) { $suiteFailed = $true }
} finally {
    Write-Output '--- teardown ---'
    Stack-Down
    Remove-Item -LiteralPath $simDir -Recurse -Force -ErrorAction SilentlyContinue
}

if ($suiteFailed -or $fail -gt 0) { Write-Output 'SUITE FAILED'; exit 1 }
Write-Output 'SUITE PASSED'
