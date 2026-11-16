<?php

namespace App\Console\Commands;

use App\Services\CatalogEnrichmentService;
use Illuminate\Console\Command;
use Illuminate\Validation\ValidationException;
use JsonException;

class EnrichCatalog extends Command
{
    protected $signature = 'techforge:enrich-catalog {path : JSON chứa before/after đã đối chiếu} {--apply : Áp dụng khi dữ liệu chưa bị sửa}';

    protected $description = 'Bổ sung danh mục có so sánh bản gốc; mặc định chạy thử, giữ chỉnh sửa quản trị';

    public function handle(CatalogEnrichmentService $service): int
    {
        $path = $this->argument('path');
        if (! is_file($path) || ! is_readable($path) || filesize($path) > 5000000) {
            $this->error('Không đọc được file hoặc file vượt 5 MB.');

            return self::FAILURE;
        }
        try {
            $patches = json_decode(file_get_contents($path), true, 64, JSON_THROW_ON_ERROR);
            if (! is_array($patches)) {
                throw new JsonException;
            }
            $result = $service->enrich($patches, (bool) $this->option('apply'));
        } catch (JsonException) {
            $this->error('File không phải danh sách JSON hợp lệ.');

            return self::FAILURE;
        } catch (ValidationException $exception) {
            foreach ($exception->errors() as $field => $messages) {
                $this->error($field.': '.implode(' ', $messages));
            }

            return self::FAILURE;
        }
        $this->info($this->option('apply') ? 'Đã đối chiếu và áp dụng.' : 'Chạy thử: chưa ghi dữ liệu.');
        $this->line('Đã bổ sung: '.$result['updated'].' | Sẽ bổ sung: '.$result['would_update'].' | Đã đúng: '.$result['unchanged']);
        if ($result['protected']) {
            $this->warn('Giữ bản ghi đã sửa/không tìm thấy: '.implode(', ', $result['protected']));
        }

        return self::SUCCESS;
    }
}
