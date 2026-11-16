<?php

namespace Tests\Unit;

use App\Contracts\AssistantExplanationProvider;
use App\Services\AssistantService;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class AssistantServiceTest extends TestCase
{
    private function build(): array
    {
        return ['total' => 21900000, 'selected' => [], 'report' => ['status' => 'CONFLICT', 'rules' => [
            ['code' => 'cpu_socket', 'title' => 'Socket', 'status' => 'CONFLICT', 'message' => 'CPU khác socket mainboard.'],
        ]]];
    }

    public function test_no_api_provider_uses_local_analysis(): void
    {
        $result = (new AssistantService)->analyze($this->build(), ['budget' => 20000000]);
        $this->assertSame('local', $result['mode']);
        $this->assertStringContainsString('1.900.000', $result['messages'][0]);
        $this->assertSame('CONFLICT', $result['status']);
    }

    public function test_api_failure_falls_back_without_losing_rules(): void
    {
        $provider = new class implements AssistantExplanationProvider
        {
            public function explain(array $context): array
            {
                throw new RuntimeException('API unavailable');
            }
        };
        $result = (new AssistantService($provider))->analyze($this->build(), [], useProvider: true);
        $this->assertSame('fallback', $result['mode']);
        $this->assertSame('CONFLICT', $result['status']);
        $this->assertContains('Socket [CONFLICT]: CPU khác socket mainboard.', $result['messages']);
    }

    public function test_optional_narration_cannot_override_engine_status(): void
    {
        $provider = new class implements AssistantExplanationProvider
        {
            public function explain(array $context): array
            {
                return ['PASS'];
            }
        };
        $result = (new AssistantService($provider))->analyze($this->build(), [], useProvider: true);
        $this->assertSame('CONFLICT', $result['status']);
        $this->assertSame('fallback', $result['mode']);
        $this->assertNull($result['narrative']);
    }
}
