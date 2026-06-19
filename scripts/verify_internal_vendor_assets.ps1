param(
    [string]$ManifestPath = "internal/public/assets/vendor/checksums.sha256"
)

$ErrorActionPreference = 'Stop'

$repoRoot = Resolve-Path (Join-Path $PSScriptRoot '..')
$manifestFullPath = Join-Path $repoRoot $ManifestPath
$vendorRoot = Join-Path $repoRoot 'internal/public/assets/vendor'

if (-not (Test-Path -Path $manifestFullPath -PathType Leaf)) {
    throw "Checksum manifest not found: $manifestFullPath"
}

if (-not (Test-Path -Path $vendorRoot -PathType Container)) {
    throw "Vendor asset directory not found: $vendorRoot"
}

$verificationIssues = New-Object System.Collections.Generic.List[string]
$lines = Get-Content -Path $manifestFullPath

foreach ($line in $lines) {
    $trimmed = $line.Trim()
    if ($trimmed -eq '' -or $trimmed.StartsWith('#')) {
        continue
    }

    $match = [regex]::Match($trimmed, '^([a-fA-F0-9]{64})\s+(.+)$')
    if (-not $match.Success) {
        $verificationIssues.Add("Invalid checksum line format: $line")
        continue
    }

    $expected = $match.Groups[1].Value.ToLowerInvariant()
    $relativePath = $match.Groups[2].Value.Trim()
    $relativePathOs = $relativePath.Replace('/', [System.IO.Path]::DirectorySeparatorChar)
    $targetPath = Join-Path $vendorRoot $relativePathOs

    if (-not (Test-Path -Path $targetPath -PathType Leaf)) {
        $verificationIssues.Add("Missing asset file: $relativePath")
        continue
    }

    $actual = (Get-FileHash -Algorithm SHA256 -Path $targetPath).Hash.ToLowerInvariant()
    if ($actual -ne $expected) {
        $verificationIssues.Add("Hash mismatch for $relativePath (expected: $expected, actual: $actual)")
    }
}

if ($verificationIssues.Count -gt 0) {
    $details = [string]::Join([Environment]::NewLine, $verificationIssues)
    throw "Vendor asset checksum verification failed with $($verificationIssues.Count) issue(s).$([Environment]::NewLine)$details"
}

Write-Output 'Vendor asset checksum verification passed.'
