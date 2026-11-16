<?php

namespace App\Services;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class ArticleRequirementsService
{
    public function rules(): array
    {
        return [
            'requirements' => 'nullable|array:budget,purpose,min_ram_gb,min_storage_gb',
            'requirements.budget' => 'nullable|integer|min:1000000|max:1000000000',
            'requirements.purpose' => ['required_with:requirements', Rule::in(array_keys(AssistantService::PURPOSES))],
            'requirements.min_ram_gb' => 'nullable|integer|min:1|max:1024',
            'requirements.min_storage_gb' => 'nullable|integer|min:1|max:100000',
        ];
    }

    public function validated(?array $requirements): ?array
    {
        $validator = Validator::make(['requirements' => $requirements], $this->rules());
        if (! $requirements || $validator->fails()) {
            return null;
        }

        return $this->normalize($requirements);
    }

    public function needsConfirmation(array $current, array $incoming): bool
    {
        return $current !== [] && $this->normalize($current) !== $incoming;
    }

    /** Gắn xác nhận với đúng nhu cầu cũ và yêu cầu bài đang xem, tránh ghi đè từ tab cũ. */
    public function transferToken(array $current, array $incoming): string
    {
        return hash('sha256', json_encode([$current, $incoming], JSON_THROW_ON_ERROR));
    }

    private function normalize(array $requirements): array
    {
        $data = array_intersect_key($requirements, array_flip(['budget', 'purpose', 'min_ram_gb', 'min_storage_gb']));
        foreach ($data as $key => $value) {
            if ($key !== 'purpose' && $value !== null) {
                $data[$key] = (int) $value;
            }
        }
        ksort($data);

        return $data;
    }
}
