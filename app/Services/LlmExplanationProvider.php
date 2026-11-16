<?php

namespace App\Services;

use App\Contracts\AssistantExplanationProvider;
use Illuminate\Support\Facades\Http;
use RuntimeException;

final class LlmExplanationProvider implements AssistantExplanationProvider
{
    public function explain(array $context): array
    {
        $key = config('services.llm.api_key');
        $model = config('services.llm.model');
        if (! config('services.llm.enabled') || blank($key) || blank($model)) {
            throw new RuntimeException('Dịch vụ giải thích chưa được cấu hình.');
        }

        // Chỉ gửi thông điệp PHP và nhu cầu đã lọc; không gửi tài khoản/địa chỉ/đơn hàng.
        // Không retry tự động: giới hạn thời gian chờ và số lần gọi có thể tính phí.
        $response = Http::withToken($key)->acceptJson()->connectTimeout(3)->timeout(8)
            ->withoutRedirecting()->post('https://api.openai.com/v1/responses', [
                'model' => $model,
                'store' => false,
                'max_output_tokens' => 256,
                'reasoning' => ['effort' => 'none'],
                'instructions' => 'Chọn tối đa 3 mã thông điệp cần ưu tiên cho người xây PC. '
                    .'Ưu tiên xung đột kỹ thuật, thiếu dữ liệu, rồi ngân sách và nhu cầu. '
                    .'Chỉ chọn mã trong messages. Không quyết định tương thích hay sinh lời khuyên mới.',
                'input' => json_encode($context, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                'text' => ['format' => [
                    'type' => 'json_schema', 'name' => 'techforge_priorities', 'strict' => true,
                    'schema' => [
                        'type' => 'object', 'additionalProperties' => false,
                        'properties' => ['message_ids' => [
                            'type' => 'array', 'minItems' => 1, 'maxItems' => 3,
                            'items' => ['type' => 'string', 'enum' => array_keys($context['messages'])],
                        ]],
                        'required' => ['message_ids'],
                    ],
                ]],
            ]);

        if (! $response->successful() || $response->json('status') !== 'completed') {
            // Không đưa nội dung lỗi của nhà cung cấp (có thể chứa dữ liệu) vào log/giao diện.
            throw new RuntimeException('Dịch vụ giải thích chưa hoàn thành yêu cầu.');
        }

        $texts = [];
        foreach ($response->json('output', []) as $item) {
            if (($item['type'] ?? null) !== 'message') {
                continue;
            }
            foreach ($item['content'] ?? [] as $content) {
                if (($content['type'] ?? null) === 'refusal') {
                    throw new RuntimeException('Dịch vụ từ chối yêu cầu.');
                }
                if (($content['type'] ?? null) === 'output_text') {
                    $texts[] = $content['text'] ?? '';
                }
            }
        }
        if (count($texts) !== 1 || ! is_string($texts[0]) || strlen($texts[0]) > 4000) {
            throw new RuntimeException('Phản hồi không đúng cấu trúc.');
        }
        $payload = json_decode($texts[0], true, 8, JSON_THROW_ON_ERROR);
        if (! is_array($payload) || array_keys($payload) !== ['message_ids'] || ! is_array($payload['message_ids'])) {
            throw new RuntimeException('Phản hồi chứa trường không được phép.');
        }

        // AssistantService tiếp tục kiểm tra danh sách và từng mã trước khi dựng câu chữ.
        return $payload['message_ids'];
    }
}
