<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Services\AssistantService;
use Database\Seeders\DemoProductSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class LlmExplanationProviderTest extends TestCase
{
    use RefreshDatabase;

    private function enableApi(): void
    {
        config(['services.llm.enabled' => true, 'services.llm.api_key' => 'test-key', 'services.llm.model' => 'test-model']);
    }

    private function build(): array
    {
        return ['total' => 21900000, 'selected' => [], 'report' => ['status' => 'CONFLICT', 'rules' => [
            ['code' => 'cpu_socket', 'title' => 'Socket', 'status' => 'CONFLICT', 'message' => 'CPU khác socket mainboard.'],
        ]]];
    }

    private static function response(string $text): array
    {
        return ['status' => 'completed', 'output' => [
            ['type' => 'reasoning', 'summary' => []],
            ['type' => 'message', 'content' => [['type' => 'output_text', 'text' => $text]]],
        ]];
    }

    public function test_valid_response_only_selects_server_messages_and_sends_no_personal_data(): void
    {
        $this->enableApi();
        Http::fake(['api.openai.com/*' => Http::response(self::response('{"message_ids":["rule_cpu_socket","budget"]}'))]);
        $result = app(AssistantService::class)->analyze($this->build(), [
            'budget' => 20000000, 'purpose' => 'gaming', 'article_title' => 'private-title', 'email' => 'private@example.test',
        ], useProvider: true);
        $this->assertSame('supplemented', $result['mode']);
        $this->assertSame('CONFLICT', $result['status']);
        $this->assertStringContainsString('Socket [CONFLICT]: CPU khác socket mainboard.', $result['narrative']);
        $this->assertStringContainsString('1.900.000', $result['narrative']);
        Http::assertSent(fn ($request) => $request->url() === 'https://api.openai.com/v1/responses'
            && $request['store'] === false && $request['text']['format']['strict'] === true
            && ! str_contains($request->body(), 'private'));
        Http::assertSentCount(1);
    }

    public static function invalidResponses(): array
    {
        return [
            'HTTP error' => [[], 429],
            'server error' => [[], 500],
            'incomplete' => [['status' => 'incomplete', 'output' => []], 200],
            'empty output' => [['status' => 'completed', 'output' => []], 200],
            'refusal' => [['status' => 'completed', 'output' => [['type' => 'message', 'content' => [['type' => 'refusal']]]]], 200],
            'invalid JSON' => [self::response('PASS'), 200],
            'override status' => [self::response('{"message_ids":["budget"],"status":"PASS"}'), 200],
            'invented code' => [self::response('{"message_ids":["compatible_100_percent"]}'), 200],
            'duplicate' => [self::response('{"message_ids":["budget","budget"]}'), 200],
            'empty list' => [self::response('{"message_ids":[]}'), 200],
            'wrong type' => [self::response('{"message_ids":[1]}'), 200],
            'object instead of list' => [self::response('{"message_ids":{"first":"budget"}}'), 200],
            'too many' => [self::response('{"message_ids":["budget","purpose","rule_cpu_socket","budget"]}'), 200],
        ];
    }

    #[DataProvider('invalidResponses')]
    public function test_bad_response_falls_back_without_losing_conflict(array $body, int $status): void
    {
        $this->enableApi();
        Http::fake(['api.openai.com/*' => Http::response($body, $status)]);
        $result = app(AssistantService::class)->analyze($this->build(), [], useProvider: true);
        $this->assertSame('fallback', $result['mode']);
        $this->assertNull($result['narrative']);
        $this->assertSame('CONFLICT', $result['status']);
        $this->assertContains('Socket [CONFLICT]: CPU khác socket mainboard.', $result['messages']);
        Http::assertSentCount(1);
    }

    public function test_timeout_uses_local_analysis(): void
    {
        $this->enableApi();
        Http::fake(fn () => throw new ConnectionException('Timeout'));
        $result = app(AssistantService::class)->analyze($this->build(), [], useProvider: true);
        $this->assertSame('fallback', $result['mode']);
        $this->assertSame('CONFLICT', $result['status']);
    }

    public function test_missing_key_and_disabled_api_make_no_http_requests(): void
    {
        Http::fake();
        foreach ([['enabled' => false, 'api_key' => 'test-key'], ['enabled' => true, 'api_key' => '']] as $settings) {
            config(['services.llm' => $settings + ['model' => 'test-model']]);
            $result = app(AssistantService::class)->analyze($this->build(), [], useProvider: true);
            $this->assertSame('local', $result['mode']);
        }
        Http::assertNothingSent();
    }

    public function test_only_explicit_post_calls_api_and_price_change_invalidates_session_result(): void
    {
        $this->seed(DemoProductSeeder::class);
        $this->enableApi();
        Http::fake(['api.openai.com/*' => Http::response(self::response('{"message_ids":["budget"]}'))]);
        $cpu = Product::where('sku', 'DEMO-CPU-A')->sole();
        $this->withSession(['builder.parts' => ['cpu' => $cpu->id]])->get('/builder')->assertOk();
        Http::assertNothingSent();
        $this->post('/assistant/requirements', ['budget' => 20000000, 'purpose' => 'office'])->assertRedirect(route('builder.index').'#assistant');
        $this->get('/builder')->assertViewHas('analysis', fn ($a) => $a['mode'] === 'supplemented');
        $this->get('/builder')->assertOk();
        $this->post('/assistant/requirements', ['budget' => 20000000, 'purpose' => 'office'])->assertRedirect();
        Http::assertSentCount(1);
        $cpu->update(['price' => 4500000]);
        $this->get('/builder')->assertViewHas('analysis', fn ($a) => $a['mode'] === 'local' && $a['narrative'] === null);
        Http::assertSentCount(1);
    }

    public function test_assistant_post_is_rate_limited(): void
    {
        Http::fake();
        for ($attempt = 0; $attempt < 3; $attempt++) {
            $this->post('/assistant/requirements', ['purpose' => 'office'])->assertRedirect();
        }
        $this->post('/assistant/requirements', ['purpose' => 'office'])->assertStatus(429);
        Http::assertNothingSent();
    }
}
