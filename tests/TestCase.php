<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Http;

abstract class TestCase extends BaseTestCase
{
    public function createApplication()
    {
        $app = parent::createApplication();
        // Test không bao giờ vô tình gọi API trả phí qua khóa ở .env cục bộ.
        $app['config']->set('services.llm.enabled', false);
        Http::preventStrayRequests();
        // Đọc cấu hình kết nối sau khi Laravel đã xử lý cả DB_URL, trước RefreshDatabase.
        $connection = $app['db']->connection()->getDriverName();
        $database = $app['db']->connection()->getDatabaseName();
        if (! (($connection === 'sqlite' && $database === ':memory:') || ($connection === 'mysql' && $database === 'techforge_test'))) {
            throw new \RuntimeException('Kiểm thử chỉ được dùng SQLite :memory: hoặc database techforge_test riêng.');
        }

        return $app;
    }
}
