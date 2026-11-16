<?php

namespace App\Http\Responses;

use App\Services\ProductSpecificationSchema;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;

class RateLimitNotice
{
    public static function render(ThrottleRequestsException $exception, Request $request): ?RedirectResponse
    {
        if ($request->isMethodSafe() || $request->expectsJson() || ! $request->acceptsHtml() || ! $request->hasSession()) {
            return null;
        }

        $origin = $request->getSchemeAndHttpHost();
        $previous = $request->headers->get('referer');
        if ($previous !== null && (! str_starts_with($previous, $origin.'/') || str_contains($previous, '\\')
            || preg_match('/[\x00-\x20]/', $previous))) {
            return null;
        }

        // Trang chia sẻ dùng no-referrer. Chỉ dựng lại URL từ route có sẵn.
        if ($previous === null && $request->routeIs('configurations.remix') && in_array('text/html', $request->getAcceptableContentTypes(), true)) {
            $previous = route('configurations.shared', $request->route('token'));
        }
        if ($previous === null) {
            return null;
        }
        if ($request->routeIs('assistant.update') && parse_url($previous, PHP_URL_PATH) === route('builder.index', absolute: false)) {
            $previous = explode('#', $previous, 2)[0].'#assistant';
        }

        // Quay lại bằng GET. Chỉ giữ các ô được phép, không giữ mật khẩu hay mã của lần gửi.
        $response = redirect()->to($previous, 303)->with('rate_limit_notice', true);
        $fields = match ($request->route()?->getName()) {
            'assistant.update' => ['budget', 'purpose', 'min_ram_gb', 'min_storage_gb'],
            'orders.store' => ['recipient', 'phone', 'address'],
            'login.store' => ['email'],
            'register.store' => ['name', 'email'],
            default => [],
        };
        $input = array_filter($request->only($fields), 'is_scalar');
        if ($request->routeIs('builder.check')) {
            $types = array_keys(ProductSpecificationSchema::TYPES);
            $input['parts'] = array_filter(Arr::only((array) $request->input('parts', []), $types), 'is_scalar');
            $input['owned'] = array_values(array_intersect($types, array_filter((array) $request->input('owned', []), 'is_string')));
        }

        return $input === [] ? $response : $response->withInput($input);
    }
}
