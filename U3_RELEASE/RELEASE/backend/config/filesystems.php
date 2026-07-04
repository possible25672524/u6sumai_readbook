<?php

return [

    'default' => env('FILESYSTEM_DISK', 'local'),

    // Disk used for all document storage (MinIO in production/Docker)
    'default_document_disk' => env('DOCUMENT_STORAGE_DISK', 's3'),

    'disks' => [

        'local' => [
            'driver' => 'local',
            'root'   => storage_path('app/private'),
            'serve'  => true,
            'throw'  => false,
        ],

        'public' => [
            'driver'     => 'local',
            'root'       => storage_path('app/public'),
            'url'        => env('APP_URL') . '/storage',
            'visibility' => 'public',
            'throw'      => false,
        ],

        // ── MinIO (S3-compatible) ──────────────────────────────
        's3' => [
            'driver'                  => 's3',
            'key'                     => env('MINIO_ACCESS_KEY', env('AWS_ACCESS_KEY_ID', 'minio_admin')),
            'secret'                  => env('MINIO_SECRET_KEY', env('AWS_SECRET_ACCESS_KEY', 'minio_secret')),
            'region'                  => env('AWS_DEFAULT_REGION', 'us-east-1'),
            'bucket'                  => env('MINIO_BUCKET', env('AWS_BUCKET', 'study-assistant-files')),
            'url'                     => env('AWS_URL'),
            'endpoint'                => env('MINIO_ENDPOINT', 'http://minio:9000'),
            'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', true),
            'throw'                   => false,
        ],

    ],

    'links' => [
        public_path('storage') => storage_path('app/public'),
    ],

];
