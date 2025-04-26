# Laravel DB Seeder Export

A Laravel package to export database tables into Laravel Seeder classes with support for Telegram notifications and email alerts.

## Features

- Export database tables to Laravel Seeder classes
- Automatic dependency detection between tables
- Support for multiple database types (MySQL, PostgreSQL, SQLite, SQL Server)
- Schema-aware seeders that can adapt to column changes
- Telegram notifications with file attachments
- Email notifications with detailed statistics
- Advanced filtering options to include/exclude tables
- JSON output for API integrations
- Comprehensive stats and reporting

## Installation

You can install the package via composer:

```bash
composer require laravel-toolkit/laravel-db-seeder-export
```

## Publishing Configuration

```bash
php artisan vendor:publish --tag=db-seeder-export-config
```

This will publish the configuration file to `config/db-seeder-export.php`.

To publish the email templates:

```bash
php artisan vendor:publish --tag=db-seeder-export-views
```

## Usage

### Basic Usage

Export all tables:

```bash
php artisan db:export-seeder --all
```

Export specific tables:

```bash
php artisan db:export-seeder users posts comments
```

### Options

| Option | Description |
|--------|-------------|
| `--all` | Export all tables in the database |
| `--disable-foreign-keys` | Temporarily disable foreign key constraints during seeding |
| `--exclude=table1,table2` | Tables to exclude from export |
| `--exclude-pattern=pattern1,pattern2` | Exclude tables matching these patterns (e.g., "telescope_*,log_*") |
| `--include-migrations` | Include the migrations table which is excluded by default |
| `--schema-aware` | Create schema-aware seeders that can adapt to column changes |
| `--telegram` | Send the backup to Telegram |
| `--delete-after-send` | Delete the backup files after sending to Telegram |
| `--output-json` | Format output as JSON (useful for API calls) |
| `--notify-email=email1,email2` | Send email notification to these addresses |
| `--skip-empty-tables` | Skip tables that have no data |
| `--storage-disk=diskname` | Specify storage disk to save backup (default: local) |
| `--max-execution-time=seconds` | Set maximum execution time in seconds |

### Configuration

You can configure the package by editing the `config/db-seeder-export.php` file or by setting environment variables in your `.env` file:

```env
# General Settings
DB_SEEDER_SCHEMA_AWARE=true
DB_SEEDER_DISABLE_FK=true
DB_SEEDER_SKIP_EMPTY=false
DB_SEEDER_MAX_EXECUTION_TIME=300
DB_SEEDER_STORAGE_DISK=local

# Telegram Configuration
DB_SEEDER_TELEGRAM_ENABLED=true
DB_SEEDER_TELEGRAM_BOT_TOKEN=your_telegram_bot_token
DB_SEEDER_TELEGRAM_CHAT_ID=your_telegram_chat_id

# Email Configuration
DB_SEEDER_MAIL_ENABLED=true
DB_SEEDER_MAIL_TO=admin@example.com,alerts@example.com
```

### Using Created Seeders

After running the export command, the seeders will be created in a timestamped directory inside the `database/seeders` folder. The command will output instructions for using the seeders.

To run the master seeder (which will run all individual seeders in the correct order):

```bash
php artisan db:seed --class="Database\Seeders\BackupXXXXXXXXXXXXX\DatabaseBackupSeeder"
```

## Telegram Integration

To use the Telegram integration, you need to:

1. Create a Telegram bot using [BotFather](https://t.me/botfather)
2. Get your chat ID (you can use the [userinfobot](https://t.me/userinfobot))
3. Set the environment variables:
   - `DB_SEEDER_TELEGRAM_ENABLED=true`
   - `DB_SEEDER_TELEGRAM_BOT_TOKEN=your_token`
   - `DB_SEEDER_TELEGRAM_CHAT_ID=your_chat_id`

## Email Notifications

To send email notifications after backup completion:

1. Ensure your Laravel mail configuration is set up correctly
2. Set the recipient email addresses in the config file or using the `--notify-email` option

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.