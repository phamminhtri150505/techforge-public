<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NewsInboxItem extends Model
{
    protected $fillable = ['fingerprint', 'source', 'url', 'title', 'preview', 'source_published_at'];

    protected function casts(): array
    {
        return ['source_published_at' => 'datetime'];
    }

    public function attribution(): string
    {
        return $this->source.' — '.$this->url;
    }
}
