// Chạy thủ công với danh sách URL đã đối chiếu. Không crawl, không sửa ảnh gốc.
// node scripts/download-product-images.mjs <file JSON đã chọn> <file kết quả JSON>
import {readFile, writeFile, mkdir, access} from 'node:fs/promises';
import {createHash} from 'node:crypto';

const [input, output] = process.argv.slice(2);
if (!input || !output) throw new Error('Cần đường dẫn danh sách đã chọn và file kết quả.');
const rows = JSON.parse(await readFile(input, 'utf8'));
await mkdir('public/images/products', {recursive: true});
const accepted = [], failures = [];
function format(bytes) {
  if (bytes.subarray(0, 8).equals(Buffer.from([137,80,78,71,13,10,26,10]))) return 'png';
  if (bytes[0] === 255 && bytes[1] === 216 && bytes[2] === 255) return 'jpg';
  if (bytes.toString('ascii',0,4) === 'RIFF' && bytes.toString('ascii',8,12) === 'WEBP') return 'webp';
  throw new Error('Không phải PNG/JPEG/WebP; không nhận HTML, SVG hay GIF.');
}
for (const row of rows) {
  try {
    if (![row.source_page_url, row.source_image_url].every(u=>new URL(u).protocol==='https:')) throw new Error('Nguồn phải dùng HTTPS.');
    let bytes;
    if (row.extracted_path) {
      if (!/^\.runtime\/image-research\/pdf-images\/[a-z0-9_-]+\.(png|jpg|webp)$/.test(row.extracted_path)) throw new Error('Đường dẫn trích PDF không hợp lệ.');
      bytes = await readFile(row.extracted_path);
    } else {
      const response = await fetch(row.source_image_url, {signal: AbortSignal.timeout(25000), redirect:'follow'});
      if (!response.ok) throw new Error(`HTTP ${response.status}`);
      if (new URL(response.url).protocol !== 'https:') throw new Error('Chuyển hướng xuống HTTP.');
      if (Number(response.headers.get('content-length')) > 5*1024*1024) throw new Error('Ảnh lớn hơn 5 MiB.');
      const chunks=[]; let total=0;
      for await (const chunk of response.body) {
        total += chunk.length;
        if (total > 5*1024*1024) throw new Error('Ảnh lớn hơn 5 MiB.');
        chunks.push(chunk);
      }
      bytes=Buffer.concat(chunks);
    }
    const extension=format(bytes);
    const sha256=createHash('sha256').update(bytes).digest('hex');
    const path=`images/products/${sha256.slice(0,24)}.${extension}`;
    try {
      await access(`public/${path}`);
      const existing=createHash('sha256').update(await readFile(`public/${path}`)).digest('hex');
      if(existing!==sha256) throw new Error('File hiện có khác nội dung; dừng thay vì ghi đè.');
    } catch(error) {
      if(error.code!=='ENOENT') throw error;
      await writeFile(`public/${path}`,bytes,{flag:'wx'});
    }
    const {extracted_path, ...metadata}=row;
    metadata.source_page_url=new URL(metadata.source_page_url).href;
    metadata.source_image_url=new URL(metadata.source_image_url).href;
    accepted.push({...metadata,path,sha256,bytes:bytes.length});
    console.log(`OK ${row.sku} (${Math.round(bytes.length/1024)} KiB)`);
  } catch(error) {
    failures.push({sku:row.sku,error:error.message});
    console.log(`CHƯA NHẬN ${row.sku}: ${error.message}`);
  }
}
await writeFile(output, JSON.stringify(accepted,null,2)+'\n');
await writeFile(`${output}.failures.json`,JSON.stringify(failures,null,2)+'\n');
console.log(`Đã tải ${accepted.length}/${rows.length}; còn ${failures.length}. Chưa tự cập nhật manifest của ứng dụng.`);
