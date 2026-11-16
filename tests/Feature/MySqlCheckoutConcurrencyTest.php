<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Services\CartService;
use App\Services\OrderService;
use App\Services\ProductSpecificationSchema;
use Database\Seeders\DemoProductSeeder;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class MySqlCheckoutConcurrencyTest extends TestCase
{
    use DatabaseMigrations;

    public function test_price_changed_by_another_connection_during_checkout_is_not_read_from_old_snapshot(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            $this->markTestSkipped('Ca này kiểm tra REPEATABLE READ và locking read của MySQL 8.4.');
        }
        $this->seed(DemoProductSeeder::class);
        $user = User::factory()->create();
        $parts = [];
        foreach (array_keys(ProductSpecificationSchema::TYPES) as $type) {
            $parts[$type] = Product::where('sku', 'DEMO-'.strtoupper($type).'-A')->sole()->id;
        }
        $service = app(OrderService::class);
        $quote = $service->quoteFingerprint(app(CartService::class)->quote($parts));
        $connectionName = DB::getDefaultConnection();
        config(['database.connections.competing' => config('database.connections.'.$connectionName)]);
        $changed = false;

        // Tạo thay đổi thật từ connection thứ hai ngay sau consistent read đầu tiên trong transaction.
        // Fixture phải committed nên test này dùng DatabaseMigrations, không dùng outer transaction.
        DB::listen(function (QueryExecuted $event) use (&$changed, $parts, $connectionName): void {
            if (! $changed && $event->connectionName === $connectionName && DB::transactionLevel() > 0
                && str_contains($event->sql, 'from `orders`')) {
                $changed = true;
                DB::connection('competing')->table('products')->where('id', $parts['cpu'])->update(['price' => 5000000]);
            }
        });

        try {
            $service->place($user, $parts, ['checkout_token' => (string) Str::uuid(), 'recipient' => 'Khách demo',
                'phone' => '0900000000', 'address' => 'Địa chỉ giả lập số 123'], $quote);
            $this->fail('Checkout phải yêu cầu xem lại giá mới, không được đọc snapshot giá cũ.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('cart', $exception->errors());
        } finally {
            DB::purge('competing');
        }
        $this->assertTrue($changed);
        $this->assertSame(5000000, Product::findOrFail($parts['cpu'])->price);
        $this->assertSame(0, Order::count());
    }
}
