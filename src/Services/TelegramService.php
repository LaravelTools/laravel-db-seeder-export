<?php

namespace LaravelToolkit\DbSeederExport\Services;

use GuzzleHttp\Client;
use Exception;

class TelegramService
{
    protected static Client $client;

    protected static string $token;

    protected static string $chatId;

    public static function init(?string $context = null): void
    {
        self::$client = new Client;
        
        if ($context) {
            $token = env($context . '_TELEGRAM_BOT_TOKEN');
            $chatId = env($context . '_TELEGRAM_CHAT_ID');
            
            if (empty($token) || empty($chatId)) {
                $token = config('db-seeder-export.telegram.token');
                $chatId = config('db-seeder-export.telegram.chat_id');
            }
        } else {
            $token = config('db-seeder-export.telegram.token');
            $chatId = config('db-seeder-export.telegram.chat_id');
        }
        
        if (empty($token) || empty($chatId)) {
            throw new Exception("Telegram credentials not found in environment variables or config");
        }
        
        self::$token = $token;
        self::$chatId = $chatId;
    }

    public static function sendTelegramBotMessage($message, ?string $context = null)
    {
        try {
            self::init($context); // Initialize with optional context
            
            $url = 'https://api.telegram.org/bot'.self::$token.'/sendMessage';

            $response = self::$client->post($url, [
                'form_params' => [
                    'chat_id' => self::$chatId,
                    'text' => $message,
                ],
            ]);

            return json_decode($response->getBody()->getContents(), true);
        } catch (Exception $e) {
            throw new Exception("Failed to send Telegram message: " . $e->getMessage());
        }
    }
    
    public static function sendTelegramBotFile($filePath, $caption = '', ?string $context = null)
    {
        try {
            self::init($context); // Initialize with optional context
            
            if (!file_exists($filePath)) {
                throw new Exception("File not found: {$filePath}");
            }
            
            $url = "https://api.telegram.org/bot".self::$token."/sendDocument";
            
            $response = self::$client->post($url, [
                'multipart' => [
                    [
                        'name' => 'chat_id',
                        'contents' => self::$chatId
                    ],
                    [
                        'name' => 'document',
                        'contents' => fopen($filePath, 'r'),
                        'filename' => basename($filePath)
                    ],
                    [
                        'name' => 'caption',
                        'contents' => $caption
                    ]
                ]
            ]);
            
            return json_decode($response->getBody()->getContents(), true);
        } catch (Exception $e) {
            throw new Exception("Failed to send file to Telegram: " . $e->getMessage());
        }
    }
}