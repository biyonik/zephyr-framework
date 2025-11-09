<?php

declare(strict_types=1);

return [
    'default' => env('FILESYSTEM_DRIVER', 'local'),

    'disks' => [
        'local' => [
            'driver' => 'local',
            'root' => storage_path('app'),
        ],

        'public' => [
            'driver' => 'local',
            'root' => storage_path('app/public'),
            'url' => env('APP_URL', 'http://localhost') . '/storage',
            'visibility' => 'public',

            // ✅ GÜVENLIK AYARLARI
            'allowed_mime_types' => [
                'image/jpeg',
                'image/png',
                'image/gif',
                'image/webp',
                'application/pdf',
            ],
            'max_file_size' => 5 * 1024 * 1024, // 5MB
        ],
    ],
];