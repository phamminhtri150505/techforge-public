<?php

namespace App\Services;

use App\Contracts\AssistantExplanationProvider;
use RuntimeException;
use Throwable;

class AssistantService
{
    public const PURPOSES = ['office' => 'Học tập / văn phòng', 'gaming' => 'Chơi game', 'creative' => 'Đồ họa / sáng tạo'];

    public function __construct(private ?AssistantExplanationProvider $provider = null) {}

    public function analyze(array $build, array $requirements, bool $useProvider = false): array
    {
        $budget = $requirements['budget'] ?? null;
        $messages = [];
        if ($build['unpriced_types'] ?? $build['unpriced_selection'] ?? []) {
            $messages['budget'] = 'Chưa đủ giá cho phần cần mua; số tiền hiện tại chỉ tạm tính các linh kiện đã có giá. Cần liên hệ xác minh trước khi đối chiếu ngân sách cả phương án.';
        } elseif ($budget && empty($build['selected']) && $build['total'] === 0) {
            $messages['budget'] = 'Chưa có linh kiện để đối chiếu ngân sách. Hãy chọn linh kiện trong Builder hoặc dùng nút Đề xuất cấu hình.';
        } elseif ($budget) {
            $difference = $budget - $build['total'];
            $messages['budget'] = $difference >= 0
                ? 'Tạm còn '.number_format($difference, 0, ',', '.').' ₫ trong ngân sách. Nếu chưa chọn đủ linh kiện, đây chưa phải chi phí cả bộ.'
                : 'Vượt ngân sách '.number_format(abs($difference), 0, ',', '.').' ₫. Cân nhắc thay linh kiện theo giá trong danh mục.';
        } else {
            $messages['budget'] = 'Nhập ngân sách để so sánh với chi phí phần cần mua, sau ưu đãi hiện tại. Linh kiện đã có không cộng vào khoản này.';
        }
        $messages['purpose'] = match ($requirements['purpose'] ?? 'office') {
            'gaming' => 'Nhu cầu chơi game: cần thêm tên game, độ phân giải và mục tiêu FPS để đánh giá hiệu năng. Chưa có dữ liệu đo để kết luận FPS hay nghẽn cổ chai.',
            'creative' => 'Nhu cầu sáng tạo: đối chiếu yêu cầu của phần mềm, dung lượng RAM và không gian lưu trữ. Chưa có benchmark để kết luận hiệu năng.',
            default => 'Nhu cầu học tập/văn phòng: ưu tiên mức chi hợp lý, dung lượng RAM và SSD theo phần mềm sử dụng.',
        };
        foreach (['ram' => 'min_ram_gb', 'storage' => 'min_storage_gb'] as $type => $key) {
            if (! empty($requirements[$key])) {
                $capacity = $build['selected'][$type]->specifications['capacity_gb'] ?? null;
                $label = ProductSpecificationSchema::TYPES[$type];
                $messages[$type] = $capacity === null ? "$label chưa đủ dữ liệu để đối chiếu nhu cầu."
                    : "$label: {$capacity} GB / mục tiêu {$requirements[$key]} GB. ".($capacity >= $requirements[$key] ? 'Đạt mục tiêu dung lượng.' : 'Chưa đạt mục tiêu dung lượng.');
            }
        }
        foreach ($build['report']['rules'] as $rule) {
            if ($rule['status'] !== 'PASS') {
                $messages['rule_'.$rule['code']] = $rule['title'].' ['.$rule['status'].']: '.$rule['message'];
            }
        }
        $result = ['mode' => 'local', 'messages' => array_values($messages), 'narrative' => null, 'status' => $build['report']['status']];
        if ($useProvider && $this->provider) {
            try {
                // LLM chỉ chọn mã. PHP xác minh rồi lấy nguyên văn thông điệp đã tính.
                $ids = $this->provider->explain([
                    'requirements' => array_intersect_key($requirements, array_flip(['budget', 'purpose', 'min_ram_gb', 'min_storage_gb'])),
                    'total' => $build['total'], 'status' => $build['report']['status'], 'messages' => $messages,
                ]);
                if (! array_is_list($ids) || count($ids) < 1 || count($ids) > 3) {
                    throw new RuntimeException('Danh sách ưu tiên không hợp lệ.');
                }
                foreach ($ids as $id) {
                    if (! is_string($id) || ! array_key_exists($id, $messages)) {
                        throw new RuntimeException('Mã thông điệp không thuộc kết quả hiện tại.');
                    }
                }
                if (count(array_unique($ids)) !== count($ids)) {
                    throw new RuntimeException('Mã thông điệp bị trùng.');
                }
                $result['narrative'] = implode("\n", array_map(fn ($id) => $messages[$id], $ids));
                $result['mode'] = 'supplemented';
            } catch (Throwable) {
                $result['mode'] = 'fallback';
            }
        }

        return $result;
    }

    public function fingerprint(array $build, array $requirements): string
    {
        // Đổi lựa chọn, thông số, giá hoặc nhu cầu thì không tái sử dụng kết quả API cũ.
        $parts = [];
        foreach ($build['selected'] as $type => $product) {
            $parts[$type] = [$product->id, $product->price, $product->price_kind, $product->specifications];
        }

        return hash('sha256', json_encode([$parts, $build['total'], $build['report'], $requirements,
            $build['owned'] ?? [], $build['discount'] ?? null], JSON_THROW_ON_ERROR));
    }
}
