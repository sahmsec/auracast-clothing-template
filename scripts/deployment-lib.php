<?php
/** Pure validation shared by the deployment receiver and its tests. */
function ac_require($condition, $message) {
    if (!$condition) throw new RuntimeException($message);
}
function ac_inside($path, $root) {
    return $path === $root || str_starts_with($path, $root . DIRECTORY_SEPARATOR);
}
function ac_allowed_file($file) {
    if (!is_string($file) || !preg_match('~^[A-Za-z0-9_-][A-Za-z0-9_.\/-]{0,239}$~D', $file)
        || preg_match('~(?:^|/)\.|(?:wp-config|credentials?|passwords?|private)~i', $file)) return false;
    return preg_match('~^(?:[A-Za-z0-9_-]+\.php|style\.css|screenshot\.png|(?:LICENSE|readme)\.txt)$~D', $file)
        || preg_match('~^(?:inc|woocommerce|template-parts)/(?:[A-Za-z0-9_-]+/)*[A-Za-z0-9_-]+\.php$~D', $file)
        || preg_match('~^assets/(?:css/[A-Za-z0-9_-]+\.css|js/[A-Za-z0-9_-]+\.js|data/[A-Za-z0-9_-]+\.json|images/[A-Za-z0-9_-]+\.(?:png|jpe?g|webp|svg|gif|ico|avif)|videos/[A-Za-z0-9_-]+\.(?:mp4|webm)|fonts/(?:fonts\.css|[A-Za-z0-9_.-]+\.(?:woff2?|ttf|otf|txt)))$~D', $file);
}
function ac_command($command) {
    ac_require(preg_match('/^auracast-(plan|deploy|status) ([a-f0-9]{40}) ([a-f0-9]{64})$/D', $command, $parts) === 1, 'Only fixed Auracast release commands are allowed.');
    return ['operation' => $parts[1], 'commit' => $parts[2], 'digest' => $parts[3]];
}
function ac_header($bytes, $command) {
    ac_require(strlen($bytes) > 0 && strlen($bytes) <= 256 * 1024, 'Invalid release header size.');
    $header = json_decode($bytes, true, 32, JSON_THROW_ON_ERROR);
    ac_require(($header['schema'] ?? '') === 1 && ($header['theme'] ?? '') === 'auracast'
        && ($header['commit'] ?? '') === $command['commit'] && ($header['zip_sha256'] ?? '') === $command['digest'], 'Release header does not match the SSH request.');
    ac_require(preg_match('/^\d+\.\d+\.\d+$/D', $header['version'] ?? '') === 1, 'Invalid release version.');
    ac_require(is_int($header['zip_size'] ?? null) && $header['zip_size'] > 0 && $header['zip_size'] <= 128 * 1024 * 1024, 'Invalid ZIP size.');
    $files = $header['files'] ?? null;
    ac_require(is_array($files) && count($files) >= 3 && count($files) <= 1000, 'Invalid release file count.');
    foreach (['style.css', 'functions.php', 'front-page.php'] as $file) ac_require(isset($files[$file]), 'Required theme files are absent.');
    $total = 0;
    foreach ($files as $file => $metadata) {
        ac_require(ac_allowed_file($file), 'File is outside the theme allowlist.');
        ac_require(preg_match('/^[a-f0-9]{64}$/D', $metadata['sha256'] ?? '') === 1
            && is_int($metadata['size'] ?? null) && $metadata['size'] >= 0 && $metadata['size'] <= 24 * 1024 * 1024, 'Invalid release file metadata.');
        $total += $metadata['size'];
    }
    ac_require($total <= 128 * 1024 * 1024, 'Extracted theme exceeds the release limit.');
    return $header;
}
function ac_extract($archive, $header, $stage) {
    ac_require(class_exists('ZipArchive'), 'PHP ZipArchive is required.');
    ac_require(hash_equals($header['zip_sha256'], hash_file('sha256', $archive)) && filesize($archive) === $header['zip_size'], 'ZIP checksum or size differs.');
    $zip = new ZipArchive();
    ac_require($zip->open($archive) === true, 'Cannot read release ZIP.');
    try {
        ac_require($zip->numFiles === count($header['files']), 'ZIP differs from the release manifest.');
        $seen = [];
        for ($index = 0; $index < $zip->numFiles; $index++) {
            $entry = $zip->statIndex($index);
            $name = $entry['name'];
            ac_require(str_starts_with($name, 'auracast/'), 'ZIP contains a foreign root.');
            $file = substr($name, 9);
            ac_require(ac_allowed_file($file) && isset($header['files'][$file]) && !isset($seen[$file]), 'ZIP contains an unsafe, duplicate or unlisted file.');
            $seen[$file] = true;
            ac_require($entry['size'] === $header['files'][$file]['size'] && empty($entry['encryption_method']), 'ZIP file size or encryption differs.');
            if ($zip->getExternalAttributesIndex($index, $system, $attributes)) {
                $type = ($attributes >> 16) & 0170000;
                ac_require($type === 0 || $type === 0100000, 'ZIP entries must be regular files.');
            }
            $target = $stage . '/' . $file;
            ac_require(is_dir(dirname($target)) || mkdir(dirname($target), 0755, true), 'Cannot create release directory.');
            $input = $zip->getStream($name); $output = fopen($target, 'x');
            ac_require($input && $output, 'Cannot prepare release file.');
            try { $written = stream_copy_to_stream($input, $output, $header['files'][$file]['size'] + 1); }
            finally { fclose($input); fclose($output); }
            ac_require($written === $header['files'][$file]['size'] && chmod($target, 0644)
                && hash_equals($header['files'][$file]['sha256'], hash_file('sha256', $target)), 'Extracted release file differs.');
        }
        ac_require(preg_match('/^Version:\s*' . preg_quote($header['version'], '/') . '\s*$/m', file_get_contents($stage . '/style.css')) === 1, 'Packaged theme version differs.');
    } finally { $zip->close(); }
}
