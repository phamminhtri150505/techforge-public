// Chỉ đọc các URL sản phẩm đã chọn; không tìm/crawl liên kết tự động, chưa tải ảnh.
import {readFile, writeFile, mkdir} from 'node:fs/promises';
const files=['market-2026-10-08.json','cpu-2026-10-09.json','gpu-popular-2026-10-10.json'];
const products=new Map();
for(const file of files) for(const row of JSON.parse(await readFile(`database/catalog/${file}`,'utf8'))) products.set(row.sku,row);
const decode=s=>s.replaceAll('&amp;','&').replaceAll('&quot;','"').replaceAll('&#39;',"'");
const attr=(tag,name)=>decode(tag.match(new RegExp(`\\b${name}\\s*=\\s*["']([^"']*)["']`,'i'))?.[1]??'');
const results=[];
const tasks=[...products.values()].map(row=>async()=>{
 const urls=[...new Set([...(row.catalog_sources??[]).map(s=>s.url),row.price_source_url].filter(u=>u&&!u.endsWith('.pdf')))];
 const pages=[];
 for(const url of urls){
  try {
   const response=await fetch(url,{signal:AbortSignal.timeout(18000)});
   const html=await response.text();
   const images=[];
   for(const tag of html.match(/<meta\b[^>]*>/gi)??[]) if(['og:image','twitter:image','og:image:secure_url'].includes(attr(tag,'property')||attr(tag,'name'))) images.push({url:attr(tag,'content'),alt:'meta-image'});
   for(const tag of html.match(/<img\b[^>]*>/gi)??[]){
    const src=attr(tag,'src')||attr(tag,'data-src'); const alt=attr(tag,'alt');
    if(src&&!/logo|icon|banner|flag|avatar|sprite/i.test(src+' '+alt)) images.push({url:src,alt});
   }
   const unique=[...new Map(images.filter(x=>!x.url.startsWith('data:')).map(x=>{try{return [new URL(x.url,response.url).href,{url:new URL(x.url,response.url).href,alt:x.alt}]}catch{return ['',null]}})).values()].filter(Boolean);
   pages.push({url,final_url:response.url,status:response.status,title:decode(html.match(/<title[^>]*>([\s\S]*?)<\/title>/i)?.[1]?.trim()??''),images:unique.slice(0,35)});
  } catch(error){pages.push({url,error:error.message});}
 }
 results.push({sku:row.sku,name:row.name,type:row.type,brand:row.brand,manufacturer_code:row.manufacturer_code,pages});
 console.log(row.sku,pages.map(p=>p.status??'error').join(','));
});
let cursor=0;
await Promise.all(Array.from({length:4},async()=>{while(cursor<tasks.length)await tasks[cursor++]();}));
await mkdir('.runtime/image-research',{recursive:true});
await writeFile('.runtime/image-research/candidates.json',JSON.stringify(results,null,2));
console.log('Đã lưu URL ứng viên, chưa xác nhận ảnh và chưa thay đổi ứng dụng.');
