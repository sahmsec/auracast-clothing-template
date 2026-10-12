import fs from 'node:fs/promises';
const html=await fs.readFile('reference/yokai-instagram/feed.html','utf8');
const match=html.match(/const mf = (\[[\s\S]*?\]);/);
if(!match)throw new Error('Reference Instagram media not found');
const media=JSON.parse(match[1]).slice(0,6);
const items=await Promise.all(media.map(async(post,index)=>{
 const prefix=`instagram-demo-${String(index+1).padStart(2,'0')}`;
 const item={title:`Instagram ${post.mediaType==='VIDEO'?'reel':'post'} ${index+1}`,link:post.permalink,caption:'',cover:`${prefix}.jpg`,video:''};
 const poster=post.thumbnailUrl||(post.mediaType!=='VIDEO'?post.mediaUrl:'');
 if(poster){const response=await fetch(poster,{signal:AbortSignal.timeout(60000)});if(!response.ok)throw new Error('Poster download '+response.status);await fs.writeFile(`auracast/assets/images/${item.cover}`,Buffer.from(await response.arrayBuffer()));}
 if(post.mediaType==='VIDEO'){
  item.video=`${prefix}.mp4`;
  const response=await fetch(post.mediaUrl,{signal:AbortSignal.timeout(120000)});if(!response.ok)throw new Error('Reel download '+response.status);await fs.mkdir('auracast/assets/videos',{recursive:true});await fs.writeFile(`auracast/assets/videos/${item.video}`,Buffer.from(await response.arrayBuffer()));
 }
 return item;
}));
await fs.writeFile('auracast/assets/data/instagram-demo.json',JSON.stringify({profile:'https://www.instagram.com/yokaiclothingbd/',reference:'https://www.yokaiclothingbd.com/',items},null,2)+'\n');
console.log(JSON.stringify(items.map(i=>({cover:i.cover,video:i.video,link:i.link})),null,2));
