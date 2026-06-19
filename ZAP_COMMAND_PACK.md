# OWASP ZAP Command Pack (Local)

This guide gives ready-to-run ZAP scripts and commands for this project on both Windows and Linux.

## 1. Fast Start (Recommended)

Run location rules:

- Option A: Run from repository root (`AnonymousFeedbackTool`).
- Option B: Run from anywhere using absolute script paths.

Run these two scripts in order:

1. Passive baseline scan (safe):
  - Windows: `./scripts/run_zap_baseline.ps1`
  - Linux: `./scripts/run_zap_baseline.sh`
2. Controlled active scan (allowlist + excludes + High-only fail rules):
  - Windows: `./scripts/run_zap_active_controlled.ps1`
  - Linux: `./scripts/run_zap_active_controlled.sh`

Default controlled active scan allowlist:

- External: `/api/health/storage`, `/api/categories`, `/api/statuses`
- Internal: `/api/categories`, `/api/provinces`, `/api/statuses`, `/api/stages`

Default controlled active scan excludes:

- `.*/api/hr/logout.*`
- `.*/api/hr/login.*`
- `.*/api/hr/cases/.*/co-investigators.*`

## 2. Scope and Targets

Use these local targets:

- External app: `http://127.0.0.1:18084`
- Internal app: `http://127.0.0.1:18083`

Recommended smoke endpoints:

- External health: `/api/health/storage`
- Internal public API: `/api/categories`

## 3. Pre-Requisites

- Docker installed and running.
- Local app servers running:
  - internal on `127.0.0.1:18083`
  - external on `127.0.0.1:18084`
- Run scans only in local/dev/UAT (not production).

Quick Docker readiness check (Windows PowerShell):

```powershell
docker version
```

If server info is missing or you see a pipe error, start Docker Desktop first.

Important for Docker-based scans on Windows:

- Start PHP servers on `0.0.0.0` (not `127.0.0.1`) so containers can reach them via `host.docker.internal`.

## 4. Start Local Apps

Important:

- `php -S` router script paths are relative to your current terminal folder.
- If you are not in the repo root, use absolute paths.

### Windows (PowerShell)

```powershell
# Terminal 1
php -S 0.0.0.0:18083 -t internal internal/index.php

# Terminal 2
php -S 0.0.0.0:18084 -t external external/index.php
```

From anywhere (absolute paths):

```powershell
# Terminal 1
php -S 0.0.0.0:18083 -t "C:\Development\Internal\AnonymousFeedbackTool\internal" "C:\Development\Internal\AnonymousFeedbackTool\internal\index.php"

# Terminal 2
php -S 0.0.0.0:18084 -t "C:\Development\Internal\AnonymousFeedbackTool\external" "C:\Development\Internal\AnonymousFeedbackTool\external\index.php"
```

### Linux

```bash
# Terminal 1
php -S 0.0.0.0:18083 -t internal internal/index.php

# Terminal 2
php -S 0.0.0.0:18084 -t external external/index.php
```

From anywhere (absolute paths):

```bash
# Terminal 1
php -S 0.0.0.0:18083 -t /path/to/AnonymousFeedbackTool/internal /path/to/AnonymousFeedbackTool/internal/index.php

# Terminal 2
php -S 0.0.0.0:18084 -t /path/to/AnonymousFeedbackTool/external /path/to/AnonymousFeedbackTool/external/index.php
```

## 5. Prepare Report Folder

### Windows (PowerShell)

```powershell
New-Item -ItemType Directory -Force -Path .\zap-reports | Out-Null
```

### Linux

```bash
mkdir -p ./zap-reports
```

## 6. Baseline Scan (Passive, Safe)

Baseline exit codes used in this project:

- `0`: no FAIL/WARN alerts
- `1`: FAIL alerts found (script stops)
- `2`: WARN alerts found (script continues; expected during hardening)
- `3`: runtime/connectivity error (script stops)

Script-first usage:

### Windows (PowerShell)

From repository root:

```powershell
.\scripts\run_zap_baseline.ps1
```

From anywhere:

```powershell
& "C:\Development\Internal\AnonymousFeedbackTool\scripts\run_zap_baseline.ps1"
```

### Linux

From repository root:

```bash
chmod +x ./scripts/run_zap_baseline.sh
./scripts/run_zap_baseline.sh
```

From anywhere:

```bash
bash /path/to/AnonymousFeedbackTool/scripts/run_zap_baseline.sh
```

Manual Docker commands (optional):

Use this first. It crawls and passively analyzes.

### Windows (PowerShell)

```powershell
docker run --rm -t -v "${PWD}:/zap/wrk" ghcr.io/zaproxy/zaproxy:stable zap-baseline.py -t http://host.docker.internal:18084 -r zap-reports/external-baseline.html -J zap-reports/external-baseline.json

docker run --rm -t -v "${PWD}:/zap/wrk" ghcr.io/zaproxy/zaproxy:stable zap-baseline.py -t http://host.docker.internal:18083 -r zap-reports/internal-baseline.html -J zap-reports/internal-baseline.json
```

