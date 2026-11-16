<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AdminOrderInterfaceTest extends TestCase
{
    use RefreshDatabase;

    #[DataProvider('statusCases')]
    public function test_order_form_only_offers_allowed_next_steps(string $status, array $expected): void
    {
        $admin = User::factory()->create();
        $admin->forceFill(['is_admin' => true])->save();
        $order = Order::create([
            'user_id' => $admin->id, 'checkout_token' => (string) Str::uuid(),
            'recipient' => 'Khách QA', 'phone' => '0900000000', 'address' => 'Địa chỉ giả lập',
            'status' => $status, 'payment_method' => 'COD_SIMULATED', 'total' => 100000,
            'compatibility_snapshot' => ['status' => 'NOT_CHECKED', 'rules' => []],
        ]);
        $response = $this->actingAs($admin)->get('/admin')->assertOk();
        $document = new \DOMDocument;
        @$document->loadHTML('<?xml encoding="utf-8" ?>'.$response->getContent());
        $xpath = new \DOMXPath($document);
        $select = $xpath->query('//select[@aria-label="Trạng thái đơn '.$order->id.'"]');
        if ($expected === []) {
            $this->assertSame(0, $select->length);
            $response->assertSee('Đã kết thúc');
        } else {
            $this->assertSame(1, $select->length);
            $values = [];
            foreach ($xpath->query('.//option[string-length(@value)>0]', $select->item(0)) as $option) {
                $values[] = $option->getAttribute('value');
            }
            $this->assertSame($expected, $values);
        }
        // Giả mạo request vẫn phải bị chặn, dù bỏ qua danh sách chọn trên giao diện.
        $this->patch('/admin/orders/'.$order->id, ['status' => 'pending'])->assertSessionHasErrors('status');
        $this->assertSame($status, $order->fresh()->status);
    }

    public static function statusCases(): array
    {
        return [
            ['pending', ['confirmed', 'cancelled']],
            ['confirmed', ['shipping', 'cancelled']],
            ['shipping', ['completed']],
            ['completed', []],
            ['cancelled', []],
        ];
    }
}
