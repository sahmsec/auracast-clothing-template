import http from 'node:http';
import fs from 'node:fs';
import path from 'node:path';
import {fileURLToPath} from 'node:url';
import {spawn} from 'node:child_process';

const projectRoot=path.resolve(path.dirname(fileURLToPath(import.meta.url)),'..');
const localOrigin='http://127.0.0.1:8088';
const localOrigins=[localOrigin,'https://127.0.0.1:8088','http://localhost:8088','https://localhost:8088'];

function replaceOrigins(text,publicOrigin){
 for(const origin of localOrigins){
  text=text.replaceAll(origin,publicOrigin).replaceAll(origin.replaceAll('/','\\/'),publicOrigin.replaceAll('/','\\/'));
 }
 return text;
}

function privatePath(rawURL){
 let pathname=rawURL.split('?')[0];
 try{for(let i=0;i<3;i++){const decoded=decodeURIComponent(pathname);if(decoded===pathname)break;pathname=decoded;}}catch{return true;}
 if(!pathname.startsWith('/')||pathname.startsWith('//')||/[\\\x00-\x1f]/.test(pathname))return true;
 const parts=pathname.split('/');
 if(parts.some(part=>part.startsWith('.')||/[. ]$/.test(part))||/%[a-f\d]{2}/i.test(pathname))return true;
 if(/\.(sqlite(?:3)?|db|sql|log|ini|bak|zip)$/i.test(pathname))return true;
 if(parts.some(part=>/^(database|wc-logs|woocommerce_uploads)$/i.test(part)))return true;
 if(/^\/wp-admin(?:\/|$)/i.test(pathname)&&pathname.toLowerCase()!=='/wp-admin/admin-ajax.php')return true;
 if(/\.php/i.test(pathname)&&!['/index.php','/wp-admin/admin-ajax.php'].includes(pathname.toLowerCase()))return true;
 return false;
}

// Sharing adapter only. The WordPress URL and database stay local and unchanged.
export function createPreviewProxy(getPublicOrigin){
 return http.createServer((request,response)=>{
  const publicOrigin=getPublicOrigin();
  if(!publicOrigin){response.writeHead(503,{'Content-Type':'text/plain'});response.end('Preparing the preview link. Please reload shortly.');return;}
  if(privatePath(request.url)){response.writeHead(404,{'Content-Type':'text/plain','X-Robots-Tag':'noindex, nofollow'});response.end('Not available on the shared preview.');return;}
  const headers={...request.headers,host:'127.0.0.1:8088','accept-encoding':'identity'};
  delete headers['forwarded'];delete headers['x-forwarded-host'];delete headers['x-forwarded-proto'];
  for(const name of ['origin','referer']){if(headers[name])headers[name]=headers[name].replace(publicOrigin,localOrigin);}
  const upstream=http.request({hostname:'127.0.0.1',port:8088,path:request.url,method:request.method,headers},originResponse=>{
   const outputHeaders={...originResponse.headers,'x-robots-tag':'noindex, nofollow, noarchive'};
   delete outputHeaders['connection'];delete outputHeaders['transfer-encoding'];
   for(const name of ['location','content-location','access-control-allow-origin']){if(typeof outputHeaders[name]==='string')outputHeaders[name]=replaceOrigins(outputHeaders[name],publicOrigin);}
   const isText=/(?:^text\/|javascript|json|xml)/i.test(String(outputHeaders['content-type']||''));
   if(isText && !outputHeaders['content-encoding']){
    const chunks=[];
    originResponse.on('data',chunk=>chunks.push(chunk));
    originResponse.on('end',()=>{
     const body=Buffer.from(replaceOrigins(Buffer.concat(chunks).toString('utf8'),publicOrigin));
     delete outputHeaders['etag'];delete outputHeaders['last-modified'];
     outputHeaders['content-length']=body.length;outputHeaders['cache-control']='no-store';
     response.writeHead(originResponse.statusCode,outputHeaders);response.end(body);
    });
   }else{response.writeHead(originResponse.statusCode,outputHeaders);originResponse.pipe(response);}
   originResponse.on('error',()=>response.destroy());
  });
  upstream.on('error',()=>{if(!response.headersSent){response.writeHead(502,{'Content-Type':'text/plain'});response.end('The local store is unavailable.');}else response.destroy();});
  request.on('aborted',()=>upstream.destroy());
  request.pipe(upstream);
 });
}

