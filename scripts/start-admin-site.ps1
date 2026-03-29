$ErrorActionPreference = 'Stop'

$workspace = Split-Path -Parent $PSScriptRoot
$php = (Get-Command php).Source
$bootstrap = Join-Path $workspace 'scripts\bootstrap-admin-site.php'
$router = Join-Path $workspace 'tests\admin-site\router.php'
$tmpDir = Join-Path $workspace 'tests\tmp'
$pidFile = Join-Path $tmpDir 'admin-site-server.pid'
$infoFile = Join-Path $tmpDir 'admin-site-info.json'
$stdoutFile = Join-Path $tmpDir 'admin-site-server.out.log'
$stderrFile = Join-Path $tmpDir 'admin-site-server.err.log'

if (-not (Test-Path $tmpDir)) {
    New-Item -ItemType Directory -Path $tmpDir | Out-Null
}

if (Test-Path $pidFile) {
    $existingPid = Get-Content $pidFile -ErrorAction SilentlyContinue
    if ($existingPid) {
        $existingProcess = Get-Process -Id $existingPid -ErrorAction SilentlyContinue
        $listener = netstat -ano | Select-String "127.0.0.1:8890" | Select-String "LISTENING" | Select-String ([string]$existingPid)
        if ($existingProcess -and $listener) {
            Write-Host "Admin site already running on http://127.0.0.1:8890 (PID $existingPid)."
            if (Test-Path $infoFile) {
                Get-Content $infoFile
            }
            exit 0
        }
    }
    Remove-Item $pidFile -ErrorAction SilentlyContinue
}

& $php $bootstrap

$process = Start-Process -FilePath $php -ArgumentList '-S', '127.0.0.1:8890', $router -WorkingDirectory $workspace -RedirectStandardOutput $stdoutFile -RedirectStandardError $stderrFile -WindowStyle Hidden -PassThru
Set-Content -Path $pidFile -Value $process.Id

Start-Sleep -Seconds 2

Write-Host "Admin site started on http://127.0.0.1:8890"
Write-Host "Login: admin / password"
if (Test-Path $infoFile) {
    Write-Host ""
    Get-Content $infoFile
}
