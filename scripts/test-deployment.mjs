import { existsSync } from 'node:fs';
import { spawnSync } from 'node:child_process';
import path from 'node:path';
import assert from 'node:assert/strict';
import { allowedFile, projectRoot, themeManifest } from './theme-release.mjs';

for (const file of ['../wp-config.php', 'assets/images/../../wp-config.php', 'assets/images/.env', '/style.css', 'wp-config.php', 'plugins/plugin.php', 'assets/images/shell.php']) assert.equal(allowedFile(file), false, file);
const manifest = await themeManifest();
assert.ok(Object.keys(manifest.files).length >= 100);
const local = path.join(projectRoot, '.local/php/php.exe');
const executable = existsSync(local) ? local : 'php';
const args = [...(existsSync(local) ? ['-c', path.join(projectRoot, '.local/php/php.ini')] : []), '-d', 'display_errors=stderr'];
for (const file of ['deployment-lib.php', 'github-deploy-receiver.php', 'test-deployment.php']) {
  const lint = spawnSync(executable, [...args, '-l', path.join(projectRoot, 'scripts', file)], { encoding: 'utf8' });
  if (lint.status !== 0) throw new Error(lint.stdout + lint.stderr);
}
const test = spawnSync(executable, [...args, path.join(projectRoot, 'scripts/test-deployment.php')], { stdio: 'inherit' });
if (test.status !== 0) process.exit(test.status || 1);
console.log(`Release allowlist verified: ${Object.keys(manifest.files).length} theme files.`);
