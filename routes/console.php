<?php

use App\Mail\NewsDigestMail;
use App\Models\NewsInboxItem;
use App\Models\User;
use App\Services\NewsDigestComposer;
use App\Services\NewsFeedCollector;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\Schema;

Artisan::command('techforge:collect-news {--dry-run : Chỉ kiểm tra nguồn, không lưu tin}', function (NewsFeedCollector $collector, NewsDigestComposer $composer) {
    $preview = $this->option('dry-run');
    if (! $preview && (! Schema::hasTable('news_inbox_items') || ! Schema::hasTable('news_digests'))) {
        $this->error('Hãy cập nhật cơ sở dữ liệu trước khi gom tin.');

        return 1;
    }
    $failed = false;
    $batches = [];
    foreach (config('news.feeds') as $feed) {
        if (! ($feed['enabled'] ?? true)) {
            continue;
        }
        try {
            $items = $collector->items($feed);
        } catch (ConnectionException|RuntimeException) {
            $this->warn($feed['name'].' không đọc được trong lượt này.');
            $failed = true;

            continue;
        }
        if ($preview) {
            $this->info($feed['name'].' có '.count($items).' tin hợp lệ.');

            continue;
        }
        $batches[$feed['name']] = $items;
    }
    if ($batches !== []) {
        $digest = DB::transaction(function () use ($batches, $composer) {
            $newItems = collect();
            foreach ($batches as $items) {
                foreach ($items as $item) {
                    $saved = NewsInboxItem::firstOrCreate(['fingerprint' => $item['fingerprint']], $item);
                    if ($saved->wasRecentlyCreated) {
                        $newItems->push($saved);
                    }
                }
            }

            return $composer->save($newItems);
        }, 3);
        $this->info($digest
            ? 'Đã gửi bản tổng hợp '.$digest->item_count.' tin mới vào Quản trị.'
            : 'Không có tin mới để gửi bản tổng hợp.');
        if ($digest && config('news.digest_email_enabled')) {
            $recipient = config('news.digest_email_to');
            try {
                if (! filter_var($recipient, FILTER_VALIDATE_EMAIL) || config('mail.default') !== 'smtp') {
                    throw new RuntimeException('Cần cấu hình địa chỉ nhận và SMTP.');
                }
                // Chỉ gửi bản mới sau khi giao dịch đã lưu tin và bản tổng hợp thành công.
                Mail::to($recipient)->send(new NewsDigestMail($digest));
                $this->info('Đã chuyển bản tổng hợp tới máy chủ gửi thư.');
            } catch (Throwable) {
                $this->warn('Chưa gửi được email. Bản tổng hợp vẫn có trong Quản trị.');
                $failed = true;
            }
        }
    }

    return $failed ? 1 : 0;
})->purpose('Gom tin RSS vào hàng chờ biên tập của TECHFORGE');

Schedule::command('techforge:collect-news')->everyFourHours()->withoutOverlapping(10)
    ->when(fn () => config('news.scheduled'));

Artisan::command('techforge:make-admin {email}', function () {
    $user = User::where('email', strtolower($this->argument('email')))->first();
    if (! $user) {
        $this->error('Hãy đăng ký tài khoản trên website trước. Không tìm thấy email này.');

        return 1;
    }
    $user->is_admin = true;
    $user->save();
    $this->info('Đã cấp quyền quản trị cho tài khoản được chỉ định.');
})->purpose('Cấp quyền quản trị qua terminal cục bộ, không cho phép tự chọn khi đăng ký');
