# Consistent custom-format backup of the Windows PostgreSQL database.
# Does not stop PostgreSQL and does not modify the source database.
$ErrorActionPreference = 'Stop'
$Root = Resolve-Path (Join-Path $PSScriptRoot '..\..')
$DumpBin = 'C:\Program Files\PostgreSQL\18\bin'
$PgDump = Join-Path $DumpBin 'pg_dump.exe'
$PgRestore = Join-Path $DumpBin 'pg_restore.exe'
$Psql = Join-Path $DumpBin 'psql.exe'

function Read-DotEnv([string]$Path) {
    $map = @{}
    foreach ($raw in [System.IO.File]::ReadAllLines($Path)) {
        $line = $raw.Trim()
        if ($line.Length -eq 0 -or $line.StartsWith('#')) { continue }
        $i = $line.IndexOf('=')
        if ($i -lt 1) { continue }
        $key = $line.Substring(0, $i).Trim()
        $value = $line.Substring($i + 1).Trim()
        if (($value.StartsWith('"') -and $value.EndsWith('"')) -or ($value.StartsWith("'") -and $value.EndsWith("'"))) {
            $value = $value.Substring(1, $value.Length - 2)
        }
        $map[$key] = $value
    }
    return $map
}

$envMap = Read-DotEnv (Join-Path $Root 'backend\.env')
$backupDir = Join-Path $Root 'backups\postgres'
New-Item -ItemType Directory -Force -Path $backupDir | Out-Null
$stamp = Get-Date -Format 'yyyyMMdd-HHmmss'
$outFile = Join-Path $backupDir "bookstore_ai-$stamp.dump"

$env:PGPASSWORD = $envMap['DB_PASSWORD']
try {
    & $Psql -h 127.0.0.1 -p $envMap['DB_PORT'] -U $envMap['DB_USERNAME'] -d $envMap['DB_DATABASE'] -v ON_ERROR_STOP=1 -c "SELECT current_database(), current_setting('server_version');"
    if ($LASTEXITCODE -ne 0) { throw "psql preflight failed: $LASTEXITCODE" }

    & $PgDump -h 127.0.0.1 -p $envMap['DB_PORT'] -U $envMap['DB_USERNAME'] -d $envMap['DB_DATABASE'] -Fc --no-owner --no-acl -f $outFile
    if ($LASTEXITCODE -ne 0) { throw "pg_dump failed: $LASTEXITCODE" }
}
finally {
    Remove-Item Env:PGPASSWORD -ErrorAction SilentlyContinue
}

$item = Get-Item $outFile
if ($item.Length -lt 1024) {
    throw "Backup file is too small: $($item.Length) bytes"
}

& $PgRestore --list $outFile | Select-Object -First 40
if ($LASTEXITCODE -ne 0) { throw "pg_restore --list failed: $LASTEXITCODE" }

Write-Output "BACKUP_OK path=$outFile bytes=$($item.Length)"
