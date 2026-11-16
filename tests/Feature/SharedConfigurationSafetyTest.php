<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\SavedConfiguration;
use App\Models\User;
use Database\Seeders\DemoProductSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class SharedConfigurationSafetyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DemoProductSeeder::class);
        $this->withCookie(config('session.cookie'), session()->getId());
    }

    private function saved(): SavedConfiguration
    {
        $saved = new SavedConfiguration(['name' => 'Cấu hình kiểm thử', 'parts' => [
            'cpu' => Product::where('sku', 'DEMO-CPU-A')->sole()->id,
        ], 'owned_types' => ['cpu']]);
        $saved->user_id = User::factory()->create()->id;
        $saved->share_token = bin2hex(random_bytes(32));
        $saved->save();

        return $saved;
    }

    private function signature(SavedConfiguration $saved): string
    {
        $response = $this->get('/shared/'.$saved->share_token)->assertOk();

        return $response->original->getData()['selectionToken'] ?? str_repeat('0', 64);
    }

    #[DataProvider('changedStateCases')]
    public function test_old_confirmation_cannot_overwrite_a_changed_recipient_or_source(string $change): void
    {
        $saved = $this->saved();
        $cpu = $saved->parts['cpu'];
        $this->withSession(['builder.parts' => ['cpu' => $cpu], 'builder.owned' => []]);
        $signature = $this->signature($saved);
        $latest = ['parts' => ['cpu' => $cpu], 'owned' => []];
        $target = $saved;
        if ($change === 'parts') {
            $latest['parts']['cpu'] = Product::where('sku', 'DEMO-CPU-B')->sole()->id;
        } elseif ($change === 'owned') {
            $latest['owned'] = ['cpu'];
        } elseif ($change === 'source') {
            $saved->update(['parts' => ['cpu' => Product::where('sku', 'DEMO-CPU-B')->sole()->id]]);
        } elseif ($change === 'name') {
            $saved->update(['name' => 'Tên vừa thay đổi']);
        } elseif ($change === 'other_link') {
            $target = $this->saved();
        } elseif ($change === 'session') {
            session()->regenerate();
            session()->save();
            $this->withCookie(config('session.cookie'), session()->getId());
        } else {
            $signature = str_repeat('f', 64);
        }
        if (in_array($change, ['parts', 'owned'], true)) {
            // Mô phỏng tab khác qua endpoint thật, để session được lưu như trình duyệt.
            $this->post('/builder/check', $latest)->assertRedirect('/builder');
        }
        $this->post('/shared/'.$target->share_token.'/remix', ['confirm_remix' => 1, 'selection_token' => $signature])
            ->assertSessionHasErrors('shared_selection')
            ->assertSessionHas('builder.parts', $latest['parts'])->assertSessionHas('builder.owned', $latest['owned']);
        Http::assertNothingSent();
    }

    public static function changedStateCases(): array
    {
        return array_map(fn ($case) => [$case], ['parts', 'owned', 'source', 'name', 'other_link', 'session', 'forged']);
    }

    public function test_missing_signature_and_signature_array_cannot_apply_even_with_checkbox(): void
    {
        $saved = $this->saved();
        foreach ([[], ['selection_token' => ['forged']]] as $input) {
            $this->post('/shared/'.$saved->share_token.'/remix', ['confirm_remix' => 1] + $input)
                ->assertSessionHasErrors('selection_token')->assertSessionMissing('builder.parts');
        }
    }

    public function test_valid_copy_keeps_needs_and_cart_clears_ownership_and_does_not_change_original(): void
    {
        $saved = $this->saved();
        // Lấy snapshot từ DB trước thao tác: MySQL chuẩn hóa khoảng trắng JSON khi lưu.
        $before = $saved->fresh()->getRawOriginal();
        $this->withSession(['builder.owned' => ['cpu'], 'builder.requirements' => ['budget' => 20000000],
            'builder.assistant' => ['old' => true], 'cart' => ['items' => [999 => 2]]]);
        $signature = $this->signature($saved);
        $cpu = Product::findOrFail($saved->parts['cpu']);
        $cpu->update(['price' => 5000000]);
        $this->post('/shared/'.$saved->share_token.'/remix', ['confirm_remix' => 1, 'selection_token' => $signature,
            'parts' => ['gpu' => 999], 'owned' => ['cpu'], 'total' => 1, 'status' => 'PASS'])
            ->assertRedirect('/builder')->assertSessionHas('builder.parts', $saved->parts)
            ->assertSessionMissing('builder.owned')->assertSessionMissing('builder.assistant')
            ->assertSessionHas('builder.requirements', ['budget' => 20000000])->assertSessionHas('cart', ['items' => [999 => 2]]);
        $this->get('/builder')->assertOk()->assertViewHas('total', 5000000);
        $this->assertEquals($before, $saved->fresh()->getRawOriginal());
        $this->assertDatabaseCount('saved_configurations', 1);
        Http::assertNothingSent();
    }

    public function test_empty_source_is_controlled_and_cannot_erase_current_selection(): void
    {
        $saved = $this->saved();
        $saved->update(['parts' => []]);
        $this->withSession(['builder.parts' => ['cpu' => 999]])
            ->get('/shared/'.$saved->share_token)->assertOk()->assertViewHas('build', null);
        $signature = $this->signature($saved);
        $this->post('/shared/'.$saved->share_token.'/remix', ['confirm_remix' => 1, 'selection_token' => $signature])
            ->assertSessionHasErrors('shared_configuration')->assertSessionHas('builder.parts', ['cpu' => 999]);
    }

    public function test_revoked_or_malformed_link_rejects_copy_without_losing_current_selection(): void
    {
        $saved = $this->saved();
        $signature = $this->signature($saved);
        $oldToken = $saved->share_token;
        $saved->share_token = null;
        $saved->save();
        $this->withSession(['builder.parts' => ['cpu' => 999]])->post('/shared/'.$oldToken.'/remix', [
            'confirm_remix' => 1, 'selection_token' => $signature,
        ])->assertNotFound()->assertSessionHas('builder.parts', ['cpu' => 999]);
        foreach (['short', str_repeat('z', 64)] as $bad) {
            $this->get('/shared/'.$bad)->assertNotFound();
        }
    }

    public function test_repeated_copy_requests_are_limited_without_changing_recipient_selection(): void
    {
        $saved = $this->saved();
        $sharedPage = route('configurations.shared', $saved->share_token);
        $remix = route('configurations.remix', $saved->share_token);
        $this->withSession(['builder.parts' => ['cpu' => 999]]);
        for ($attempt = 0; $attempt < 10; $attempt++) {
            $this->post($remix)->assertSessionHasErrors('confirm_remix');
        }
        $this->postJson($remix)->assertTooManyRequests()->assertHeader('Retry-After');
        $this->post($remix, [], ['Accept' => 'text/html'])->assertStatus(303)
            ->assertRedirect($sharedPage)->assertSessionHas('rate_limit_notice', true)
            ->assertSessionHas('builder.parts', ['cpu' => 999]);
        Http::assertNothingSent();
    }
}
