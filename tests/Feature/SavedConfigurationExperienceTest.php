<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\SavedConfiguration;
use App\Models\User;
use Database\Seeders\DemoProductSeeder;
use DOMDocument;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class SavedConfigurationExperienceTest extends TestCase
{
    use RefreshDatabase;

    private function saved(User $owner, string $name = 'Bản lưu của tôi', ?string $token = null): SavedConfiguration
    {
        $saved = new SavedConfiguration(['name' => $name, 'parts' => ['cpu' => 1], 'owned_types' => ['cpu']]);
        $saved->user_id = $owner->id;
        $saved->share_token = $token;
        $saved->save();

        return $saved;
    }

    private function xpath(string $html): DOMXPath
    {
        $document = new DOMDocument;
        @$document->loadHTML('<?xml encoding="utf-8" ?>'.$html);

        return new DOMXPath($document);
    }

    public function test_empty_list_requires_login_and_keeps_the_starting_link(): void
    {
        $this->get('/configurations')->assertRedirect('/login');
        $this->actingAs(User::factory()->create())->get('/configurations')->assertOk()
            ->assertSee('Bạn chưa lưu cấu hình nào.')->assertSee('Bắt đầu chọn linh kiện')
            ->assertSee(route('builder.index'));
        $this->assertDatabaseCount('saved_configurations', 0);
        Http::assertNothingSent();
    }

    public function test_owner_list_is_private_escaped_and_does_not_change_builder_or_cart(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $this->saved($other, 'Không được đọc bản này');
        $mine = $this->saved($owner, '<script>alert(1)</script> '.str_repeat('x', 70));
        $builder = ['parts' => ['cpu' => 999], 'owned' => ['cpu'], 'requirements' => ['budget' => 20000000]];
        $cart = ['items' => [999 => 2]];
        $response = $this->actingAs($owner)->withSession(['builder' => $builder, 'cart' => $cart])
            ->get('/configurations')->assertOk()->assertDontSee('Không được đọc bản này')
            ->assertSee($mine->name)->assertDontSee('<script>alert(1)</script>', false)
            ->assertSessionHas('builder', $builder)->assertSessionHas('cart', $cart)
            ->assertHeader('Referrer-Policy', 'no-referrer')->assertHeader('X-Robots-Tag', 'noindex, nofollow');
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $this->assertStringContainsString('private', $response->headers->get('Cache-Control'));
        $this->assertDatabaseCount('saved_configurations', 2);
        Http::assertNothingSent();
    }

    public function test_all_changes_are_inside_closed_confirmation_panels_with_csrf_and_cancel_cannot_submit(): void
    {
        $owner = User::factory()->create();
        $saved = $this->saved($owner);
        $response = $this->actingAs($owner)->get('/configurations')->assertOk()
            ->assertSee('thay bằng bản lưu này')->assertSee('không thể khôi phục')
            ->assertSee('Ai có liên kết cũng xem được');
        $xpath = $this->xpath($response->getContent());
        foreach ([['load', 'POST'], ['destroy', 'DELETE'], ['share', 'POST']] as [$action, $method]) {
            $forms = $xpath->query('//form[@action="'.route('configurations.'.$action, $saved).'"]');
            $this->assertSame(1, $forms->length);
            $form = $forms->item(0);
            $this->assertSame(1, $xpath->query('ancestor::details[not(@open)]', $form)->length);
            $this->assertSame(1, $xpath->query('.//input[@name="_token"]', $form)->length);
            $this->assertSame(1, $xpath->query('.//button[@type="reset" and @data-cancel-saved-action]', $form)->length);
            if ($method === 'DELETE') {
                $this->assertSame(1, $xpath->query('.//input[@name="_method" and @value="DELETE"]', $form)->length);
            }
        }
        $this->assertDatabaseHas('saved_configurations', ['id' => $saved->id, 'share_token' => null]);
    }

    public function test_existing_share_link_is_collapsed_and_revoke_has_its_own_confirmation(): void
    {
        $owner = User::factory()->create();
        $saved = $this->saved($owner, token: str_repeat('a', 64));
        $response = $this->actingAs($owner)->get('/configurations')->assertOk()
            ->assertSee('Đang chia sẻ')->assertSee('Xem liên kết chia sẻ')->assertSee('Xác nhận thu hồi')
            ->assertSee('những bản sao người nhận đã tạo sẽ không bị xóa');
        $xpath = $this->xpath($response->getContent());
        $this->assertSame(1, $xpath->query('//details[contains(@class,"saved-share-link") and not(@open)]//input[@readonly]')->length);
        $form = $xpath->query('//form[@action="'.route('configurations.unshare', $saved).'"]')->item(0);
        $this->assertSame(1, $xpath->query('ancestor::details[not(@open)]', $form)->length);
        $this->assertSame(str_repeat('a', 64), $saved->fresh()->share_token);
    }

    public function test_pagination_keeps_other_users_records_out_of_every_page(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        for ($index = 0; $index < 11; $index++) {
            $saved = $this->saved($owner, 'Cấu hình '.$index);
            $saved->created_at = now()->subMinutes($index);
            $saved->save();
        }
        $this->saved($other, 'Bản của người khác');
        $first = $this->actingAs($owner)->get('/configurations')->assertOk()->assertDontSee('Bản của người khác');
        $this->assertCount(10, $first->viewData('configurations')->items());
        $this->assertTrue($first->viewData('configurations')->hasMorePages());
        $second = $this->get('/configurations?page=2')->assertOk()->assertDontSee('Bản của người khác');
        $this->assertCount(1, $second->viewData('configurations')->items());
        $this->assertSame('Cấu hình 10', $second->viewData('configurations')->first()->name);
    }

    #[DataProvider('invalidSavedParts')]
    public function test_invalid_saved_product_cannot_replace_current_parts_owned_needs_or_cart(string $change): void
    {
        $this->seed(DemoProductSeeder::class);
        $owner = User::factory()->create();
        $cpu = Product::where('sku', 'DEMO-CPU-A')->sole();
        $saved = $this->saved($owner);
        $saved->update(['parts' => ['cpu' => $cpu->id]]);
        if ($change === 'missing') {
            $cpu->delete();
        } elseif ($change === 'inactive') {
            $cpu->update(['is_active' => false]);
        } else {
            $cpu->update(['type' => 'ram', 'specifications' => []]);
        }
        $builder = ['parts' => ['cpu' => 999], 'owned' => ['cpu'], 'requirements' => ['budget' => 20000000], 'assistant' => ['old' => true]];
        $cart = ['items' => [999 => 1]];
        $this->actingAs($owner)->withSession(['builder' => $builder, 'cart' => $cart])
            ->post(route('configurations.load', $saved))->assertSessionHasErrors('parts.cpu')
            ->assertSessionHas('builder', $builder)->assertSessionHas('cart', $cart);
        $this->assertDatabaseCount('saved_configurations', 1);
        Http::assertNothingSent();
    }

    public static function invalidSavedParts(): array
    {
        return [['missing'], ['inactive'], ['wrong_type']];
    }
}
