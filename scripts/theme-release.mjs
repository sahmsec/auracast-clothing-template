import fs from 'node:fs/promises';
import { createReadStream, createWriteStream } from 'node:fs';
import { pipeline } from 'node:stream/promises';
import path from 'node:path';
import crypto from 'node:crypto';
import { execFileSync } from 'node:child_process';
import { fileURLToPath } from 'node:url';
import yazl from 'yazl';

export const projectRoot = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
export const themeRoot = path.join(projectRoot, 'auracast');
export const maximumBytes = 128 * 1024 * 1024;
export function allowedFile(file) {
  if (!/^[A-Za-z0-9_-][A-Za-z0-9_.\/-]{0,239}$/.test(file) || file.split('/').some(part => part.startsWith('.')) || /(?:wp-config|credentials?|passwords?|private)/i.test(file)) return false;
  return /^(?:[A-Za-z0-9_-]+\.php|style\.css|screenshot\.png|(?:LICENSE|readme)\.txt)$/.test(file)
    || /^(?:inc|woocommerce|template-parts)\/(?:[A-Za-z0-9_-]+\/)*[A-Za-z0-9_-]+\.php$/.test(file)
    || /^assets\/(?:css\/[A-Za-z0-9_-]+\.css|js\/[A-Za-z0-9_-]+\.js|data\/[A-Za-z0-9_-]+\.json|images\/[A-Za-z0-9_-]+\.(?:png|jpe?g|webp|svg|gif|ico|avif)|videos\/[A-Za-z0-9_-]+\.(?:mp4|webm)|fonts\/(?:fonts\.css|[A-Za-z0-9_.-]+\.(?:woff2?|ttf|otf|txt)))$/.test(file);
}
export async function themeManifest() {
  const files = {}, paths = [];
  async function walk(directory) {
    for (const entry of await fs.readdir(directory, { withFileTypes: true })) {
      const target = path.join(directory, entry.name);
      if (entry.isSymbolicLink()) throw new Error('Release source cannot contain symbolic links.');
      if (entry.isDirectory()) await walk(target);
      else if (entry.isFile()) paths.push(path.relative(themeRoot, target).split(path.sep).join('/'));
      else throw new Error('Unsupported release file type.');
    }
  }
  await walk(themeRoot);
  let size = 0;
  for (const file of paths.sort()) {
    if (!allowedFile(file)) throw new Error(`File is outside the theme release allowlist: ${file}`);
    const bytes = await fs.readFile(path.join(themeRoot, file));
    size += bytes.length;
    if (bytes.length > 24 * 1024 * 1024 || size > maximumBytes) throw new Error('Theme exceeds deployment size limits.');
    files[file] = { sha256: crypto.createHash('sha256').update(bytes).digest('hex'), size: bytes.length };
  }
  const style = await fs.readFile(path.join(themeRoot, 'style.css'), 'utf8');
  const version = style.match(/^Version:\s*(\d+\.\d+\.\d+)\s*$/m)?.[1];
  const bootstrap = await fs.readFile(path.join(themeRoot, 'functions.php'), 'utf8');
  const packageVersion = JSON.parse(await fs.readFile(path.join(projectRoot, 'package.json'), 'utf8')).version;
  if (!version || version !== packageVersion || !bootstrap.includes(`define( 'AURACAST_VERSION', '${version}' );`)) throw new Error('Theme, bootstrap and package versions must agree.');
  return { schema: 1, theme: 'auracast', version, files };
}
export async function buildArchive(destination, manifest) {
  await fs.mkdir(path.dirname(destination), { recursive: true });
  const temporary = `${destination}.${crypto.randomBytes(6).toString('hex')}.tmp`;
  const zip = new yazl.ZipFile();
  zip.on('error', error => zip.outputStream.destroy(error));
  const completion = pipeline(zip.outputStream, createWriteStream(temporary));
  for (const file of Object.keys(manifest.files)) zip.addFile(path.join(themeRoot, file), `auracast/${file}`, { mtime: new Date('2020-01-01T00:00:00Z'), mode: 0o100644 });
  zip.end();
  await completion;
  await fs.rename(temporary, destination);
}
export async function checksum(file) {
  const hash = crypto.createHash('sha256');
  for await (const bytes of createReadStream(file)) hash.update(bytes);
  return hash.digest('hex');
}
if (process.argv.includes('--package') || process.argv.includes('--deploy')) {
  const deploy = process.argv.includes('--deploy');
  const manifest = await themeManifest();
  const destination = path.join(projectRoot, 'dist', deploy ? 'auracast-deploy.zip' : 'auracast.zip');
  await buildArchive(destination, manifest);
  if (deploy) {
    const commit = process.env.AURACAST_RELEASE_COMMIT || execFileSync('git', ['rev-parse', 'HEAD'], { cwd: projectRoot, encoding: 'utf8' }).trim();
    if (!/^[a-f0-9]{40}$/.test(commit)) throw new Error('An exact Git commit is required.');
    const header = { ...manifest, commit, zip_sha256: await checksum(destination), zip_size: (await fs.stat(destination)).size };
    await fs.writeFile(path.join(projectRoot, 'dist/deploy-header.json'), JSON.stringify(header, null, 2) + '\n');
  }
  console.log(`Packaged Auracast ${manifest.version}: ${Object.keys(manifest.files).length} theme files.`);
}
