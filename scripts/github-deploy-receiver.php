<?php
/** Private CLI receiver. Its dedicated SSH key must use this as a forced command. */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require __DIR__ . '/deployment-lib.php';
function ac_run($arguments) {
    $process = proc_open($arguments, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
    ac_require(is_resource($process), 'Cannot start release verification.');
    $output = stream_get_contents($pipes[1]); $errors = stream_get_contents($pipes[2]);
    fclose($pipes[1]); fclose($pipes[2]); $status = proc_close($process);
    if ($status !== 0) {
        file_put_contents(__DIR__ . '/release-private.log', $output . $errors, FILE_APPEND);
        chmod(__DIR__ . '/release-private.log', 0600);
        throw new RuntimeException('Release verification failed; inspect the private hosting log.');
    }
    return $output;
}
function ac_native($config, $version = null, $purge = false) {
    $code = 'echo json_encode(["url"=>untrailingslashit(home_url()),"theme"=>get_stylesheet(),"version"=>wp_get_theme()->get("Version"),"woocommerce"=>class_exists("WooCommerce")]);';
    if ($purge) $code = 'if(function_exists("wp_cache_clear_cache"))wp_cache_clear_cache();if(function_exists("rocket_clean_domain"))rocket_clean_domain();do_action("litespeed_purge_all");wp_cache_flush();' . $code;
    $result = json_decode(trim(ac_run([PHP_BINARY, $config['wp_cli'], '--path=' . $config['wordpress_root'], 'eval', $code])), true, 32, JSON_THROW_ON_ERROR);
    ac_require(($result['url'] ?? '') === rtrim($config['expected_url'], '/') && ($result['theme'] ?? '') === 'auracast'
        && ($result['woocommerce'] ?? false) === true, 'WordPress URL, active theme or WooCommerce differs from the configured store.');
    if ($version !== null) ac_require(($result['version'] ?? '') === $version, 'WordPress loaded another theme version.');
    return $result;
}
function ac_write_state($path, $state) {
    $temporary = $path . '.' . bin2hex(random_bytes(6));
    ac_require(file_put_contents($temporary, json_encode($state, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR)) !== false && chmod($temporary, 0600) && rename($temporary, $path), 'Cannot record release state.');
}
function ac_received_bytes($length) {
    $bytes = '';
    while (strlen($bytes) < $length) {
        $part = fread(STDIN, $length - strlen($bytes));
        ac_require($part !== false && $part !== '', 'Incomplete release header.');
        $bytes .= $part;
    }
    return $bytes;
}
function ac_remove_stage($path, $backups) {
    if (!$path || !is_dir($path)) return;
    $resolved = realpath($path); $root = realpath($backups);
    ac_require($resolved && $root && !is_link($path) && dirname($resolved) === $root
        && preg_match('/^incoming-\d{8}-\d{6}-[a-f0-9]{40}-[a-f0-9]{8}$/D', basename($resolved)), 'Unsafe release staging cleanup target.');
    $remove = function ($directory) use (&$remove, $resolved) {
        ac_require(ac_inside(realpath($directory), $resolved) && !is_link($directory), 'Unsafe release staging directory.');
        foreach (new FilesystemIterator($directory) as $item) {
            if ($item->isDir() && !$item->isLink()) $remove($item->getPathname());
            else ac_require(unlink($item->getPathname()), 'Cannot remove temporary release file.');
        }
        ac_require(rmdir($directory), 'Cannot remove temporary release directory.');
    };
    $remove($resolved);
}
$lock = null; $archive = null; $previous = null; $destination = null;
$installed = false; $owns_maintenance = false; $maintenance = null; $config = null;
$stage = null; $exit_code = 0; $maintenance_code = null;
try {
    $command = ac_command(getenv('SSH_ORIGINAL_COMMAND') ?: '');
    ac_require(PHP_VERSION_ID >= 80300, 'Hosting deployment requires PHP 8.3+.');
    $config_path = __DIR__ . '/config.php';
    ac_require(is_file($config_path) && !is_link($config_path), 'Private hosting configuration is missing.');
    $config = require $config_path;
    $wordpress = realpath($config['wordpress_root'] ?? '');
    $private = realpath($config['private_root'] ?? '');
    $backups = realpath($config['backup_root'] ?? '');
    ac_require($private === realpath(__DIR__) && $wordpress && is_file($wordpress . '/wp-config.php') && is_file($wordpress . '/wp-load.php')
        && $backups && $backups !== $private && ac_inside($backups, $private) && !is_link($config['backup_root'])
        && !ac_inside($private, $wordpress) && !ac_inside($wordpress, $private), 'Private deployment paths are unsafe.');
    $themes = realpath($wordpress . '/wp-content/themes');
    ac_require($themes && ac_inside($themes, $wordpress) && stat($themes)['dev'] === stat($backups)['dev'], 'The theme and backups must share a filesystem.');
    $destination = $themes . '/auracast';
    ac_require(is_dir($destination) && !is_link($destination), 'Expected an existing regular Auracast theme directory.');
    ac_require(is_file($config['wp_cli'] ?? '') && !is_link($config['wp_cli']), 'Private WP-CLI is missing.');
    $settle = $config['settle_seconds'] ?? 3;
    ac_require(is_int($settle) && $settle >= 0 && $settle <= 5, 'Invalid cache revalidation delay.');
    $config['wordpress_root'] = $wordpress;
    $lock = fopen($private . '/deploy.lock', 'c');
    ac_require($lock && flock($lock, LOCK_EX | LOCK_NB), 'Another deployment is running.');
    chmod($private . '/deploy.lock', 0600);
    $state_path = $private . '/release-state.json';
    if ($command['operation'] === 'status') {
        ac_require(is_file($state_path), 'No successful Auracast release has been recorded.');
        $state = json_decode(file_get_contents($state_path), true, 32, JSON_THROW_ON_ERROR);
        ac_require(($state['commit'] ?? '') === $command['commit'] && ($state['zip_sha256'] ?? '') === $command['digest'], 'A different release is installed.');
        ac_native($config, $state['version']); echo json_encode($state, JSON_THROW_ON_ERROR) . "\n"; return;
    }
    ac_require(fgets(STDIN, 32) === "AURACAST1\n", 'Invalid deployment protocol.');
    $length = trim(fgets(STDIN, 16) ?: '');
    ac_require(ctype_digit($length) && (int) $length > 0 && (int) $length <= 256 * 1024, 'Invalid header length.');
    $header = ac_header(ac_received_bytes((int) $length), $command);
    $release = gmdate('Ymd-His') . '-' . $command['commit'] . '-' . bin2hex(random_bytes(4));
    $archive = $private . '/received-' . $release . '.zip';
    $file = fopen($archive, 'x'); ac_require($file !== false, 'Cannot receive archive.');
    try { $received = stream_copy_to_stream(STDIN, $file, $header['zip_size'] + 1); }
    finally { fclose($file); }
    chmod($archive, 0600);
    ac_require($received === $header['zip_size'], 'Incomplete or oversized archive.');
    $stage = $backups . '/incoming-' . $release;
    ac_require(mkdir($stage, 0755), 'Cannot create private release staging directory.');
    ac_extract($archive, $header, $stage);
    foreach ($header['files'] as $name => $metadata) {
        if (str_ends_with($name, '.php')) ac_run([PHP_BINARY, '-l', $stage . '/' . $name]);
    }
    ac_native($config);
    if ($command['operation'] === 'plan') {
        echo 'Verified hosting and ' . count($header['files']) . " theme files; no installation performed.\n"; return;
    }
    $maintenance = $wordpress . '/.maintenance';
    ac_require(!file_exists($maintenance) && !is_link($maintenance), 'WordPress is already in maintenance.');
    $previous = $backups . '/previous-' . $release;
    $handle = fopen($maintenance, 'x'); ac_require($handle !== false, 'Cannot enter maintenance.');
    $owns_maintenance = true; $maintenance_code = '<?php $upgrading = ' . time() . '; // auracast-' . $release;
    try { ac_require(fwrite($handle, $maintenance_code) === strlen($maintenance_code), 'Cannot write maintenance marker.'); }
    finally { fclose($handle); }
    ac_require(rename($destination, $previous), 'Cannot back up the current theme.');
    ac_require(rename($stage, $destination), 'Cannot install the verified theme.');
    $installed = true;
    if ($settle) usleep($settle * 1000000);
    ac_native($config, $header['version'], true);
    foreach ($header['files'] as $name => $metadata) ac_require(hash_equals($metadata['sha256'], hash_file('sha256', $destination . '/' . $name)), 'Installed theme files differ.');
    $state = ['commit' => $command['commit'], 'zip_sha256' => $command['digest'], 'version' => $header['version'], 'file_count' => count($header['files']), 'previous_theme' => $previous, 'deployed_at' => gmdate('c')];
    ac_write_state($state_path, $state);
    echo 'Installed Auracast ' . $header['version'] . ' from ' . $command['commit'] . "; native checks passed.\n";
} catch (Throwable $error) {
    $restored = false;
    if ($previous && is_dir($previous) && $destination && !is_link($destination)) {
        if ($installed && is_dir($destination)) {
            $failed = $backups . '/failed-' . bin2hex(random_bytes(8));
            $restored = rename($destination, $failed) && rename($previous, $destination);
        } elseif (!file_exists($destination)) $restored = rename($previous, $destination);
        if ($restored) {
            try { if ($settle) usleep($settle * 1000000); ac_native($config, null, true); }
            catch (Throwable $rollback_error) { $restored = false; }
        }
        if (!$restored) $owns_maintenance = false; // Keep maintenance when recovery needs the operator.
    }
    fwrite(STDERR, 'Deployment refused or rolled back: ' . $error->getMessage() . "\n");
    $exit_code = 1;
} finally {
    if ($archive && is_file($archive) && !is_link($archive) && ac_inside(realpath($archive), realpath(__DIR__))) unlink($archive);
    if ($stage && isset($backups)) ac_remove_stage($stage, $backups);
    if ($owns_maintenance && $maintenance && is_file($maintenance)) {
        if (file_get_contents($maintenance) === $maintenance_code) unlink($maintenance);
        else { fwrite(STDERR, "Maintenance was changed by another operation; marker retained.\n"); $exit_code = 1; }
    }
    if (is_resource($lock)) { flock($lock, LOCK_UN); fclose($lock); }
}
exit($exit_code);
