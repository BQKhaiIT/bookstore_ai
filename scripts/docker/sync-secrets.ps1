# Builds gitignored Docker secret files from the local Laravel .env and ngrok config.
# Prints lengths and key names only. Never prints secret values.
$ErrorActionPreference = 'Stop'
$Root = Resolve-Path (Join-Path $PSScriptRoot '..\..')
$EnvPath = Join-Path $Root 'backend\.env'
$Secrets = Join-Path $Root 'docker\secrets'
New-Item -ItemType Directory -Force -Path $Secrets | Out-Null

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

function Write-Utf8([string]$Path, [string]$Text) {
    $utf8 = New-Object System.Text.UTF8Encoding $false
    [System.IO.File]::WriteAllText($Path, $Text.Replace("`r`n", "`n").Replace("`r", "`n"), $utf8)
}

if (-not (Test-Path $EnvPath)) {
    throw "Missing backend/.env"
}

$envMap = Read-DotEnv $EnvPath
foreach ($key in @('DB_DATABASE', 'DB_USERNAME', 'DB_PASSWORD', 'APP_KEY', 'SEPAY_ENV', 'SEPAY_PUBLIC_BASE_URL')) {
    if (-not $envMap.ContainsKey($key) -or [string]::IsNullOrWhiteSpace($envMap[$key])) {
        throw "backend/.env is missing $key"
    }
}

if ($envMap['SEPAY_ENV'] -ne 'sandbox') {
    throw "Refusing to sync: SEPAY_ENV must stay sandbox."
}

$expectedOrigin = 'https://squishy-showcase-lapped.ngrok-free.dev'
if ($envMap['SEPAY_PUBLIC_BASE_URL'].TrimEnd('/') -ne $expectedOrigin) {
    throw "SEPAY_PUBLIC_BASE_URL is not the fixed ngrok origin."
}

$dbName = $envMap['DB_DATABASE']
$dbUser = $envMap['DB_USERNAME']
Write-Utf8 (Join-Path $Secrets 'postgres.env') "POSTGRES_DB=$dbName`nPOSTGRES_USER=$dbUser`n"
Write-Utf8 (Join-Path $Secrets 'db_password') $envMap['DB_PASSWORD']

$ngrokCandidates = @(
    (Join-Path $env:LOCALAPPDATA 'ngrok\ngrok.yml'),
    (Join-Path $env:USERPROFILE '.ngrok2\ngrok.yml'),
    (Join-Path $env:APPDATA 'ngrok\ngrok.yml')
)
$token = $null
foreach ($candidate in $ngrokCandidates) {
    if (-not (Test-Path $candidate)) { continue }
    $content = [System.IO.File]::ReadAllText($candidate)
    $match = [regex]::Match($content, '(?m)^\s*authtoken:\s*[''"]?([^''"#\r\n]+)[''"]?\s*$')
    if ($match.Success) {
        $token = $match.Groups[1].Value.Trim()
        break
    }
}
if ([string]::IsNullOrWhiteSpace($token)) {
    throw "ngrok authtoken was not found in the local ngrok config."
}
$token = $token.Replace('$', '$$')
Write-Utf8 (Join-Path $Secrets 'ngrok.env') "NGROK_AUTHTOKEN=$token`n"

Write-Output "synced postgres.env user=$dbUser db=$dbName password_length=$($envMap['DB_PASSWORD'].Length)"
Write-Output "synced ngrok.env token_length=$($token.Replace('$$','$').Length)"
Write-Output "app_key_length=$($envMap['APP_KEY'].Length)"
Write-Output "sepay_env=$($envMap['SEPAY_ENV'])"
Write-Output "sepay_public_base_url=$expectedOrigin"
Write-Output "app_url=$($envMap['APP_URL'])"
Write-Output "frontend_url=$($envMap['FRONTEND_URL'])"
Write-Output "db_host_in_env_file=$($envMap['DB_HOST']):$($envMap['DB_PORT'])"
Write-Output "mail_mailer=$($envMap['MAIL_MAILER'])"
Write-Output "cloudinary_cloud_set=$([bool]$envMap['CLOUDINARY_CLOUD_NAME'])"
Write-Output "secret_key_set=$([bool]$envMap['SEPAY_SECRET_KEY']) merchant_set=$([bool]$envMap['SEPAY_MERCHANT_ID'])"
