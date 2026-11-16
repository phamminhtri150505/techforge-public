<?php

namespace App\Services;

use App\Models\Product;
use App\Models\Promotion;
use Illuminate\Validation\ValidationException;

class RecommendationService
{
    public const ORDER = ['cpu', 'mainboard', 'ram', 'case', 'gpu', 'psu', 'storage', 'cooler'];

    public const OPTIONS_PER_TYPE = 80;

    public const BEAM_WIDTH = 48;

    private const TARGETS = [
        'office' => ['cores' => 6, 'vram_gb' => 4, 'ram' => 16, 'storage' => 500, 'weights' => [2, 0, 2, 1]],
        'gaming' => ['cores' => 8, 'vram_gb' => 12, 'ram' => 32, 'storage' => 1000, 'weights' => [2, 4, 2, 1]],
        'creative' => ['cores' => 12, 'vram_gb' => 16, 'ram' => 64, 'storage' => 2000, 'weights' => [4, 2, 3, 2]],
    ];

    public function __construct(private BuilderService $builder, private CompatibilityService $compatibility) {}

    public function recommend(array $requirements, array $owned): array
    {
        $owned = array_filter($owned, static fn ($id) => $id !== null && $id !== '');
        $existing = $this->builder->build($owned);
        foreach ($existing['selected'] as $product) {
            if (str_starts_with($product->sku, 'DEMO-')) {
                throw ValidationException::withMessages(['owned' => 'Hãy chọn đúng mã linh kiện đang có trong danh mục.']);
            }
        }
        $result = ['proposals' => [], 'message' => null, 'evaluated' => 0, 'limited' => false];
        if ($existing['report']['status'] === 'CONFLICT') {
            $conflicts = array_column(array_filter($existing['report']['rules'], fn ($rule) => $rule['status'] === 'CONFLICT'), 'message');

            return array_replace($result, ['message' => 'Linh kiện giữ lại đang xung đột. '.implode(' ', $conflicts).' Hãy bỏ chọn món bạn có thể thay.']);
        }

        $promotions = Promotion::where('is_active', true)->where('starts_at', '<=', now())
            ->where('ends_at', '>=', now())->orderBy('id')->get();
        $maxDiscount = (int) $promotions->max('discount_amount');
        $choices = [];
        $minimumPrices = [];
        foreach (self::ORDER as $type) {
            $target = $this->minimumCapacity($type, $requirements);
            if (isset($existing['selected'][$type])) {
                if (($existing['selected'][$type]->specifications['capacity_gb'] ?? 0) < $target) {
                    return array_replace($result, ['message' => ProductSpecificationSchema::TYPES[$type].' đang giữ chưa đủ dung lượng hoặc thiếu thông số. Hãy điều chỉnh mục tiêu hoặc cho phép thay linh kiện.']);
                }

                continue;
            }
            $catalog = Product::where('is_active', true)->where('type', $type)->where('sku', 'not like', 'DEMO-%');
            $priced = (clone $catalog)->where('availability', 'orderable')->where('price_kind', '!=', 'unknown');
            $options = (clone $priced)->when($target > 0, fn ($query) => $query->where('specifications->capacity_gb', '>=', $target))
                ->orderBy('price')->orderBy('id')->limit(self::OPTIONS_PER_TYPE + 1)->get();
            if ($options->isEmpty()) {
                $label = ProductSpecificationSchema::TYPES[$type];
                $reason = match (true) {
                    ! $catalog->exists() => "Danh mục chưa có {$label} đang hoạt động.",
                    ! $priced->exists() => "{$label} chưa có mẫu được nhận đặt mô phỏng với giá đã biết.",
                    default => "{$label} chưa có mẫu đủ dung lượng theo yêu cầu.",
                };

                return array_replace($result, ['message' => $reason.' Hãy điều chỉnh yêu cầu hoặc chọn linh kiện bạn đã có.']);
            }
            $result['limited'] = $result['limited'] || $options->count() > self::OPTIONS_PER_TYPE;
            $minimumPrices[$type] = $options->first()->price;
            $choices[$type] = $options->take(self::OPTIONS_PER_TYPE);
        }
        $remaining = array_sum($minimumPrices);
        $budget = (int) $requirements['budget'];
        if ($remaining - $maxDiscount > $budget) {
            return array_replace($result, ['message' => 'Ngân sách thấp hơn chi phí tối thiểu của các nhóm cần mua. Hãy tăng ngân sách hoặc chọn linh kiện đã có.']);
        }

        $beam = [['selected' => $existing['selected'], 'cost' => 0]];
        foreach ($choices as $type => $options) {
            $remaining -= $minimumPrices[$type];
            $next = [];
            foreach ($beam as $candidate) {
                foreach ($options as $option) {
                    $cost = $candidate['cost'] + $option->price;
                    if ($cost + $remaining - $maxDiscount > $budget) {
                        break;
                    }
                    $selected = $candidate['selected'] + [$type => $option];
                    $report = $this->compatibility->check(array_map(fn (Product $product) => $product->specifications, $selected));
                    $result['evaluated']++;
                    if ($report['status'] !== 'CONFLICT') {
                        $next[] = $this->ranked($selected, $cost, $report, $requirements['purpose']);
                    }
                }
            }
            if ($next === []) {
                return array_replace($result, ['message' => 'Chưa tìm được tổ hợp đủ nhóm trong phạm vi tìm kiếm. Hãy tăng ngân sách hoặc bỏ chọn linh kiện đã có mà bạn có thể thay.']);
            }
            if ($type === array_key_last($choices)) {
                $beam = $next;

                break;
            }
            $result['limited'] = $result['limited'] || count($next) > self::BEAM_WIDTH;
            $beam = $this->keepCandidates($next);
        }

        $qualified = [];
        foreach ($beam as $candidate) {
            $selected = $candidate['selected'];
            $build = $this->builder->buildFromProducts(array_map(fn (Product $product) => $product->id, $selected), collect($selected));
            $build = $this->builder->withPurchaseCosts($build, array_keys($owned), $promotions);
            if ($build['total'] <= $budget) {
                $qualified[] = $this->summarize($build, $requirements);
            }
        }
        $result['proposals'] = $this->chooseProposals($qualified, $budget);
        if ($result['proposals'] === []) {
            $result['message'] = 'Chi phí các phương án đã xét vẫn vượt ngân sách sau ưu đãi. Hãy tăng ngân sách hoặc giảm yêu cầu dung lượng.';
        }

        return $result;
    }

