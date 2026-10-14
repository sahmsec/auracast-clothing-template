import fs from 'node:fs/promises';
import { createReadStream } from 'node:fs';
import path from 'node:path';
import os from 'node:os';
import { spawn } from 'node:child_process';
import { checksum, projectRoot } from './theme-release.mjs';

const operation = process.argv[2];
if (!['plan', 'deploy', 'status'].includes(operation)) throw new Error('Choose plan, deploy or status.');
const headerPath = path.join(projectRoot, 'dist/deploy-header.json');
const header = await fs.readFile(headerPath);
const release = JSON.parse(header);
const archive = path.join(projectRoot, 'dist/auracast-deploy.zip');
if (!/^[a-f0-9]{40}$/.test(release.commit) || !/^[a-f0-9]{64}$/.test(release.zip_sha256)
  || await checksum(archive) !== release.zip_sha256 || (await fs.stat(archive)).size !== release.zip_size) throw new Error('Release artifact differs from its manifest.');
if (process.env.GITHUB_ACTIONS === 'true' && operation === 'deploy') {
  if (process.env.GITHUB_REF !== 'refs/heads/main' || release.commit !== process.env.GITHUB_SHA) throw new Error('Only this main-branch revision may deploy.');
  const response = await fetch(`https://api.github.com/repos/${process.env.GITHUB_REPOSITORY}/commits/main`, { headers: { Authorization: `Bearer ${process.env.GITHUB_TOKEN}`, 'User-Agent': 'auracast-deployment', 'X-GitHub-Api-Version': '2022-11-28' } });
  if (!response.ok || (await response.json()).sha !== release.commit) throw new Error('A newer main revision exists; this release will not deploy.');
}
const { AURACAST_SSH_HOST: host, AURACAST_SSH_USER: user, AURACAST_SSH_KEY: key, AURACAST_SSH_KNOWN_HOSTS: known } = process.env;
if (!/^[A-Za-z0-9.-]+$/.test(host || '') || !/^[A-Za-z0-9_]+$/.test(user || '') || !key?.includes('PRIVATE KEY') || !known?.includes('ssh-ed25519')) throw new Error('Dedicated hosting identity and pinned host key are required.');
const temporaryRoot = await fs.realpath(os.tmpdir());
const directory = await fs.mkdtemp(path.join(temporaryRoot, 'auracast-ssh-'));
try {
  const identity = path.join(directory, 'identity'), hostFile = path.join(directory, 'known-hosts');
  await fs.writeFile(identity, key.trim() + '\n', { mode: 0o600 });
  await fs.writeFile(hostFile, known.trim() + '\n', { mode: 0o600 });
  const executable = process.platform === 'win32' ? 'C:/Program Files/Git/usr/bin/ssh.exe' : 'ssh';
  const args = ['-T', '-p', '21098', '-i', identity, '-o', 'BatchMode=yes', '-o', 'IdentitiesOnly=yes', '-o', 'StrictHostKeyChecking=yes', '-o', `UserKnownHostsFile=${hostFile}`, '-o', 'ConnectTimeout=20', '-o', 'ServerAliveInterval=15', `${user}@${host}`, `auracast-${operation} ${release.commit} ${release.zip_sha256}`];
  const child = spawn(executable, args, { stdio: ['pipe', 'inherit', 'inherit'] });
  const completion = new Promise((resolve, reject) => { child.once('error', reject); child.once('close', resolve); });
  child.stdin.on('error', () => {});
  if (operation === 'status') child.stdin.end();
  else {
    child.stdin.write(`AURACAST1\n${header.length}\n`);
    child.stdin.write(header);
    createReadStream(archive).on('error', error => { child.kill(); console.error(error.message); }).pipe(child.stdin);
  }
  const status = await completion;
  if (status !== 0) throw new Error('Hosting deployment failed; inspect the private release log.');
} finally {
  const resolved = await fs.realpath(directory);
  if (path.dirname(resolved) !== temporaryRoot || !/^auracast-ssh-[A-Za-z0-9]+$/.test(path.basename(resolved))) throw new Error('Unexpected SSH credential cleanup path.');
  await fs.rm(resolved, { recursive: true, force: true });
}