async function share(){
 const localDirectory=path.join(projectRoot,'.local');
 fs.mkdirSync(localDirectory,{recursive:true});
 const helpers=[];
 let proxy,publicOrigin='';
 const cleanup=()=>{for(const helper of helpers){try{helper.kill();}catch{}}proxy?.close();};
 process.on('SIGINT',()=>{cleanup();process.exit(0);});
 process.on('SIGTERM',()=>{cleanup();process.exit(0);});
 process.on('exit',cleanup);
 try{
  try{const check=await fetch(localOrigin,{signal:AbortSignal.timeout(3000)});if(!check.ok)throw new Error('Local store returned '+check.status);}
  catch{
   const php=path.join(localDirectory,'php','php.exe');
   if(!fs.existsSync(php))throw new Error('Run scripts/setup-local.ps1 to install the local WordPress runtime first.');
   const server=spawn(php,['-c',path.join(localDirectory,'php','php.ini'),'-S','127.0.0.1:8088','-t',path.join(localDirectory,'wordpress','wordpress'),path.join(projectRoot,'scripts','router.php')],{windowsHide:true,stdio:['ignore','ignore','pipe']});
   helpers.push(server);server.stderr.on('data',()=>{});server.on('error',()=>{});
   let ready=false;
   for(let i=0;i<30;i++){await new Promise(resolve=>setTimeout(resolve,200));try{if((await fetch(localOrigin,{signal:AbortSignal.timeout(2000)})).ok){ready=true;break;}}catch{}}
   if(!ready)throw new Error('Could not start the local WordPress store.');
  }
  const binary=path.join(localDirectory,process.platform==='win32'?'cloudflared.exe':'cloudflared');
  if(!fs.existsSync(binary)){
   if(process.platform!=='win32'||process.arch!=='x64')throw new Error('Install cloudflared for your system and save it as '+binary);
   console.log('Downloading the official Cloudflare tunnel client into .local/ ...');
   const download=await fetch('https://github.com/cloudflare/cloudflared/releases/latest/download/cloudflared-windows-amd64.exe',{signal:AbortSignal.timeout(120000)});
   if(!download.ok)throw new Error('Cloudflare client download failed: '+download.status);
   const temporaryBinary=path.join(localDirectory,'cloudflared-download.exe');
   fs.writeFileSync(temporaryBinary,Buffer.from(await download.arrayBuffer()));
   fs.renameSync(temporaryBinary,binary);
  }
  proxy=createPreviewProxy(()=>publicOrigin);
  await new Promise((resolve,reject)=>{proxy.once('error',reject);proxy.listen(8089,'127.0.0.1',resolve);});
  const tunnel=spawn(binary,['tunnel','--no-autoupdate','--protocol','http2','--url','http://127.0.0.1:8089'],{windowsHide:true,stdio:['ignore','pipe','pipe']});
  helpers.push(tunnel);
  const logs=fs.createWriteStream(path.join(localDirectory,'share-tunnel.log'),{flags:'w'});
  let pending='';
  const showLink=chunk=>{
   logs.write(chunk);pending=(pending+chunk.toString()).slice(-12000);
   const match=pending.match(/https:\/\/(?!api\.)[a-z\d-]+\.trycloudflare\.com\b/i);
   if(match&&!publicOrigin){
    publicOrigin=match[0];
    fs.writeFileSync(path.join(localDirectory,'share-url.txt'),publicOrigin+'\n');
    console.log('\nShare this link with your client:\n'+publicOrigin+'\n\nKeep this terminal open and your PC awake. Press Ctrl+C to stop sharing.\nThe local admin remains at '+localOrigin+'/wp-admin/');
   }
  };
  tunnel.stdout.on('data',showLink);tunnel.stderr.on('data',showLink);
  console.log('Creating a temporary public storefront link ...');
  await new Promise((resolve,reject)=>{tunnel.once('error',reject);tunnel.once('exit',code=>code===0?resolve():reject(new Error('Tunnel stopped. See .local/share-tunnel.log for details.')));});
  logs.end();cleanup();
 }catch(error){cleanup();console.error(error.message);process.exitCode=1;}
}

if(process.argv[1]&&path.resolve(process.argv[1])===fileURLToPath(import.meta.url))await share();
