<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Filesystem Disk
    |--------------------------------------------------------------------------
    |
    | Here you may specify the default filesystem disk that should be used
    | by the framework. The "local" disk, as well as a variety of cloud
    | based disks are available to your application for file storage.
    |
    */

    'default' => env('FILESYSTEM_DISK', 'local'),

    /*
    |--------------------------------------------------------------------------
    | Filesystem Disks
    |--------------------------------------------------------------------------
    |
    | Below you may configure as many filesystem disks as necessary, and you
    | may even configure multiple disks for the same driver. Examples for
    | most supported storage drivers are configured here for reference.
    |
    | Supported drivers: "local", "ftp", "sftp", "s3"
    |
    */

    'disks' => [

        'local' => [
            'driver' => 'local',
            'root' => storage_path('app/private'),
            'serve' => true,
            'throw' => false,
            'report' => false,

            /*
             * ⚠️ WITHOUT THIS, EVERY PHOTOGRAPH UPLOADED THROUGH THE APP WAS
             * INVISIBLE TO THE REVIEWER.
             *
             * Flysystem defaults a private directory to 0700 — owner only. The
             * files land as deploy:www-data and php-fpm runs as www-data, so
             * the web server could not TRAVERSE the directory it had just
             * written through. `exists()` answered true from the console and
             * the image route returned 500 in the browser.
             *
             * ⚠️ THE EFFECT WAS THAT VERIFICATION COULD NOT HAPPEN AT ALL. A
             * member uploads four photographs, the reviewer opens the queue,
             * and every image is broken. Seeded profiles worked, because those
             * directories were created by a different process with a different
             * umask — so the demo looked perfect and only real uploads failed.
             *
             * 0750 and 0640 are STRICTER than what the files had (0644) and
             * looser than what the directories had (0700). Owner writes, the
             * web server group reads, nobody else sees anything.
             *
             * ⚠️ THIS MUST STAY IN STEP WITH THE OWNERSHIP THE DEPLOY SETS.
             * The deploy runs `chown -R deploy:www-data`. If php-fpm is ever
             * moved to another user, these bytes become unreadable again and
             * the symptom will be broken images rather than an error anybody
             * can search for.
             */
            'permissions' => [
                'file' => ['public' => 0644, 'private' => 0640],
                'dir' => ['public' => 0755, 'private' => 0750],
            ],
        ],

        'public' => [
            'driver' => 'local',
            'root' => storage_path('app/public'),
            'url' => rtrim(env('APP_URL', 'http://localhost'), '/').'/storage',
            'visibility' => 'public',
            'throw' => false,
            'report' => false,
        ],

        's3' => [
            'driver' => 's3',
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'region' => env('AWS_DEFAULT_REGION'),
            'bucket' => env('AWS_BUCKET'),
            'url' => env('AWS_URL'),
            'endpoint' => env('AWS_ENDPOINT'),
            'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', false),
            'throw' => false,
            'report' => false,
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Symbolic Links
    |--------------------------------------------------------------------------
    |
    | Here you may configure the symbolic links that will be created when the
    | `storage:link` Artisan command is executed. The array keys should be
    | the locations of the links and the values should be their targets.
    |
    */

    'links' => [
        public_path('storage') => storage_path('app/public'),
    ],

];
