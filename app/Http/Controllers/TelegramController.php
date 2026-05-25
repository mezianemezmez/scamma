<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Services\TelegramService;
use Illuminate\Support\Facades\Log;

class TelegramController extends Controller
{
    private TelegramService $telegramService;

    public function __construct(TelegramService $telegramService)
    {
        $this->telegramService = $telegramService;
    }
        /**
     * Handle incoming Telegram webhook updates.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function handle(Request $request)
    {
        // Log that the webhook endpoint was hit
        //Log::info('🔵 Telegram Webhook Endpoint Hit - Method: ' . $request->method() . ' | URL: ' . $request->fullUrl());
        
        $update = $request->all();
        //Log::info('Telegram Webhook Update: ' . json_encode($update));

        try {
            // Handle callback_query
            if (isset($update['callback_query'])) {
                $callbackQuery = $update['callback_query'];

                $callbackData = $callbackQuery['data'] ?? '';
                $parts = explode(':', $callbackData);

                if (count($parts) >= 2) {
                    // Call service to handle the callback (remove button and notify)
                    $this->telegramService->handleCallbackAndNotify($callbackQuery);
                }
            }

        } catch (\Exception $e) {
            Log::error('Telegram Webhook Error: ' . $e->getMessage());
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }

        return response()->json(['status' => 'ok']);
    }

    /**
     * Test Telegram bot token
     */
    public function test(Request $request, TelegramService $telegramService): JsonResponse
    {
        $validated = $request->validate([
            'bot_token' => ['required', 'string']
        ]);

        try {
            // Test bot token
            if ($telegramService->testBot($validated['bot_token'])) {
                // Get bot info for additional details
                $botInfo = $telegramService->getBotInfo($validated['bot_token']);
                
                $message = '✅ Bot connection successful!';
                $responseData = [
                    'success' => true,
                    'message' => $message,
                    'bot_info' => $botInfo
                ];
                
                if ($botInfo) {
                    $message .= "\n🤖 Bot: " . ($botInfo['first_name'] ?? 'Unknown');
                    if (!empty($botInfo['username'])) {
                        $message .= " (@" . $botInfo['username'] . ")";
                    }
                    $message .= "\n🆔 Bot ID: " . ($botInfo['id'] ?? 'Unknown');
                    $responseData['message'] = $message;
                }
                
                return response()->json($responseData);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => '❌ Bot connection failed. Please check your bot token.'
                ], 422);
            }
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => '⚠️ Connection test error: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Send test message
     */
    public function testMessage(Request $request, TelegramService $telegramService): JsonResponse
    {
        $validated = $request->validate([
            'bot_token' => ['required', 'string'],
            'chat_id' => ['required', 'string'],
            'message' => ['nullable', 'string', 'max:1000']
        ]);

        try {
            $testMessage = $validated['message'] ?? '🧪 Test message from your Telegram bot settings!';
            
            if ($telegramService->sendTestMessage($validated['bot_token'], $validated['chat_id'], $testMessage)) {
                return response()->json([
                    'success' => true,
                    'message' => '✅ Test message sent successfully to chat ID: ' . $validated['chat_id']
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => '❌ Failed to send test message. Please check your bot token and chat ID.'
                ], 422);
            }
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => '⚠️ Test message error: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get webhook info
     */
    public function webhookInfo(Request $request, TelegramService $telegramService): JsonResponse
    {
        $validated = $request->validate([
            'bot_token' => ['required', 'string']
        ]);

        try {
            $webhookInfo = $telegramService->getWebhookInfo($validated['bot_token']);
            
            if ($webhookInfo !== false) {
                $message = '📡 Webhook Status:';
                $message .= "\n🔗 URL: " . ($webhookInfo['url'] ?: 'Not set');
                $message .= "\n📬 Pending updates: " . ($webhookInfo['pending_update_count'] ?? 0);
                $message .= "\n📅 Last error: " . ($webhookInfo['last_error_date'] ? date('Y-m-d H:i:s', $webhookInfo['last_error_date']) : 'None');
                
                return response()->json([
                    'success' => true,
                    'message' => $message,
                    'webhook_info' => $webhookInfo
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => '❌ Failed to get webhook information.'
                ], 422);
            }
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => '⚠️ Webhook info error: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Reset webhook - removes the current webhook URL
     */
    public function resetWebhook(Request $request, TelegramService $telegramService): JsonResponse
    {
        $validated = $request->validate([
            'bot_token' => ['required', 'string']
        ]);

        try {
            if ($telegramService->removeWebhook($validated['bot_token'])) {
                return response()->json([
                    'success' => true,
                    'message' => '🔄 Webhook reset successfully! The webhook URL has been removed from your bot.'
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => '❌ Failed to reset webhook. Please check your bot token.'
                ], 422);
            }
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => '⚠️ Webhook reset error: ' . $e->getMessage()
            ], 500);
        }
    }
}
