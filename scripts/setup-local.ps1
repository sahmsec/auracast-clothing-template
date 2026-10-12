$ErrorActionPreference = 'Stop'
$projectRoot = [IO.Path]::GetFullPath((Join-Path $PSScriptRoot '..'))
Set-Location -LiteralPath $projectRoot
$localRoot = Join-Path $projectRoot '.local'
New-Item -ItemType Directory -Path $localRoot -Force | Out-Null
$items = @(
    @{Url='https://downloads.php.net/~windows/releases/archives/php-8.5.11-nts-Win32-vs17-x64.zip';Name='php'},
    @{Url='https://wordpress.org/latest.zip';Name='wordpress'},
    @{Url='https://downloads.wordpress.org/plugin/woocommerce.latest-stable.zip';Name='woocommerce'},
    @{Url='https://downloads.wordpress.org/plugin/sqlite-database-integration.latest-stable.zip';Name='sqlite'}
)
foreach ($item in $items) {
    $destination = Join-Path $localRoot $item.Name
    if (-not (Test-Path -LiteralPath $destination)) {
        $archive = Join-Path $localRoot ($item.Name + '.zip')
        Invoke-WebRequest -Uri $item.Url -OutFile $archive -UseBasicParsing
        Expand-Archive -LiteralPath $archive -DestinationPath $destination -Force
    }
}
$wordpressRoot = Join-Path $localRoot 'wordpress\wordpress'
$qaPluginsRoot = Join-Path $wordpressRoot 'wp-content\mu-plugins'
New-Item -ItemType Directory -Path $qaPluginsRoot -Force | Out-Null
Copy-Item -LiteralPath (Join-Path $PSScriptRoot 'local-qa.php') -Destination (Join-Path $qaPluginsRoot 'auracast-local-qa.php') -Force
$pluginsRoot = Join-Path $wordpressRoot 'wp-content\plugins'
foreach ($name in @('woocommerce','sqlite-database-integration')) {
    if (-not (Test-Path -LiteralPath (Join-Path $pluginsRoot $name))) {
        $source = if ($name -eq 'woocommerce') { Join-Path $localRoot 'woocommerce\woocommerce' } else { Join-Path $localRoot 'sqlite\sqlite-database-integration' }
        Copy-Item -LiteralPath $source -Destination $pluginsRoot -Recurse
    }
}
Copy-Item -LiteralPath (Join-Path $pluginsRoot 'sqlite-database-integration\db.copy') -Destination (Join-Path $wordpressRoot 'wp-content\db.php') -Force
Copy-Item -LiteralPath (Join-Path $projectRoot 'auracast') -Destination (Join-Path $wordpressRoot 'wp-content\themes') -Recurse -Force
$phpRoot = Join-Path $localRoot 'php'
$extensionDirectory = (Join-Path $phpRoot 'ext').Replace('\','/')
$phpIni = @"
extension_dir="$extensionDirectory"
extension=curl
extension=gd
extension=mbstring
extension=openssl
extension=pdo_sqlite
extension=sqlite3
extension=zip
memory_limit=512M
upload_max_filesize=128M
post_max_size=160M
max_execution_time=180
display_errors=Off
log_errors=On
error_log="$($localRoot.Replace('\','/'))/php-errors.log"
date.timezone=Asia/Dhaka
"@
[IO.File]::WriteAllText((Join-Path $phpRoot 'php.ini'),$phpIni)
$configPath = Join-Path $wordpressRoot 'wp-config.php'
if (-not (Test-Path -LiteralPath $configPath)) {
    $config = @'
<?php
define('DB_NAME','auracast');define('DB_USER','local');define('DB_PASSWORD','');define('DB_HOST','localhost');
define('DB_CHARSET','utf8');define('DB_COLLATE','');define('DB_ENGINE','sqlite');
define('DB_PATH',__DIR__.'/wp-content/database/auracast-streetwear.sqlite');
define('WP_HOME','http://127.0.0.1:8088');define('WP_SITEURL','http://127.0.0.1:8088');
define('WP_DEBUG',true);define('WP_DEBUG_DISPLAY',false);define('WP_DEBUG_LOG',true);
define('DISABLE_WP_CRON',true);define('WP_ENVIRONMENT_TYPE','local');
define('WP_HTTP_BLOCK_EXTERNAL',true);
define('WP_MEMORY_LIMIT','256M');define('FS_METHOD','direct');
$table_prefix='wp_';
if(!defined('ABSPATH'))define('ABSPATH',__DIR__.'/');
require_once ABSPATH.'wp-settings.php';
'@
    [IO.File]::WriteAllText($configPath,$config)
}
& (Join-Path $phpRoot 'php.exe') -c (Join-Path $phpRoot 'php.ini') (Join-Path $PSScriptRoot 'bootstrap-local.php')
if ($LASTEXITCODE -ne 0) { throw 'WordPress initialization failed.' }
& (Join-Path $phpRoot 'php.exe') -c (Join-Path $phpRoot 'php.ini') (Join-Path $PSScriptRoot 'seed-local.php')
if ($LASTEXITCODE -ne 0) { throw 'Demo import failed.' }
Write-Output 'Run npm.cmd run preview to serve the local WooCommerce store at http://127.0.0.1:8088'