    public function review(array $proposal, array $requirements): array
    {
        $build = $this->builder->withPurchaseCosts($this->builder->build($proposal['parts']), $proposal['owned']);
        foreach ($build['selected'] as $type => $product) {
            if ((! in_array($type, $proposal['owned'], true) && ($product->availability !== 'orderable' || $product->price_kind === 'unknown'))
                || ($product->specifications['capacity_gb'] ?? 0) < $this->minimumCapacity($type, $requirements)) {
                throw ValidationException::withMessages(['recommendation' => 'Danh mục đã thay đổi. Hãy đề xuất lại trước khi áp dụng.']);
            }
        }
        if (! $build['report']['selection_complete'] || $build['report']['status'] === 'CONFLICT' || $build['total'] > $requirements['budget']) {
            throw ValidationException::withMessages(['recommendation' => 'Phương án đã thay đổi hoặc vượt ngân sách. Hãy đề xuất lại.']);
        }

        return $this->summarize($build, $requirements);
    }

    private function minimumCapacity(string $type, array $requirements): int
    {
        return match ($type) {
            'ram' => (int) ($requirements['min_ram_gb'] ?? 0),
            'storage' => (int) ($requirements['min_storage_gb'] ?? 0),
            default => 0,
        };
    }

    private function ranked(array $selected, int $cost, array $report, string $purpose): array
    {
        $profile = self::TARGETS[$purpose];
        $capacity = [
            $selected['cpu']->specifications['cores'] ?? 0,
            $selected['gpu']->specifications['vram_gb'] ?? 0,
            $selected['ram']->specifications['capacity_gb'] ?? 0,
            $selected['storage']->specifications['capacity_gb'] ?? 0,
        ];
        $usage = 0;
        foreach (['cores', 'vram_gb', 'ram', 'storage'] as $i => $field) {
            $usage += intdiv(10 * $profile['weights'][$i] * min($capacity[$i], $profile[$field]), $profile[$field]);
        }
        $warnings = count(array_filter($report['rules'], fn ($rule) => $rule['status'] === 'WARNING'));
        $score = $usage + 3 * $report['checked_count'] - 5 * $warnings;
        $key = implode(':', array_map(fn (Product $product) => $product->id, $selected));

        return compact('selected', 'cost', 'score', 'usage', 'key', 'warnings');
    }

