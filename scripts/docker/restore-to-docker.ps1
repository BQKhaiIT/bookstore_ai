# Restores a custom-format backup into PostgreSQL on 127.0.0.1:5433 only.
# Refuses port 5432 so the Windows database cannot be overwritten.
param(
    [Parameter(Mandatory = $true)]
    [string]$DumpFile
)

$ErrorActionPreference = 'Stop'
if (-not (Test-Path $DumpFile)) { throw "Dump file not found." }

$TargetHost = '127.0.0.1'
$TargetPort = 5433
if ($TargetPort -eq 5432) { throw 'Refusing to restore onto port 5432.' }

$Root = Resolve-Path (Join-Path $PSScriptRoot '..\..')
$PgRestore = 'C:\Program Files\PostgreSQL\18\bin\pg_restore.exe'
$Psql = 'C:\Program Files\PostgreSQL\18\bin\psql.exe'
$Secret = Join-Path $Root 'docker\secrets\db_password'
$Identity = Join-Path $Root 'docker\secrets\postgres.env'
if (-not (Test-Path $Secret) -or -not (Test-Path $Identity)) {
    throw 'Missing docker/secrets. Run scripts/docker/sync-secrets.ps1 first.'
}

$identityMap = @{}
foreach ($raw in [System.IO.File]::ReadAllLines($Identity)) {
    $line = $raw.Trim()
    if ($line.Length -eq 0 -or $line.StartsWith('#')) { continue }
    $i = $line.IndexOf('=')
    if ($i -gt 0) { $identityMap[$line.Substring(0, $i)] = $line.Substring($i + 1) }
}

$env:PGPASSWORD = [System.IO.File]::ReadAllText($Secret).Trim()
try {
    $probe = & $Psql -h $TargetHost -p $TargetPort -U $identityMap['POSTGRES_USER'] -d $identityMap['POSTGRES_DB'] -v ON_ERROR_STOP=1 -At -c "SELECT current_setting('server_version'); SELECT to_regclass('public.users');"
    if ($LASTEXITCODE -ne 0) { throw "Cannot connect to Docker PostgreSQL on ${TargetHost}:${TargetPort}" }
    Write-Output $probe
    if ($probe -match 'users') {
        throw 'Target already contains public.users. Refusing to restore over existing Docker data.'
    }

    & $PgRestore -h $TargetHost -p $TargetPort -U $identityMap['POSTGRES_USER'] -d $identityMap['POSTGRES_DB'] --no-owner --no-acl --exit-on-error $DumpFile
    if ($LASTEXITCODE -ne 0) { throw "pg_restore failed: $LASTEXITCODE" }
}
finally {
    Remove-Item Env:PGPASSWORD -ErrorAction SilentlyContinue
}

Write-Output "RESTORE_OK port=$TargetPort"
