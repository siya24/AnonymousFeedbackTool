param(
    [string]$ExternalUrl = "http://host.docker.internal:18084",
    [string]$InternalUrl = "http://host.docker.internal:18083",
    [string]$ReportDir = "zap-reports",
    [string]$Image = "ghcr.io/zaproxy/zaproxy:stable"
)

$ErrorActionPreference = 'Stop'

function Assert-DockerReady {
    if (-not (Get-Command docker -ErrorAction SilentlyContinue)) {
        throw "Docker CLI was not found on PATH. Install Docker Desktop and reopen PowerShell."
    }

    $serverVersion = ''
    $dockerCheckFailed = $false

    try {
        $serverVersion = (& docker version --format '{{.Server.Version}}' 2>$null | Out-String).Trim()
    } catch {
        $dockerCheckFailed = $true
    }

    if ($dockerCheckFailed -or $LASTEXITCODE -ne 0 -or $serverVersion -eq '') {
        throw "Docker daemon is not reachable (Docker Desktop may be stopped or failed to start). Start Docker Desktop and wait for 'Engine running', then rerun this script."
    }
}

Assert-DockerReady

$repoRoot = Resolve-Path (Join-Path $PSScriptRoot '..')
$reportsPath = Join-Path $repoRoot $ReportDir
New-Item -ItemType Directory -Path $reportsPath -Force | Out-Null

Write-Output "Using ZAP image: $Image"
Write-Output "Reports directory: $reportsPath"
Write-Output "Running passive baseline scans only (no active attack)."

$volumeArg = "${repoRoot}:/zap/wrk"

$externalArgs = @(
    'run', '--rm', '-t',
    '-v', $volumeArg,
    $Image,
    'zap-baseline.py',
    '-t', $ExternalUrl,
    '-r', (($ReportDir -replace '\\', '/') + '/external-baseline.html'),
    '-J', (($ReportDir -replace '\\', '/') + '/external-baseline.json')
)

$internalArgs = @(
    'run', '--rm', '-t',
    '-v', $volumeArg,
    $Image,
    'zap-baseline.py',
    '-t', $InternalUrl,
    '-r', (($ReportDir -replace '\\', '/') + '/internal-baseline.html'),
    '-J', (($ReportDir -replace '\\', '/') + '/internal-baseline.json')
)

Write-Output "Scanning external target: $ExternalUrl"
& docker @externalArgs
$externalExit = $LASTEXITCODE
if ($externalExit -eq 3) {
    throw "External baseline scan failed with exit code 3 (runtime/connectivity failure). Ensure target app is running and reachable from Docker. On Windows, start PHP with 0.0.0.0 (not 127.0.0.1), e.g. php -S 0.0.0.0:18084 -t external external/index.php"
}
if ($externalExit -eq 1) {
    throw "External baseline scan found FAIL alerts (exit code 1). Review the generated baseline report and remediate critical findings before continuing."
}
if ($externalExit -eq 2) {
    Write-Warning "External baseline scan found WARN alerts (exit code 2). Continuing to scan internal target."
}

Write-Output "Scanning internal target: $InternalUrl"
& docker @internalArgs
$internalExit = $LASTEXITCODE
if ($internalExit -eq 3) {
    throw "Internal baseline scan failed with exit code 3 (runtime/connectivity failure). Ensure target app is running and reachable from Docker. On Windows, start PHP with 0.0.0.0 (not 127.0.0.1), e.g. php -S 0.0.0.0:18083 -t internal internal/index.php"
}
if ($internalExit -eq 1) {
    throw "Internal baseline scan found FAIL alerts (exit code 1). Review the generated baseline report and remediate critical findings before continuing."
}
if ($internalExit -eq 2) {
    Write-Warning "Internal baseline scan found WARN alerts (exit code 2)."
}

Write-Output "Baseline scans completed successfully."
Write-Output "Reports generated under: $reportsPath"
