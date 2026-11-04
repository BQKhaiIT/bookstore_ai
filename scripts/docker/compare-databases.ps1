# Compares row counts and constraint counts. Prints aggregates only, never row contents.
$ErrorActionPreference = 'Stop'
$Root = Resolve-Path (Join-Path $PSScriptRoot '..\..')
$Psql = 'C:\Program Files\PostgreSQL\18\bin\psql.exe'

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

$source = Read-DotEnv (Join-Path $Root 'backend\.env')
$identity = Read-DotEnv (Join-Path $Root 'docker\secrets\postgres.env')
$dockerPassword = [System.IO.File]::ReadAllText((Join-Path $Root 'docker\secrets\db_password')).Trim()

$sql = @'
SELECT 'tables' AS metric, count(*)::text AS value
FROM information_schema.tables
WHERE table_schema = 'public' AND table_type = 'BASE TABLE'
UNION ALL
SELECT 'foreign_keys', count(*)::text
FROM pg_constraint
WHERE contype = 'f'
UNION ALL
SELECT 'users', count(*)::text FROM users
UNION ALL
SELECT 'users_super_admin', count(*)::text FROM users WHERE role = 'super_admin'
UNION ALL
SELECT 'books', count(*)::text FROM books
UNION ALL
SELECT 'orders', count(*)::text FROM orders
UNION ALL
SELECT 'payments', count(*)::text FROM payments
UNION ALL
SELECT 'reviews', count(*)::text FROM reviews
ORDER BY 1;
'@

function Invoke-Count([string]$Label, [string]$HostName, [string]$Port, [string]$User, [string]$Database, [string]$Password) {
    $env:PGPASSWORD = $Password
    try {
        Write-Output "=== $Label ${HostName}:${Port}/$Database ==="
        & $Psql -h $HostName -p $Port -U $User -d $Database -v ON_ERROR_STOP=1 -c $sql
        if ($LASTEXITCODE -ne 0) { throw "$Label query failed" }
    }
    finally {
        Remove-Item Env:PGPASSWORD -ErrorAction SilentlyContinue
    }
}

Invoke-Count 'windows' '127.0.0.1' $source['DB_PORT'] $source['DB_USERNAME'] $source['DB_DATABASE'] $source['DB_PASSWORD']
Invoke-Count 'docker' '127.0.0.1' '5433' $identity['POSTGRES_USER'] $identity['POSTGRES_DB'] $dockerPassword
