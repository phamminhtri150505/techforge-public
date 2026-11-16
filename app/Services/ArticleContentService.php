<?php

namespace App\Services;

use DOMDocument;
use DOMElement;
use DOMXPath;
use Illuminate\Support\Str;

class ArticleContentService
{
    /** Markdown có kiểm soát; nội dung cũ không có cú pháp vẫn là các đoạn văn. */
    public function render(string $content): array
    {
        $html = Str::markdown($content, [
            'html_input' => 'escape', 'allow_unsafe_links' => false,
            'max_nesting_level' => 30, 'renderer' => ['soft_break' => "<br>\n"],
        ]);
        $document = new DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);
        try {
            $document->loadHTML('<?xml encoding="UTF-8"><div id="article-body">'.$html.'</div>');
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
        $xpath = new DOMXPath($document);
        $body = $document->getElementById('article-body');
        $toc = [];
        $ids = [];
        $hasSection = false;
        foreach (iterator_to_array($xpath->query('.//h1|.//h2|.//h3|.//h4|.//h5|.//h6', $body)) as $heading) {
            // Chỉ tiêu đề trang là H1; không để bài biên tập phá hệ phân cấp.
            $level = in_array($heading->tagName, ['h1', 'h2']) || ! $hasSection ? 2 : 3;
            $hasSection = true;
            $text = trim($heading->textContent);
            $base = 'bai-'.(Str::slug($text) ?: 'muc');
            $id = $base;
            for ($suffix = 2; isset($ids[$id]); $suffix++) {
                $id = $base.'-'.$suffix;
            }
            $ids[$id] = true;
            $replacement = $document->createElement('h'.$level);
            $replacement->setAttribute('id', $id);
            $replacement->setAttribute('tabindex', '-1');
            while ($heading->firstChild) {
                $replacement->appendChild($heading->firstChild);
            }
            $heading->parentNode->replaceChild($replacement, $heading);
            $toc[] = ['id' => $id, 'text' => $text, 'level' => $level];
        }
        foreach ($xpath->query('.//a', $body) as $link) {
            $href = $link->getAttribute('href');
            if (! $this->safeLink($href)) {
                $link->removeAttribute('href');
            } elseif (preg_match('~^https?://~i', $href)) {
                $link->setAttribute('rel', 'noopener noreferrer');
            }
        }
        // Không tải ảnh tùy ý từ bên ngoài: chỉ ảnh raster cục bộ, đã tồn tại.
        foreach (iterator_to_array($xpath->query('.//img', $body)) as $image) {
            $path = $image->getAttribute('src');
            $file = str_starts_with($path, '/images/') ? realpath(public_path(ltrim($path, '/'))) : false;
            $root = realpath(public_path('images')).DIRECTORY_SEPARATOR;
            $size = $file && str_starts_with($file, $root)
                && preg_match('/\.(png|jpe?g|webp|gif|avif)$/i', $file) ? @getimagesize($file) : false;
            if (! $size) {
                $image->parentNode->replaceChild($document->createTextNode($image->getAttribute('alt')), $image);

                continue;
            }
            $image->setAttribute('width', (string) $size[0]);
            $image->setAttribute('height', (string) $size[1]);
            $image->setAttribute('loading', 'lazy');
            $image->setAttribute('decoding', 'async');
            $caption = $image->getAttribute('title');
            $image->removeAttribute('title');
            // Hình độc lập thành figure; ảnh nằm trong link/đoạn giữ nguyên ngữ nghĩa.
            $parent = $image->parentNode;
            if ($parent instanceof DOMElement && $parent->tagName === 'p' && $parent->childNodes->length === 1) {
                $figure = $document->createElement('figure');
                $parent->parentNode->replaceChild($figure, $parent);
                $figure->appendChild($image);
                if ($caption !== '') {
                    $figure->appendChild($document->createElement('figcaption'))->appendChild($document->createTextNode($caption));
                }
            }
        }
        foreach (iterator_to_array($xpath->query('.//table', $body)) as $index => $table) {
            $wrapper = $document->createElement('div');
            $wrapper->setAttribute('class', 'article-table');
            $wrapper->setAttribute('role', 'region');
            $wrapper->setAttribute('aria-label', 'Bảng thông tin '.($index + 1).' — có thể cuộn ngang');
            $wrapper->setAttribute('tabindex', '0');
            $table->parentNode->replaceChild($wrapper, $table);
            $wrapper->appendChild($table);
            foreach ($table->getElementsByTagName('th') as $cell) {
                $cell->setAttribute('scope', 'col');
            }
        }
        $html = '';
        foreach ($body->childNodes as $node) {
            $html .= $document->saveHTML($node);
        }
        // Tiếng Việt: ước lượng theo đơn vị ngăn cách bằng khoảng trắng, không phải số từ ngữ nghĩa.
        $units = preg_split('/\s+/u', trim($body->textContent), flags: PREG_SPLIT_NO_EMPTY);

        return ['html' => $html, 'toc' => count($toc) >= 3 ? $toc : [],
            'reading_minutes' => count($units) ? max(1, (int) ceil(count($units) / 200)) : null];
    }

    private function safeLink(string $href): bool
    {
        if (preg_match('/[\x00-\x20\\\\]/', $href)) {
            return false;
        }

        return (str_starts_with($href, '/') && ! str_starts_with($href, '//'))
            || str_starts_with($href, '#') || (bool) preg_match('~^https?://[^/]+~i', $href);
    }
}
