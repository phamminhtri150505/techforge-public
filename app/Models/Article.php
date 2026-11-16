<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Article extends Model
{
    public const CATEGORIES = ['Kiến thức PC', 'Hướng dẫn', 'Tin công nghệ'];

    public const COVER_STYLES = ['circuit' => 'Mạch điện', 'processor' => 'Vi xử lý', 'graphics' => 'Đồ họa', 'workspace' => 'Góc máy'];

    protected $fillable = ['title', 'slug', 'category', 'excerpt', 'content', 'data_source', 'requirements', 'is_published', 'featured_rank', 'cover_style'];

    protected function casts(): array
    {
        return ['requirements' => 'array', 'is_published' => 'boolean'];
    }
}
