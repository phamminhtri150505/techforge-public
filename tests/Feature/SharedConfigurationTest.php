<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\SavedConfiguration;
use App\Models\User;
use App\Services\ProductSpecificationSchema;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SharedConfigurationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    private function parts(): array
    {
        $parts = [];
        foreach (array_keys(ProductSpecificationSchema::TYPES) as $type) {
            $parts[$type] = Product::where('sku', 'DEMO-'.strtoupper($type).'-A')->sole()->id;
        }

        return $parts;
    }

    private function saved(User $owner, ?array $parts = null, bool $shared = true): SavedConfiguration
    {
        $saved = new SavedConfiguration(['name' => 'Bản PC chia sẻ', 'parts' => $parts ?? $this->parts()]);
        $saved->user_id = $owner->id;
        $saved->share_token = $shared ? bin2hex(random_bytes(32)) : null;
        $saved->save();

        return $saved;
    }

    private function copySignature(SavedConfiguration $saved): string
    {
        // Lưu thay đổi fixture như request ở tab trước đã hoàn tất, trước khi GET đọc lại phiên.
        session()->save();
        $this->withCookie(config('session.cookie'), session()->getId());

        return $this->get('/shared/'.$saved->share_token)->assertOk()->viewData('selectionToken');
    }

    public function test_saving_is_private_even_if_request_contains_a_share_token(): void
    {
        $this->actingAs(User::factory()->create())->withSession(['builder.parts' => $this->parts()])
            ->post('/configurations', ['name' => 'Riêng tư', 'share_token' => str_repeat('a', 64)])
            ->assertRedirect('/configurations');
        $this->assertNull(SavedConfiguration::sole()->share_token);
        $this->get('/shared/'.str_repeat('a', 64))->assertNotFound();
        $this->get('/shared/1')->assertNotFound();
    }

    public function test_only_owner_can_enable_or_revoke_and_repeat_enable_preserves_link(): void
    {
        $owner = User::factory()->create();
        $saved = $this->saved($owner, shared: false);
        $url = '/configurations/'.$saved->id.'/share';
        $this->post($url)->assertRedirect('/login');
        $this->delete($url)->assertRedirect('/login');
        $this->actingAs(User::factory()->create())->post($url)->assertNotFound();
        $this->delete($url)->assertNotFound();
        $this->assertNull($saved->fresh()->share_token);
        $this->actingAs($owner)->post($url)->assertRedirect('/configurations');
        $token = $saved->fresh()->share_token;
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $token);
        $this->post($url)->assertRedirect('/configurations');
        $this->assertSame($token, $saved->fresh()->share_token);
        $this->get('/configurations')->assertSee('/shared/'.$token)->assertSee('Thu hồi liên kết');
    }

    public function test_public_view_does_not_expose_owner_or_change_builder_and_never_calls_api(): void
    {
        $owner = User::factory()->create(['name' => 'PRIVATE OWNER 991', 'email' => 'private-owner-991@example.test']);
        $saved = $this->saved($owner);
        $oldParts = ['cpu' => $this->parts()['cpu']];
        $this->withSession(['builder.parts' => $oldParts])->get('/shared/'.$saved->share_token)
            ->assertOk()->assertSee($saved->name)->assertSee('21.900.000')
            ->assertDontSee($owner->name)->assertDontSee($owner->email)
            ->assertSessionHas('builder.parts', $oldParts)
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow')
            ->assertHeader('Referrer-Policy', 'no-referrer')
            ->assertViewHas('build', fn ($build) => $build['report']['status'] === 'PASS'
                && array_keys($build['selected']) === array_keys(ProductSpecificationSchema::TYPES));
        Http::assertNothingSent();
    }

    public function test_public_view_recalculates_changed_price_and_conflict(): void
    {
        $saved = $this->saved(User::factory()->create());
        $gpu = Product::findOrFail($saved->parts['gpu']);
        $specifications = $gpu->specifications;
        $specifications['length_mm'] = 600;
        $gpu->update(['price' => $gpu->price + 1000000, 'specifications' => $specifications]);
        $this->get('/shared/'.$saved->share_token)->assertOk()->assertSee('22.900.000')
            ->assertViewHas('build', fn ($build) => $build['report']['status'] === 'CONFLICT');
    }

    public function test_partial_draft_keeps_incomplete_warning_and_name_is_escaped(): void
    {
        $saved = $this->saved(User::factory()->create(), ['cpu' => $this->parts()['cpu']]);
        $saved->update(['name' => '<script>alert(1)</script>']);
        $this->get('/shared/'.$saved->share_token)->assertOk()
            ->assertSee('&lt;script&gt;', false)->assertDontSee('<script>alert(1)</script>', false)
            ->assertSee('Cấu hình chưa hoàn chỉnh')
            ->assertViewHas('build', fn ($build) => $build['report']['status'] === 'WARNING'
                && ! $build['report']['selection_complete']);
    }

    public function test_guest_remix_needs_confirmation_and_ignores_submitted_parts_and_prices(): void
    {
        $saved = $this->saved(User::factory()->create());
        $oldParts = ['cpu' => $this->parts()['cpu']];
        $url = '/shared/'.$saved->share_token.'/remix';
        $this->withSession(['builder.parts' => $oldParts])->post($url)
            ->assertSessionHasErrors('confirm_remix')->assertSessionHas('builder.parts', $oldParts);
        $this->withSession(['builder.requirements' => ['budget' => 18000000], 'builder.assistant' => ['old' => true]]);
        $signature = $this->copySignature($saved);
        $this->post($url, ['confirm_remix' => 1, 'selection_token' => $signature, 'parts' => [], 'total' => 1])
            ->assertRedirect('/builder')->assertSessionHas('builder.parts', $saved->parts)
            ->assertSessionHas('builder.requirements.budget', 18000000)->assertSessionMissing('builder.assistant');
        // JSON là ánh xạ: MySQL không bảo đảm thứ tự khóa như SQLite.
        $this->assertEquals($saved->parts, $saved->fresh()->parts);
        $this->assertDatabaseCount('saved_configurations', 1);
        $this->get('/builder')->assertViewHas('total', 21900000);
    }

    public function test_remix_saved_by_recipient_is_independent_and_private_after_original_revoked(): void
    {
        $owner = User::factory()->create();
        $recipient = User::factory()->create();
        $saved = $this->saved($owner);
        $this->actingAs($recipient);
        $signature = $this->copySignature($saved);
        $this->post('/shared/'.$saved->share_token.'/remix', ['confirm_remix' => 1, 'selection_token' => $signature])->assertRedirect('/builder');
        $parts = $saved->parts;
        unset($parts['gpu']);
        $this->post('/builder/check', ['parts' => $parts])->assertRedirect('/builder');
        $this->post('/configurations', ['name' => 'Bản riêng'])->assertRedirect('/configurations');
        $copy = SavedConfiguration::where('user_id', $recipient->id)->sole();
        $this->assertNull($copy->share_token);
        $this->assertEquals($parts, $copy->parts);
        $this->assertCount(8, $saved->fresh()->parts);
        $this->actingAs($owner)->delete('/configurations/'.$saved->id.'/share')->assertRedirect('/configurations');
        $this->actingAs($recipient)->post('/configurations/'.$copy->id.'/load')->assertRedirect('/builder');
    }

    public function test_revoking_or_deleting_blocks_old_link_and_reenabling_uses_new_token(): void
    {
        $owner = User::factory()->create();
        $saved = $this->saved($owner);
        $oldUrl = '/shared/'.$saved->share_token;
        $controlUrl = '/configurations/'.$saved->id.'/share';
        $this->actingAs($owner)->delete($controlUrl)->assertRedirect('/configurations');
        $this->get($oldUrl)->assertNotFound();
        $this->post($oldUrl.'/remix', ['confirm_remix' => 1])->assertNotFound();
        $this->post($controlUrl)->assertRedirect('/configurations');
        $newUrl = '/shared/'.$saved->fresh()->share_token;
        $this->assertNotSame($oldUrl, $newUrl);
        $this->get($oldUrl)->assertNotFound();
        $this->get($newUrl)->assertOk();
        $this->delete('/configurations/'.$saved->id)->assertRedirect();
        $this->get($newUrl)->assertNotFound();
        $this->post($newUrl.'/remix', ['confirm_remix' => 1])->assertNotFound();
    }

    public function test_inactive_product_blocks_copy_without_losing_existing_selection(): void
    {
        $saved = $this->saved(User::factory()->create());
        $url = '/shared/'.$saved->share_token;
        $this->get($url)->assertOk();
        Product::findOrFail($saved->parts['gpu'])->update(['is_active' => false]);
        $this->get($url)->assertOk()->assertViewHas('build', null)
            ->assertSee('chưa thể sao chép')->assertDontSee('Sao chép vào Builder');
        $oldParts = ['cpu' => $this->parts()['cpu']];
        $this->withSession(['builder.parts' => $oldParts]);
        $signature = $this->copySignature($saved);
        $this->post($url.'/remix', ['confirm_remix' => 1, 'selection_token' => $signature])
            ->assertSessionHasErrors('parts.gpu')->assertSessionHas('builder.parts', $oldParts);
    }
}
