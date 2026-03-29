$ErrorActionPreference = 'Stop'

$workspace = Split-Path -Parent $PSScriptRoot
$infoFile = Join-Path $workspace 'tests\tmp\admin-site-info.json'

if (-not (Test-Path $infoFile)) {
    Write-Host 'Admin site info not found. Run .\scripts\start-admin-site.ps1 first.'
    exit 1
}

Get-Content $infoFile
