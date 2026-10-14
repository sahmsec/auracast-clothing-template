<?php
require __DIR__ . '/deployment-lib.php';
function ac_test($name, $callback, $refused = false) {
    try { $callback(); if ($refused) throw new LogicException('Expected refusal was not raised.'); }
    catch (Throwable $error) { if (!$refused || $error instanceof LogicException) throw $error; }
    echo 'PASS ' . $name . "\n";
}
$temporary = sys_get_temp_dir() . '/auracast-deploy-test-' . bin2hex(random_bytes(8));
mkdir($temporary, 0700);
function ac_test_clean($path) {
    if (is_link($path) || is_file($path)) { unlink($path); return; }
    foreach (new FilesystemIterator($path) as $item) ac_test_clean($item->getPathname());
    rmdir($path);
}
try {
    $private = $temporary . '/private'; $wordpress = $temporary . '/wordpress';
    mkdir($private . '/backups', 0700, true); mkdir($wordpress . '/wp-content/themes/auracast', 0755, true);
    foreach (['deployment-lib.php', 'github-deploy-receiver.php'] as $file) copy(__DIR__ . '/' . $file, $private . '/' . $file);
    file_put_contents($wordpress . '/wp-config.php', 'Store configuration sentinel');
    file_put_contents($wordpress . '/wp-load.php', '<?php throw new RuntimeException("Never load the fixture store");');
    file_put_contents($wordpress . '/orders-sentinel.txt', 'Orders must remain unchanged');
    mkdir($wordpress . '/wp-content/uploads'); file_put_contents($wordpress . '/wp-content/uploads/photo.jpg', 'Owner upload sentinel');
    file_put_contents($wordpress . '/wp-content/themes/auracast/style.css', "/*\nVersion: 1.1.1\n*/");
    file_put_contents($wordpress . '/wp-content/themes/auracast/previous.txt', 'Original theme');
    $wp_cli = $private . '/wp-cli.php';
    file_put_contents($wp_cli, '<?php $path = substr(array_values(array_filter($argv, fn($a)=>str_starts_with($a,"--path=")))[0],7); preg_match("/Version: ([0-9.]+)/",file_get_contents($path."/wp-content/themes/auracast/style.css"),$m); $version=$m[1]; if($version===getenv("AC_TEST_FAIL_VERSION"))exit(1); echo json_encode(["url"=>"https://store.example.invalid","theme"=>"auracast","version"=>$version,"woocommerce"=>true]);');
    $config = ['wordpress_root' => $wordpress, 'private_root' => $private, 'backup_root' => $private . '/backups', 'wp_cli' => $wp_cli, 'expected_url' => 'https://store.example.invalid', 'settle_seconds' => 0];
    file_put_contents($private . '/config.php', '<?php return ' . var_export($config, true) . ';');
    $make = function ($version = '1.2.2', $extra = null) use ($temporary) {
        $files = ['style.css' => "/*\nTheme Name: Auracast\nVersion: $version\n*/", 'functions.php' => '<?php // fixture', 'front-page.php' => '<?php // fixture'];
        if ($extra) $files[$extra[0]] = $extra[1];
        $archive = $temporary . '/fixture-' . bin2hex(random_bytes(4)) . '.zip';
        $zip = new ZipArchive(); $zip->open($archive, ZipArchive::CREATE);
        $metadata = [];
        foreach ($files as $name => $bytes) { $zip->addFromString('auracast/' . $name, $bytes); $metadata[$name] = ['sha256' => hash('sha256', $bytes), 'size' => strlen($bytes)]; }
        $zip->close();
        return ['schema' => 1, 'theme' => 'auracast', 'version' => $version, 'commit' => str_repeat($version === '1.2.2' ? 'a' : 'b', 40), 'zip_sha256' => hash_file('sha256', $archive), 'zip_size' => filesize($archive), 'files' => $metadata, '_archive' => $archive];
    };
    $release = $make();
    $command = ac_command('auracast-deploy ' . $release['commit'] . ' ' . $release['zip_sha256']);
    ac_test('Fixed SSH release commands are accepted', fn() => ac_command('auracast-plan ' . $release['commit'] . ' ' . $release['zip_sha256']));
    foreach (['whoami', 'auracast-deploy main anything', 'auracast-deploy ' . $release['commit'] . ' ' . $release['zip_sha256'] . '; whoami'] as $value) ac_test('Arbitrary SSH command is refused', fn() => ac_command($value), true);
    ac_test('Manifest identifies the exact release', fn() => ac_header(json_encode($release), $command));
    foreach (['../wp-config.php', '/wp-config.php', 'assets/images/../../wp-config.php', 'assets/images/.env', 'assets/images/shell.php', 'plugins/plugin.php'] as $value) {
        $changed = $release; $changed['files'][$value] = ['size' => 0, 'sha256' => hash('sha256', '')];
        ac_test('Unsafe release file is refused: ' . $value, fn() => ac_header(json_encode($changed), $command), true);
    }
    $changed = $release; $changed['commit'] = str_repeat('c', 40);
    ac_test('A different commit is refused', fn() => ac_header(json_encode($changed), $command), true);
    $changed = $release; $changed['zip_size'] = 128 * 1024 * 1024 + 1;
    ac_test('Oversized archives are refused', fn() => ac_header(json_encode($changed), $command), true);
    $changed = $release; $changed['files']['style.css']['sha256'] = str_repeat('0', 64);
    mkdir($temporary . '/checksum-stage');
    ac_test('Changed ZIP content is refused', fn() => ac_extract($release['_archive'], $changed, $temporary . '/checksum-stage'), true);
    foreach (['foreign-root', 'path-traversal', 'unlisted-entry', 'symlink-entry'] as $case) {
        $malformed = $make();
        $zip = new ZipArchive(); $zip->open($malformed['_archive']);
        if ($case === 'foreign-root') $zip->renameName('auracast/front-page.php', 'other/front-page.php');
        if ($case === 'path-traversal') $zip->renameName('auracast/front-page.php', 'auracast/../wp-config.php');
        if ($case === 'unlisted-entry') $zip->addFromString('auracast/unlisted.php', 'Unexpected file');
        if ($case === 'symlink-entry') $zip->setExternalAttributesName('auracast/functions.php', ZipArchive::OPSYS_UNIX, 0120777 << 16);
        $zip->close();
        clearstatcache(true, $malformed['_archive']);
        $malformed['zip_sha256'] = hash_file('sha256', $malformed['_archive']);
        $malformed['zip_size'] = filesize($malformed['_archive']);
        $stage = $temporary . '/' . $case; mkdir($stage);
        ac_test('Unsafe ZIP is refused: ' . $case, fn() => ac_extract($malformed['_archive'], $malformed, $stage), true);
    }
    $invoke = function ($release, $operation, $failed_version = '') use ($private) {
        $header = $release; unset($header['_archive']); $bytes = json_encode($header);
        $environment = array_merge(getenv(), ['SSH_ORIGINAL_COMMAND' => 'auracast-' . $operation . ' ' . $release['commit'] . ' ' . $release['zip_sha256'], 'AC_TEST_FAIL_VERSION' => $failed_version]);
        $process = proc_open([PHP_BINARY, $private . '/github-deploy-receiver.php'], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, null, $environment);
        if ($operation !== 'status') fwrite($pipes[0], "AURACAST1\n" . strlen($bytes) . "\n" . $bytes . file_get_contents($release['_archive']));
        fclose($pipes[0]); $output = stream_get_contents($pipes[1]) . stream_get_contents($pipes[2]); fclose($pipes[1]); fclose($pipes[2]);
        return [proc_close($process), $output];
    };
    [$status, $output] = $invoke($release, 'plan');
    ac_test('Hosting plan leaves the current theme and maintenance untouched', fn() => ac_require($status === 0 && is_file($wordpress . '/wp-content/themes/auracast/previous.txt') && !file_exists($wordpress . '/.maintenance'), $output));
    file_put_contents($wordpress . '/.maintenance', 'Another updater owns this marker');
    [$status] = $invoke($release, 'deploy');
    ac_test('Existing maintenance is preserved', fn() => ac_require($status === 1 && file_get_contents($wordpress . '/.maintenance') === 'Another updater owns this marker', 'Foreign maintenance changed'));
    unlink($wordpress . '/.maintenance');
    [$status, $output] = $invoke($release, 'deploy');
    ac_test('Actual receiver installs the verified theme and keeps its backup', fn() => ac_require($status === 0 && !file_exists($wordpress . '/.maintenance') && count(glob($private . '/backups/previous-*')) === 1, $output));
    [$status] = $invoke($release, 'status');
    ac_test('Installed revision passes the native status check', fn() => ac_require($status === 0, 'Wrong installed release'));
    [$status, $output] = $invoke($make('1.2.3'), 'deploy', '1.2.3');
    ac_test('A failed native check restores the previous theme', function () use ($status, $output, $wordpress) {
        ac_require($status === 1 && str_contains(file_get_contents($wordpress . '/wp-content/themes/auracast/style.css'), '1.2.2') && !file_exists($wordpress . '/.maintenance'), $output);
    });
    ac_test('Store configuration, orders and uploaded photographs are preserved', fn() => ac_require(file_get_contents($wordpress . '/wp-config.php') === 'Store configuration sentinel' && file_get_contents($wordpress . '/orders-sentinel.txt') === 'Orders must remain unchanged' && file_get_contents($wordpress . '/wp-content/uploads/photo.jpg') === 'Owner upload sentinel', 'Store data changed'));
    ac_test('Temporary archives and staging files are cleaned within their private roots', fn() => ac_require(count(glob($private . '/received-*.zip')) === 0 && count(glob($private . '/backups/incoming-*')) === 0, 'Temporary release data remains'));
} finally {
    $resolved = realpath($temporary);
    ac_require($resolved && dirname($resolved) === realpath(sys_get_temp_dir()) && preg_match('/^auracast-deploy-test-[a-f0-9]{16}$/D', basename($resolved)), 'Unsafe test cleanup target.');
    ac_test_clean($resolved);
}
