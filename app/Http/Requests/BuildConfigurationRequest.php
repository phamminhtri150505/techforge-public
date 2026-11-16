<?php

namespace App\Http\Requests;

use App\Services\ProductSpecificationSchema;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BuildConfigurationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['parts' => ['sometimes', 'array:'.implode(',', array_keys(ProductSpecificationSchema::TYPES))],
            'parts.*' => ['nullable', 'integer', 'min:1'], 'owned' => ['sometimes', 'array'],
            'owned.*' => ['string', 'distinct', Rule::in(array_keys(ProductSpecificationSchema::TYPES))]];
    }

    public function messages(): array
    {
        return ['parts.array' => 'Danh sách lựa chọn chứa nhóm linh kiện không được hỗ trợ.',
            'parts.*.integer' => 'Mã linh kiện phải là số nguyên.', 'parts.*.min' => 'Mã linh kiện không hợp lệ.'];
    }
}
