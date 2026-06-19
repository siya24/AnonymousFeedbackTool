param(
    [string]$Instance = "(localdb)\MSSQLLocalDB",
    [string]$DatabaseName = "anonymous_feedback_tool",
    [string]$SqlFile = "AnonymousFeedbackTool_Internal_SQLServer.sql"
)

$ErrorActionPreference = 'Stop'

$repoRoot = Resolve-Path (Join-Path $PSScriptRoot '..')
$sqlPath = Join-Path $repoRoot $SqlFile
if (-not (Test-Path -Path $sqlPath -PathType Leaf)) {
    throw "Missing SQL file: $sqlPath"
}

Add-Type -AssemblyName System.Data

function Invoke-SqlBatch {
    param(
        [string]$ConnectionString,
        [string]$Sql
    )

    $query = ($Sql | Out-String).Trim()
    if ($query -eq '') {
        return
    }

    $conn = New-Object System.Data.SqlClient.SqlConnection $ConnectionString
    try {
        $cmd = $conn.CreateCommand()
        $cmd.CommandTimeout = 0
        $cmd.CommandText = $query
        $conn.Open()
        [void]$cmd.ExecuteNonQuery()
    }
    finally {
        if ($conn.State -ne [System.Data.ConnectionState]::Closed) {
            $conn.Close()
        }
        $conn.Dispose()
    }
}

$masterConn = "Server=$Instance;Integrated Security=true;Initial Catalog=master;TrustServerCertificate=True;"
Invoke-SqlBatch -ConnectionString $masterConn -Sql @"
IF DB_ID(N'$DatabaseName') IS NOT NULL
BEGIN
    ALTER DATABASE [$DatabaseName] SET SINGLE_USER WITH ROLLBACK IMMEDIATE;
    DROP DATABASE [$DatabaseName];
END;
CREATE DATABASE [$DatabaseName];
"@

$content = Get-Content -Raw -Path $sqlPath
$content = [regex]::Replace($content, '(?im)^\s*USE\s+\[?anonymous_feedback_tool\]?\s*;\s*$', '')
$batches = [regex]::Split($content, '(?im)^\s*GO\s*$(\r?\n)?')

$dbConn = "Server=$Instance;Integrated Security=true;Initial Catalog=$DatabaseName;TrustServerCertificate=True;"
$executed = 0
foreach ($batch in $batches) {
    $trimmedBatch = ($batch | Out-String).Trim()
    if ($trimmedBatch -eq '') {
        continue
    }

    Invoke-SqlBatch -ConnectionString $dbConn -Sql $trimmedBatch
    $executed++
}

$verifyConn = New-Object System.Data.SqlClient.SqlConnection $dbConn
try {
    $verifyCmd = $verifyConn.CreateCommand()
    $verifyCmd.CommandText = @"
SELECT
    (SELECT COUNT(*) FROM dbo.users) AS users_count,
    (SELECT COUNT(*) FROM dbo.categories) AS categories_count,
    (SELECT COUNT(*) FROM dbo.statuses) AS statuses_count,
    (SELECT COUNT(*) FROM dbo.stages) AS stages_count;
"@
    $verifyConn.Open()
    $reader = $verifyCmd.ExecuteReader()
    if ($reader.Read()) {
        Write-Output "LocalDB migration completed. Batches=$executed users=$($reader['users_count']) categories=$($reader['categories_count']) statuses=$($reader['statuses_count']) stages=$($reader['stages_count'])"
    }
    $reader.Close()
}
finally {
    if ($verifyConn.State -ne [System.Data.ConnectionState]::Closed) {
        $verifyConn.Close()
    }
    $verifyConn.Dispose()
}
