param(
    [string]$ExternalBaseUrl = "http://host.docker.internal:18084",
    [string]$InternalBaseUrl = "http://host.docker.internal:18083",
    [string]$ReportDir = "zap-reports",
    [string]$Image = "ghcr.io/zaproxy/zaproxy:stable",
    [string]$RulesFile = ".zap/rules-high-only.conf",
    [int]$SpiderMinutes = 2,
    [string[]]$Allowlist = @(
        "http://host.docker.internal:18084/api/health/storage",
        "http://host.docker.internal:18084/api/categories",
        "http://host.docker.internal:18084/api/statuses",
        "http://host.docker.internal:18083/api/categories",
        "http://host.docker.internal:18083/api/provinces",
        "http://host.docker.internal:18083/api/statuses",
        "http://host.docker.internal:18083/api/stages"
    ),
    [string[]]$ExcludeRegex = @(
        ".*/api/hr/logout.*",
        ".*/api/hr/login.*",
        ".*/api/hr/cases/.*/co-investigators.*"
    )
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

$rulesPath = Join-Path $repoRoot $RulesFile
$rulesDir = Split-Path -Parent $rulesPath
New-Item -ItemType Directory -Path $rulesDir -Force | Out-Null
if (-not (Test-Path -Path $rulesPath -PathType Leaf)) {
    @(
        ".*`tFAIL`tHigh",
        ".*`tWARN`tMedium",
        ".*`tINFO`tLow",
        ".*`tIGNORE`tInformational"
    ) | Set-Content -Path $rulesPath -Encoding ascii
}

function Test-AllowedBase {
    param([string]$Url)
    return $Url.StartsWith($ExternalBaseUrl, [System.StringComparison]::OrdinalIgnoreCase) -or
           $Url.StartsWith($InternalBaseUrl, [System.StringComparison]::OrdinalIgnoreCase)
}

function Test-Excluded {
    param([string]$Url)
    foreach ($pattern in $ExcludeRegex) {
        if ($Url -match $pattern) {
            return $true
        }
    }
    return $false
}

function Get-SafeFileName {
    param([string]$Url)
    $safe = $Url.ToLowerInvariant()
    $safe = $safe -replace '^https?://', ''
    $safe = $safe -replace '[^a-z0-9]+', '-'
    $safe = $safe.Trim('-')
    if ($safe.Length -eq 0) {
        return 'scan-target'
    }
    return $safe
}

$selectedTargets = @()
foreach ($url in $Allowlist) {
    $trimmed = ([string]$url).Trim()
    if ($trimmed -eq '') {
        continue
    }

    if (-not (Test-AllowedBase -Url $trimmed)) {
        throw "Allowlist entry outside approved base URLs: $trimmed"
    }

    if (Test-Excluded -Url $trimmed) {
        Write-Output "Skipping excluded target: $trimmed"
        continue
    }

    $selectedTargets += $trimmed
}

if ($selectedTargets.Count -eq 0) {
    throw 'No scan targets remain after applying allowlist and excludes.'
}

Write-Output "Using ZAP image: $Image"
Write-Output "Reports directory: $reportsPath"
Write-Output "Rules file: $rulesPath"
Write-Output "Controlled active scan targets: $($selectedTargets.Count)"

$volumeArg = "${repoRoot}:/zap/wrk"
$rulesContainerPath = '/zap/wrk/' + ($RulesFile -replace '\\', '/')
$reportContainerDir = '/zap/wrk/' + ($ReportDir -replace '\\', '/')

foreach ($target in $selectedTargets) {
    $name = Get-SafeFileName -Url $target
    $html = "$reportContainerDir/$name-active.html"
    $json = "$reportContainerDir/$name-active.json"

    Write-Output "Scanning target: $target"
    $dockerCmd = @(
        'run', '--rm', '-t',
        '-v', $volumeArg,
        $Image,
        'zap-full-scan.py',
        '-t', $target,
        '-m', "$SpiderMinutes",
        '-c', $rulesContainerPath,
        '-r', $html,
        '-J', $json
    )

    & docker @dockerCmd
    if ($LASTEXITCODE -ne 0) {
        throw "Controlled active scan failed for $target (exit code $LASTEXITCODE). Ensure target app is running and reachable from Docker. On Windows, start PHP with 0.0.0.0 (not 127.0.0.1)."
    }
}

Write-Output 'Controlled active scans completed successfully.'
Write-Output "Reports generated under: $reportsPath"
