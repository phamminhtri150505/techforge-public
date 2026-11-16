<?php

namespace App\Services;

use DateTimeImmutable;
use DateTimeZone;
use DOMDocument;
use DOMXPath;
use GuzzleHttp\Psr7\Utils;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class NewsFeedCollector
{
    public function items(array $feed): array
    {
        $response = Http::accept('application/rss+xml, application/xml, text/xml')
            ->connectTimeout(4)->timeout(10)->withoutRedirecting()
            ->withOptions(['stream' => true])->get($feed['url']);
        $stream = $response->toPsrResponse()->getBody();
        try {
            if (! $response->successful()) {
                throw new RuntimeException('Nguồn RSS không phản hồi thành công.');
            }
            $xml = Utils::copyToString($stream, 1_000_001);
        } finally {
            $stream->close();
        }
        if (strlen($xml) > 1_000_000 || str_contains($xml, "\0") || stripos($xml, '<!DOCTYPE') !== false) {
            throw new RuntimeException('Nguồn RSS quá lớn hoặc có định dạng không được hỗ trợ.');
        }
        $document = new DOMDocument;
        if ($xml === '' || ! $document->loadXML($xml, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING)) {
            throw new RuntimeException('Nguồn không trả về RSS hợp lệ.');
        }
        $xpath = new DOMXPath($document);
        if (! $xpath->evaluate('boolean(/rss/channel)')) {
            throw new RuntimeException('Nguồn không trả về RSS hợp lệ.');
        }

        $items = [];
        $timezone = new DateTimeZone(config('app.timezone'));
        foreach ($xpath->query('/rss/channel/item') as $entry) {
            $url = $this->articleUrl(trim($xpath->evaluate('string(link)', $entry)), $feed['hosts'], $feed['path_prefix'] ?? '/');
            $title = $this->plainText($xpath->evaluate('string(title)', $entry), 200);
            if ($url === null || $title === '') {
                continue;
            }
            $fingerprint = hash('sha256', $url);
            $date = DateTimeImmutable::createFromFormat(DATE_RSS, trim($xpath->evaluate('string(pubDate)', $entry)));
            $items[$fingerprint] = [
                'fingerprint' => $fingerprint, 'source' => $feed['name'], 'url' => $url, 'title' => $title,
                'preview' => ($feed['include_preview'] ?? true) ? ($this->plainText($xpath->evaluate('string(description)', $entry), 450) ?: null) : null,
                'source_published_at' => $date ? $date->setTimezone($timezone)->format('Y-m-d H:i:s') : null,
            ];
            if (count($items) === 20) {
                break;
            }
        }

        return array_values($items);
    }

    private function articleUrl(string $link, array $hosts, string $pathPrefix): ?string
    {
        $parts = parse_url($link);
        $host = strtolower($parts['host'] ?? '');
        $path = $parts['path'] ?? '';
        if (($parts['scheme'] ?? '') !== 'https' || ! in_array($host, $hosts, true)
            || ! filter_var($link, FILTER_VALIDATE_URL) || $path === '/' || ! str_starts_with($path, $pathPrefix)
            || isset($parts['user']) || isset($parts['pass']) || isset($parts['port'])) {
            return null;
        }

        $url = 'https://'.(in_array($host, ['www.tuoitre.vn', 'www.tinhte.vn'], true) ? substr($host, 4) : $host).rtrim($path, '/');

        return strlen($url) <= 900 ? $url : null;
    }

    private function plainText(string $value, int $limit): string
    {
        $text = strip_tags(html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        $text = trim(preg_replace('/\s+/u', ' ', $text) ?? '');

        return mb_strimwidth($text, 0, $limit, '…', 'UTF-8');
    }
}
