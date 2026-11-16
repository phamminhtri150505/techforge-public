<?php

namespace App\Services;

use App\Models\Product;

class ProductImageService
{
    private array $resolvedImages = [];

    public function forProduct(Product $product): ?array
    {
        $image = config('product_images.catalog', [])[$product->sku] ?? null;
        // Cache chỉ sống trong request. Đổi định danh hoặc manifest phải kiểm tra lại,
        // kể cả kết quả trước đó là null; không dùng riêng ID/SKU để giữ nhầm ảnh biến thể cũ.
        $identity = $product->only(['sku', 'name', 'type', 'brand', 'manufacturer_code']);
        $key = hash('sha256', serialize([$identity, $image]));
        if (! array_key_exists($key, $this->resolvedImages)) {
            $this->resolvedImages[$key] = $this->resolveImage($product, $image);
        }

        return $this->resolvedImages[$key];
    }

    private function resolveImage(Product $product, mixed $image): ?array
    {
        if (! is_array($image)) {
            return null;
        }
        // Đổi mã/tên/biến thể phải đối chiếu lại ảnh, không giữ ảnh của linh kiện cũ.
        foreach (['sku', 'name', 'type', 'brand', 'manufacturer_code'] as $field) {
            if (($image[$field] ?? null) !== $product->$field) {
                return null;
            }
        }
        if (! is_string($image['path'] ?? null) || ! $this->isSafePath($image['path']) || ! is_file(public_path($image['path']))) {
            return null;
        }
        if (! in_array($image['kind'] ?? '', ['manufacturer_product', 'retailer_listing', 'licensed_photo'], true)
            || ! is_string($image['source_page_url'] ?? null) || ! $this->isHttpsUrl($image['source_page_url'])
            || ! is_string($image['source_image_url'] ?? null) || ! $this->isHttpsUrl($image['source_image_url'])) {
            return null;
        }
        foreach (['source_owner', 'note', 'checked_on'] as $field) {
            if (! is_string($image[$field] ?? null) || trim($image[$field]) === '') {
                return null;
            }
        }
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $image['checked_on']);
        if (! $date || $date->format('Y-m-d') !== $image['checked_on']) {
            return null;
        }
        if ($image['kind'] === 'licensed_photo' && (
            ! is_string($image['license_name'] ?? null) || trim($image['license_name']) === ''
            || ! is_string($image['license_url'] ?? null) || ! $this->isHttpsUrl($image['license_url'])
        )) {
            return null;
        }
        if (isset($image['original_video_url']) && (! is_string($image['original_video_url']) || ! $this->isHttpsUrl($image['original_video_url']))) {
            return null;
        }

        return $image;
    }

    public function isSafePath(string $path): bool
    {
        // Chỉ cho file raster trong đúng thư mục public; không ../, SVG hoặc URL bên ngoài.
        return (bool) preg_match('/\Aimages\/products\/[a-z0-9][a-z0-9_-]*\.(?:png|jpg|jpeg|webp)\z/', $path);
    }

    public function isHttpsUrl(string $url): bool
    {
        return filter_var($url, FILTER_VALIDATE_URL) !== false && parse_url($url, PHP_URL_SCHEME) === 'https';
    }
}
