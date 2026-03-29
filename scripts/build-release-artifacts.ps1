$ErrorActionPreference = 'Stop'

$root = Split-Path -Parent $PSScriptRoot
$dist = Join-Path $root 'dist'
$pluginBuild = Join-Path $dist 'plugin-build'
$githubBuild = Join-Path $dist 'github-build'
$pluginDir = Join-Path $pluginBuild 'universal-cpt-migrator'
$githubDir = Join-Path $githubBuild 'universal-cpt-migrator'
$pluginZip = Join-Path $dist 'universal-cpt-migrator-wordpress-install.zip'
$githubZip = Join-Path $dist 'universal-cpt-migrator-github-source.zip'

if (Test-Path $dist) {
    Remove-Item $dist -Recurse -Force
}

New-Item -ItemType Directory -Path $pluginDir | Out-Null
New-Item -ItemType Directory -Path $githubDir | Out-Null

$pluginItems = @(
    'assets',
    'docs',
    'src',
    'templates',
    'README.md',
    'uninstall.php',
    'universal-cpt-migrator.php'
)

foreach ($item in $pluginItems) {
    Copy-Item -Path (Join-Path $root $item) -Destination $pluginDir -Recurse -Force
}

$githubItems = @(
    'assets',
    'docs',
    'scripts',
    'src',
    'templates',
    'tests',
    'composer.json',
    'composer.lock',
    'package.json',
    'package-lock.json',
    'phpunit.xml.dist',
    'playwright.config.js',
    'README.md',
    'uninstall.php',
    'universal-cpt-migrator.php'
)

foreach ($item in $githubItems) {
    Copy-Item -Path (Join-Path $root $item) -Destination $githubDir -Recurse -Force
}

$pathsToRemove = @(
    (Join-Path $githubDir 'tests\tmp'),
    (Join-Path $githubDir 'tests\admin-site\wp-content\uploads'),
    (Join-Path $githubDir 'tests\browser-site\wp-content\uploads'),
    (Join-Path $githubDir 'tests\wp-content\upgrade'),
    (Join-Path $githubDir 'tests\browser-site\wp-content\database'),
    (Join-Path $githubDir 'tests\admin-site\wp-content\database')
)

foreach ($path in $pathsToRemove) {
    if (Test-Path $path) {
        Remove-Item $path -Recurse -Force
    }
}

Compress-Archive -Path (Join-Path $pluginBuild 'universal-cpt-migrator') -DestinationPath $pluginZip -Force
Compress-Archive -Path (Join-Path $githubBuild 'universal-cpt-migrator') -DestinationPath $githubZip -Force

Get-Item $pluginZip, $githubZip | Select-Object FullName, Length, LastWriteTime
