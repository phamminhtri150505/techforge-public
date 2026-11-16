<?php

namespace App\Models;

use App\Services\ProductImageService;
use App\Services\ProductSpecificationSchema;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    public const AVAILABILITY = ['orderable' => 'Đặt mô phỏng', 'contact' => 'Liên hệ', 'unavailable' => 'Tạm ngừng nhận đơn'];

    public const PRICE_KINDS = ['simulated' => 'Giá giả lập', 'retail_reference' => 'Giá tham khảo từ cửa hàng',
        'unknown' => 'Chưa xác minh giá', 'manual' => 'Giá quản trị nhập, chưa đối chiếu nguồn'];

    protected $fillable = ['sku', 'name', 'type', 'price', 'specifications', 'data_source', 'is_active', 'brand', 'availability',
        'manufacturer_code', 'catalog_sources', 'price_kind', 'price_source_url', 'price_observed_on', 'price_retailer', 'price_note'];

    public function formattedPrice(): string
    {
        return $this->price_kind === 'unknown' ? 'Liên hệ' : number_format($this->price, 0, ',', '.').' ₫';
    }

    public function builderSummary(): string
    {
        $fields = match ($this->type) {
            'cpu' => ['socket', 'cores', 'threads', 'memory_types'],
            'mainboard' => ['socket', 'chipset', 'memory_type', 'form_factor'],
            'ram' => ['memory_type', 'capacity_gb', 'modules', 'speed_mts'],
            'gpu' => ['chip_vendor', 'gpu_chipset', 'vram_gb', 'length_mm', 'recommended_psu_w'],
            'psu' => ['wattage_w', 'form_factor', 'efficiency_rating'],
            'storage' => ['capacity_gb', 'interface', 'size'],
            'cooler' => ['kind', 'sockets', 'height_mm'],
            'case' => ['mainboard_forms', 'max_gpu_length_mm', 'max_cooler_height_mm'],
            default => [],
        };

        return collect($fields)->map(function ($key) {
            $value = $this->specifications[$key] ?? null;
            $text = $value === null ? 'Chưa có dữ liệu' : (is_array($value) ? implode(', ', $value)
                : (is_bool($value) ? ($value ? 'Có' : 'Không') : (string) $value));

            return config('specifications.labels.'.$key, $key).': '.$text;
        })->join(' · ');
    }

    public function illustration(): string
    {
        return $this->imageDetails()['path'] ?? 'images/components/'.$this->type.'.svg';
    }

    public function imageDetails(): ?array
    {
        return app(ProductImageService::class)->forProduct($this);
    }

    public function imageAlt(): string
    {
        $image = $this->imageDetails();

        return $image ? $this->name.' — ảnh từ '.$image['source_owner'] : 'Minh họa nhóm '.(ProductSpecificationSchema::TYPES[$this->type] ?? $this->type).', không phải ảnh sản phẩm hãng';
    }

    public function imageCaption(): string
    {
        $image = $this->imageDetails();
        if (! $image) {
            return 'Minh họa nhóm linh kiện · không phải ảnh sản phẩm hãng';
        }

        return match ($image['kind']) {
            'manufacturer_product' => 'Ảnh sản phẩm từ hãng',
            'licensed_photo' => 'Ảnh chụp sản phẩm',
            default => 'Ảnh trên trang sản phẩm cửa hàng · bao bì chỉ tham khảo',
        };
    }

    protected function casts(): array
    {
        return ['specifications' => 'array', 'catalog_sources' => 'array', 'price' => 'integer', 'is_active' => 'boolean', 'price_observed_on' => 'date'];
    }

    protected static function booted(): void
    {
        // Áp dụng cả seeder và CRUD tương lai; không dùng query builder để bỏ qua validation.
        static::saving(function (Product $product): void {
            // Đổi giá thủ công không được tiếp tục mang nhãn giá đã đối chiếu cũ.
            if ($product->exists && $product->getOriginal('price_kind') === 'retail_reference'
                && ($product->price_kind !== 'retail_reference' || ($product->isDirty('price')
                    && ! $product->isDirty(['price_source_url', 'price_observed_on'])))) {
                if ($product->price_kind === 'retail_reference') {
                    $product->price_kind = 'manual';
                }
                $product->price_source_url = null;
                $product->price_observed_on = null;
                $product->price_retailer = null;
                $product->price_note = 'Giá đã được chỉnh sau lần đối chiếu, cần xác minh lại.';
            }
            $data = $product->attributesToArray();
            $data['price_observed_on'] = $product->price_observed_on?->format('Y-m-d');
            // Kiểm tra giá trước khi cast integer, tránh 10.5 bị ép thành 10 rồi được chấp nhận.
            $raw = $product->getAttributes();
            $data['price'] = $raw['price'] ?? null;
            $data['is_active'] = $raw['is_active'] ?? null;
            ProductSpecificationSchema::validateProduct($data);
        });
    }
}
