import {spawn} from 'node:child_process';
import path from 'node:path';
import fs from 'node:fs';
const php=path.resolve('.local/php/php.exe');
if(!fs.existsSync(php)){console.error('Run powershell -ExecutionPolicy Bypass -File scripts/setup-local.ps1 first.');process.exit(1);}
const server=spawn(php,['-c',path.resolve('.local/php/php.ini'),'-S','127.0.0.1:8088','-t',path.resolve('.local/wordpress/wordpress'),path.resolve('scripts/router.php')],{stdio:'inherit'});
console.log('Auracast WooCommerce preview: http://127.0.0.1:8088');
process.on('SIGINT',()=>server.kill());process.on('SIGTERM',()=>server.kill());server.on('exit',code=>process.exit(code||0));
