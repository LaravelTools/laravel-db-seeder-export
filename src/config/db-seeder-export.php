<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Default Excluded Tables
    |--------------------------------------------------------------------------
    |
    | Tables that are excluded from export by default
    |
    */
    'excluded_tables' => [
        'migrations', 
        'failed_jobs', 
        'password_resets', 
        'personal_access_tokens',
        'telescope_entries',
        'telescope_entries_tags',
        'telescope_monitoring',
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Storage Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for backup storage
    |
    */
    'storage' => [
        'disk' => env('DB_SEEDER_STORAGE_DISK', 'local'),
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Telegram Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for Telegram notifications
    |
    */
    'telegram' => [
        'enabled' => env('DB_SEEDER_TELEGRAM_ENABLED', false),
        'token' => env('DB_SEEDER_TELEGRAM_BOT_TOKEN', env('TELEGRAM_BOT_TOKEN')),
        'chat_id' => env('DB_SEEDER_TELEGRAM_CHAT_ID', env('TELEGRAM_CHAT_ID')),
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Email Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for email notifications
    |
    */
    'notifications' => [
        'mail' => [
            'enabled' => env('DB_SEEDER_MAIL_ENABLED', false),
            'to' => env('DB_SEEDER_MAIL_TO', ''),
        ],
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Execution Settings 
    |--------------------------------------------------------------------------
    |
    | Various settings for command execution
    |
    */
    'execution' => [
        'max_time' => env('DB_SEEDER_MAX_EXECUTION_TIME', 0), // 0 = no limit
        'schema_aware' => env('DB_SEEDER_SCHEMA_AWARE', true),
        'disable_foreign_keys' => env('DB_SEEDER_DISABLE_FK', true),
        'skip_empty_tables' => env('DB_SEEDER_SKIP_EMPTY', false),
    ],
];