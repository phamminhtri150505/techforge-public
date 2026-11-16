<?php

namespace App\Contracts;

interface AssistantExplanationProvider
{
    /** Trả danh sách mã thông điệp ưu tiên; không trả văn bản hay kết luận kỹ thuật tự do. */
    public function explain(array $context): array;
}
