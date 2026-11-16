<?php

return [
    'scheduled' => env('NEWS_COLLECT_ENABLED', false),
    'digest_email_enabled' => env('NEWS_DIGEST_EMAIL_ENABLED', false),
    'digest_email_to' => env('NEWS_DIGEST_EMAIL_TO'),
    'feeds' => [
        'tuoitre' => [
            'name' => 'Tuổi Trẻ – Công nghệ',
            'url' => 'https://tuoitre.vn/nhip-song-so.rss',
            'hosts' => ['tuoitre.vn', 'www.tuoitre.vn'],
        ],
        'nvidia' => [
            'name' => 'NVIDIA Newsroom – Gaming',
            'url' => 'https://nvidianews.nvidia.com/cats/gaming.xml',
            'hosts' => ['nvidianews.nvidia.com'],
        ],
        'tinhte' => [
            'enabled' => env('NEWS_TINHTE_ENABLED', false),
            'name' => 'Tinh tế',
            'url' => 'https://tinhte.vn/rss',
            'hosts' => ['tinhte.vn', 'www.tinhte.vn'],
            'path_prefix' => '/thread/',
            'include_preview' => false,
        ],
    ],
];
