$ErrorActionPreference = 'Stop'
$Root = Resolve-Path (Join-Path $PSScriptRoot '..\..')
Set-Location $Root
docker compose ps
Write-Output '--- backend /up ---'
try {
    $up = Invoke-WebRequest -Uri 'http://127.0.0.1:8000/up' -UseBasicParsing -TimeoutSec 15
    Write-Output "backend_up status=$($up.StatusCode)"
} catch {
    Write-Output "backend_up FAIL $($_.Exception.Message)"
    exit 1
}
Write-Output '--- frontend / ---'
try {
    $fe = Invoke-WebRequest -Uri 'http://127.0.0.1:5173/' -UseBasicParsing -TimeoutSec 15
    Write-Output "frontend status=$($fe.StatusCode) bytes=$($fe.RawContentLength)"
} catch {
    Write-Output "frontend FAIL $($_.Exception.Message)"
    exit 1
}
