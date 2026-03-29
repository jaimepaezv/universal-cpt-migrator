$ErrorActionPreference = 'Stop'

$workspace = Split-Path -Parent $PSScriptRoot
$pidFile = Join-Path $workspace 'tests\tmp\admin-site-server.pid'

if (-not (Test-Path $pidFile)) {
    Write-Host 'No running admin site was found.'
    exit 0
}

$serverPid = Get-Content $pidFile -ErrorAction SilentlyContinue
if ($serverPid) {
    $process = Get-Process -Id $serverPid -ErrorAction SilentlyContinue
    if ($process) {
        Stop-Process -Id $serverPid -Force
        Wait-Process -Id $serverPid -Timeout 5 -ErrorAction SilentlyContinue
        Write-Host "Stopped admin site process $serverPid."
    } else {
        Write-Host "PID file existed but process $serverPid was not running."
    }
}

Remove-Item $pidFile -ErrorAction SilentlyContinue
