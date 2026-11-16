<?php

namespace App\Services;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use PDO;

class ArticleSearchService
{
    public function apply(Builder $query, string $term): Builder
    {
        // Không phân biệt hoa/thường và dấu tiếng Việt; không tải mọi bài về PHP.
        $normalized = trim(Str::lower(Str::ascii($term, 'vi')));
        // Từ khóa chỉ chứa ký hiệu không chuẩn hóa được không được biến thành %%.
        if ($normalized === '') {
            return $query->whereRaw('1 = 0');
        }
        $pattern = '%'.strtr($normalized, ['!' => '!!', '%' => '!%', '_' => '!_']).'%';
        $sqlite = $query->getConnection()->getDriverName() === 'sqlite';
        if ($sqlite) {
            // SQLite không có collation tiếng Việt như MySQL. Chuẩn hóa lúc đọc
            // để kiểm thử và môi trường SQLite có cùng hành vi, không sửa dữ liệu.
            $query->getConnection()->getPdo()->sqliteCreateFunction(
                'techforge_article_text', fn ($text) => Str::lower(Str::ascii((string) $text, 'vi')),
                1, PDO::SQLITE_DETERMINISTIC
            );
        }

        return $query->where(function (Builder $matches) use ($pattern, $sqlite) {
            foreach (['title', 'excerpt', 'category'] as $field) {
                // Cột cố định, giá trị dùng binding; %, _ và ! là ký tự thường.
                $column = $sqlite ? "techforge_article_text({$field})" : "{$field} COLLATE utf8mb4_unicode_ci";
                $matches->orWhereRaw("{$column} LIKE ? ESCAPE '!'", [$pattern]);
            }
        });
    }
}
