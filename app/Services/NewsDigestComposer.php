<?php

namespace App\Services;

use App\Models\NewsDigest;
use App\Models\NewsInboxItem;
use Illuminate\Support\Collection;

class NewsDigestComposer
{
    /** Ưu tiên ba tin mới nhất mỗi nguồn để bản tổng hợp không bị một nguồn lấn át. */
    public function save(Collection $newItems): ?NewsDigest
    {
        if ($newItems->isEmpty()) {
            return null;
        }

        $entries = $newItems->sortByDesc('source_published_at')->groupBy('source')
            ->flatMap(fn (Collection $items) => $items->take(3)->map(fn (NewsInboxItem $item) => [
                'id' => $item->id,
                'source' => $item->source,
                'title' => $item->title,
                'url' => $item->url,
                'summary' => $this->summary($item->preview),
            ]))->values()->all();

        return NewsDigest::create(['item_count' => $newItems->count(), 'entries' => $entries]);
    }

    private function summary(?string $preview): ?string
    {
        if (blank($preview)) {
            return null;
        }

        $sentences = preg_split('/(?<=[.!?…])\s+(?=\p{Lu})/u', $preview, 3);
        $opening = implode(' ', array_slice($sentences ?: [$preview], 0, 2));

        return mb_strimwidth($opening, 0, 280, '…', 'UTF-8');
    }
}