### Linux

```bash
docker run --rm -t --network host -v "$(pwd):/zap/wrk" ghcr.io/zaproxy/zaproxy:stable zap-baseline.py -t http://127.0.0.1:18084 -r zap-reports/external-baseline.html -J zap-reports/external-baseline.json

docker run --rm -t --network host -v "$(pwd):/zap/wrk" ghcr.io/zaproxy/zaproxy:stable zap-baseline.py -t http://127.0.0.1:18083 -r zap-reports/internal-baseline.html -J zap-reports/internal-baseline.json
```

## 7. Controlled Active Scan (Allowlist + Excludes)

Run after baseline. This script limits scanning to approved routes and applies fail policy from `.zap/rules-high-only.conf`.

Script-first usage:

### Windows (PowerShell)

From repository root:

```powershell
.\scripts\run_zap_active_controlled.ps1
```

From anywhere:

```powershell
& "C:\Development\Internal\AnonymousFeedbackTool\scripts\run_zap_active_controlled.ps1"
```

### Linux

From repository root:

```bash
chmod +x ./scripts/run_zap_active_controlled.sh
./scripts/run_zap_active_controlled.sh
```

From anywhere:

```bash
bash /path/to/AnonymousFeedbackTool/scripts/run_zap_active_controlled.sh
```

Override targets quickly (Windows example):

```powershell
.\scripts\run_zap_active_controlled.ps1 -Allowlist @(
  'http://host.docker.internal:18084/api/health/storage',
  'http://host.docker.internal:18083/api/categories'
)
```

Override targets quickly (Linux example):

```bash
EXTERNAL_BASE_URL=http://127.0.0.1:18084 \
INTERNAL_BASE_URL=http://127.0.0.1:18083 \
./scripts/run_zap_active_controlled.sh
```

Manual `zap-full-scan.py` commands (optional):

### Windows (PowerShell)

```powershell
docker run --rm -t -v "${PWD}:/zap/wrk" ghcr.io/zaproxy/zaproxy:stable zap-full-scan.py -t http://host.docker.internal:18084 -r /zap/wrk/zap-reports/external-full.html -J /zap/wrk/zap-reports/external-full.json

docker run --rm -t -v "${PWD}:/zap/wrk" ghcr.io/zaproxy/zaproxy:stable zap-full-scan.py -t http://host.docker.internal:18083 -r /zap/wrk/zap-reports/internal-full.html -J /zap/wrk/zap-reports/internal-full.json
```

### Linux

```bash
docker run --rm -t --network host -v "$(pwd):/zap/wrk" ghcr.io/zaproxy/zaproxy:stable zap-full-scan.py -t http://127.0.0.1:18084 -r /zap/wrk/zap-reports/external-full.html -J /zap/wrk/zap-reports/external-full.json

docker run --rm -t --network host -v "$(pwd):/zap/wrk" ghcr.io/zaproxy/zaproxy:stable zap-full-scan.py -t http://127.0.0.1:18083 -r /zap/wrk/zap-reports/internal-full.html -J /zap/wrk/zap-reports/internal-full.json
```

## 8. Fail Policy: Fail CI on High Only

Create a rules config file to make scans fail only on High risk alerts.

Create `.zap/rules-high-only.conf` with:

```text
# Fail on High only
FAIL	.*	High
WARN	.*	Medium
INFO	.*	Low
IGNORE	.*	Informational
```

Then run scans with `-c /zap/wrk/.zap/rules-high-only.conf`.

### Windows example (baseline external)

```powershell
docker run --rm -t -v "${PWD}:/zap/wrk" ghcr.io/zaproxy/zaproxy:stable zap-baseline.py -t http://host.docker.internal:18084 -c /zap/wrk/.zap/rules-high-only.conf -r zap-reports/external-baseline.html -J zap-reports/external-baseline.json
```

### Linux example (full internal)

```bash
docker run --rm -t --network host -v "$(pwd):/zap/wrk" ghcr.io/zaproxy/zaproxy:stable zap-full-scan.py -t http://127.0.0.1:18083 -c /zap/wrk/.zap/rules-high-only.conf -r /zap/wrk/zap-reports/internal-full.html -J /zap/wrk/zap-reports/internal-full.json
```

## 9. Internal Authenticated Testing (Cookie + CSRF)

For internal authenticated coverage, use ZAP Desktop with context/session:

1. Start ZAP Desktop and set browser proxy to ZAP.
2. Log into internal app manually through proxied browser.
3. Confirm session cookies are present (`hr_auth_token`, `hr_csrf_token`).
4. Create a Context limited to `http://127.0.0.1:18083`.
5. Run Spider and then Active Scan inside that context.
6. Exclude logout route if the session is being terminated too early.

