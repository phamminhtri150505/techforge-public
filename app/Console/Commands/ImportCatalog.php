<?php

namespace App\Console\Commands;

use App\Services\CatalogImportService;
use App\Services\ProductSpecificationSchema;
use Illuminate\Console\Command;
use Illuminate\Validation\ValidationException;
use JsonException;

class ImportCatalog extends Command
{
    protected $signature = 'techforge:import-catalog {path? : File JSON danh mục đã đối chiếu} {--apply : Thực sự thêm dữ liệu, giữ bản ghi đang có}';

    protected $description = 'Kiểm tra/nhập danh mục linh kiện có nguồn; mặc định chỉ chạy thử, không ghi database';

    public function handle(CatalogImportService $importer): int
    {
        $path = $this->argument('path') ?? base_path('database/catalog/market-2026-10-08.json');
        if (! is_file($path) || ! is_readable($path) || filesize($path) > 5000000) {
            $this->error('Không đọc được file hoặc file vượt 5 MB.');

            return self::FAILURE;
        }
        try {
            $rows = json_decode(file_get_contents($path), true, 64, JSON_THROW_ON_ERROR);
            if (! is_array($rows)) {
                throw new JsonException;
            }
            $result = $importer->import($rows, (bool) $this->option('apply'));
        } catch (JsonException) {
            $this->error('File không phải danh sách JSON hợp lệ.');

            return self::FAILURE;
        } catch (ValidationException $exception) {
            foreach ($exception->errors() as $field => $messages) {
                $this->error($field.': '.implode(' ', $messages));
            }

            return self::FAILURE;
        }
        $this->info($result['apply'] ? 'Đã nhập, giữ nguyên bản ghi hiện có.' : 'Chạy thử: chưa ghi dữ liệu. Thêm --apply để nhập.');
        $this->line('Đã thêm: '.$result['created'].' | Sẽ thêm: '.$result['would_create'].' | Giữ nguyên: '.$result['skipped']);
        $this->table(['Nhóm', 'Số dòng trong file'], array_map(fn ($type, $count) => [ProductSpecificationSchema::TYPES[$type], $count], array_keys($result['types']), $result['types']));
        $this->line('Hãng: '.implode(', ', array_keys($result['brands'])));
        $this->line('Loại giá: '.json_encode($result['prices'], JSON_UNESCAPED_UNICODE));

        return self::SUCCESS;
    }
}