    private function keepCandidates(array $candidates): array
    {
        usort($candidates, fn ($a, $b) => [$a['cost'], -$a['score'], $a['key']] <=> [$b['cost'], -$b['score'], $b['key']]);
        $kept = array_column(array_slice($candidates, 0, intdiv(self::BEAM_WIDTH, 2)), null, 'key');
        usort($candidates, fn ($a, $b) => [-$a['score'], $a['cost'], $a['key']] <=> [-$b['score'], $b['cost'], $b['key']]);
        foreach ($candidates as $candidate) {
            $kept[$candidate['key']] = $candidate;
            if (count($kept) === self::BEAM_WIDTH) {
                break;
            }
        }

        return array_values($kept);
    }

    private function summarize(array $build, array $requirements): array
    {
        $selected = $build['selected'];
        $rank = $this->ranked($selected, $build['total'], $build['report'], $requirements['purpose']);
        $items = array_map(fn (Product $product) => [
            'id' => $product->id, 'name' => $product->name, 'sku' => $product->sku, 'price' => $product->price,
            'price_label' => Product::PRICE_KINDS[$product->price_kind],
        ], $selected);
        $parts = array_map(fn (Product $product) => $product->id, $selected);
        $warnings = array_values(array_filter($build['report']['rules'], fn ($rule) => $rule['status'] === 'WARNING'));
        $specifications = array_map(fn (Product $product) => $product->specifications, $selected);
        $fingerprint = hash('sha256', json_encode([$items, $specifications, $build['owned'], $build['discount'], $build['report']], JSON_THROW_ON_ERROR));

        return [
            'parts' => $parts, 'owned' => $build['owned'], 'items' => $items, 'total' => $build['total'],
            'subtotal' => $build['subtotal'], 'discount' => $build['discount']['amount'],
            'status' => $build['report']['status'], 'checked' => $build['report']['checked_count'], 'warnings' => $warnings,
            'key' => $rank['key'], 'score' => $rank['score'], 'usage' => $rank['usage'],
            'cores' => $selected['cpu']->specifications['cores'] ?? null,
            'vram' => $selected['gpu']->specifications['vram_gb'] ?? null,
            'ram' => $selected['ram']->specifications['capacity_gb'] ?? null,
            'storage' => $selected['storage']->specifications['capacity_gb'] ?? null,
            'fingerprint' => $fingerprint,
        ];
    }

    private function chooseProposals(array $candidates, int $budget): array
    {
        $total = fn ($candidate) => $candidate['total'];
        $balance = fn ($candidate) => $candidate['score'] - intdiv(50 * $candidate['total'], $budget);
        $ranking = [
            'Tiết kiệm chi phí' => fn ($candidate) => [$total($candidate), -$candidate['score'], $candidate['key']],
            'Cân bằng chi phí và nhu cầu' => fn ($candidate) => [-$balance($candidate), $total($candidate), $candidate['key']],
            'Ưu tiên nhu cầu sử dụng' => fn ($candidate) => [-$candidate['score'], $total($candidate), $candidate['key']],
        ];
        $reasons = [
            'Tiết kiệm chi phí' => 'Ưu tiên chi phí mua thấp nhất trong các phương án đã xét.',
            'Cân bằng chi phí và nhu cầu' => 'Kết hợp điểm nhu cầu, mức độ kiểm tra tương thích và tỷ lệ sử dụng ngân sách.',
            'Ưu tiên nhu cầu sử dụng' => 'Ưu tiên điểm thông số và mức độ kiểm tra tương thích trong giới hạn ngân sách.',
        ];
        $proposals = [];
        $seen = [];
        foreach ($ranking as $label => $rank) {
            usort($candidates, fn ($a, $b) => $rank($a) <=> $rank($b));
            foreach ($candidates as $candidate) {
                $core = implode(':', array_intersect_key($candidate['parts'], array_flip(['cpu', 'mainboard', 'gpu', 'ram', 'storage'])));
                if (! isset($seen[$core])) {
                    $seen[$core] = true;
                    $proposals[] = $candidate + ['label' => $label, 'reason' => $reasons[$label], 'remaining' => $budget - $candidate['total']];

                    break;
                }
            }
        }

        return $proposals;
    }
}