Note: the packaged `zap-baseline.py` and `zap-full-scan.py` commands above are mostly unauthenticated unless you supply automation/auth scripts.

## 10. API-Only Scan Option (When OpenAPI Exists)

If you expose an OpenAPI spec endpoint later, use:

### Windows (PowerShell)

```powershell
docker run --rm -t -v "${PWD}:/zap/wrk" ghcr.io/zaproxy/zaproxy:stable zap-api-scan.py -f openapi -t http://host.docker.internal:18084/path/to/openapi.json -r /zap/wrk/zap-reports/external-api.html -J /zap/wrk/zap-reports/external-api.json
```

### Linux

```bash
docker run --rm -t --network host -v "$(pwd):/zap/wrk" ghcr.io/zaproxy/zaproxy:stable zap-api-scan.py -f openapi -t http://127.0.0.1:18084/path/to/openapi.json -r /zap/wrk/zap-reports/external-api.html -J /zap/wrk/zap-reports/external-api.json
```

## 11. Quick Interpretation

- High alerts: fix immediately, rerun targeted scan.
- Medium alerts: fix before release unless formally accepted.
- Low/Info: track and improve over time.
- Always validate potential false positives manually.

## 12. Suggested Local Sequence

1. Start internal and external local servers first.
2. Run baseline script first (from repo root or by absolute path).
3. Confirm reports exist in `zap-reports`.
4. Fix obvious header/cookie/session issues.
5. Run controlled active script (from repo root or by absolute path).
6. Run authenticated internal scan in ZAP Desktop for HR-only surfaces.
7. Export HTML + JSON reports into `zap-reports/`.
8. Attach reports to release evidence.

Quick command recap (Windows, from anywhere):

```powershell
& "C:\Development\Internal\AnonymousFeedbackTool\scripts\run_zap_baseline.ps1"
& "C:\Development\Internal\AnonymousFeedbackTool\scripts\run_zap_active_controlled.ps1"
```

Quick command recap (Linux, from anywhere):

```bash
bash /path/to/AnonymousFeedbackTool/scripts/run_zap_baseline.sh
bash /path/to/AnonymousFeedbackTool/scripts/run_zap_active_controlled.sh
```

## 13. Troubleshooting (Windows Docker Pipe Error)

If you get this error:

`failed to connect to the docker API at npipe:////./pipe/dockerDesktopLinuxEngine...`

Do this:

1. Start Docker Desktop.
2. Wait for status: `Engine running`.
3. Run:

```powershell
docker version
```

4. If still failing, restart Docker Desktop and run:

```powershell
wsl --status
```

5. Re-run baseline script:

```powershell
& "C:\Development\Internal\AnonymousFeedbackTool\scripts\run_zap_baseline.ps1"
```

Note: both PowerShell ZAP scripts now perform a Docker preflight check and fail early with a clear message if Docker is not ready.

## 14. Troubleshooting (WSL Command Not Recognized)

If you get this error:

`wsl : The term 'wsl' is not recognized...`

and PowerShell shows a hint that the command exists in the current location, run it explicitly:

```powershell
.\wsl.exe --install
```

or run with full path:

```powershell
& "$env:WINDIR\System32\wsl.exe" --install
```

Then restart Windows and verify:

```powershell
& "$env:WINDIR\System32\wsl.exe" --status
docker version
```

If WSL still fails to install, enable required features in an Administrator PowerShell and reboot:

```powershell
dism /online /enable-feature /featurename:Microsoft-Windows-Subsystem-Linux /all /norestart
dism /online /enable-feature /featurename:VirtualMachinePlatform /all /norestart
```

## 15. Troubleshooting (SQLSTATE 08001 / LocalDB)

If PHP logs show SQLSTATE 08001 (SQL Server Network Interfaces) while scanning:

1. Start LocalDB instance:

```powershell
sqllocaldb s MSSQLLocalDB
```

2. Verify LocalDB is running:

```powershell
sqllocaldb i MSSQLLocalDB
```

3. Restart both PHP servers:

```powershell
# stop listeners on scan ports
Get-NetTCPConnection -LocalPort 18083,18084 -State Listen |
  Select-Object -ExpandProperty OwningProcess -Unique |
  ForEach-Object { Stop-Process -Id $_ -Force }

# start internal
php -S 0.0.0.0:18083 -t "C:\Development\Internal\AnonymousFeedbackTool\internal" "C:\Development\Internal\AnonymousFeedbackTool\internal\index.php"

# start external (new terminal)
php -S 0.0.0.0:18084 -t "C:\Development\Internal\AnonymousFeedbackTool\external" "C:\Development\Internal\AnonymousFeedbackTool\external\index.php"
```

4. Re-run baseline scan:

```powershell
& "C:\Development\Internal\AnonymousFeedbackTool\scripts\run_zap_baseline.ps1"
```
