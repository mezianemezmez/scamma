<?php

namespace App\Http\Controllers;

use App\Models\Antibots;
use App\Models\Client;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Jaybizzle\CrawlerDetect\CrawlerDetect;
use Symfony\Component\HttpFoundation\Response;

class BlockerController extends Controller
{
    public function authorizeClient(Request $request, $clientId): Response
    {
        $antibots = Antibots::first();

        // Validate clientId
        if (!$this->isValidClientId($clientId)) {
            return response()->json([
                'success' => false,
                'message' => 'Client ID is required and must be a valid identifier',
                'error' => 'Missing or invalid client identifier',
                'reason' => 'invalid_credentials'
            ], 400);
        }

        $client = Client::where('unique_id', $clientId)->first();

        if (!$client) {
            //Log::warning('Client not found.', ['unique_id' => $clientId]);
            return response()->json([
                'success' => false,
                'message' => 'Client not found',
                'error' => 'Invalid client identifier',
                'reason' => 'client_not_found'
            ], 404);
        }

        // Antibots protection toggle
        if (!$antibots || !$antibots->antibots_protection) {
            return response()->json([
                'success' => true,
                'message' => 'Antibots protection disabled',
                'client_meta' => $this->buildClientMeta($client),
            ], 200);
        }

        if ($client->ban === 1) {
            // Log::warning('Banned client access attempt.', [
            //     'unique_id' => $clientId,
            //     'ban_reason' => $client->why,
            //     'ip' => $request->ip()
            // ]);
            return response()->json([
                'success' => false,
                'message' => 'Access denied',
                'error' => 'Client is banned',
                'reason' => $client->why
            ], 403);
        }

        if ($antibots->captcha_protection) {
            $captchaState = Cache::get($this->captchaStateCacheKey($clientId));
            if (is_array($captchaState)) {
                $passed = (bool)($captchaState['passed'] ?? false);
                $required = (bool)($captchaState['required'] ?? false);

                if ($required && !$passed) {
                    return response()->json([
                        'success' => false,
                        'message' => 'CAPTCHA verification required',
                        'error' => 'CAPTCHA not completed',
                        'reason' => 'captcha_required'
                    ], 403);
                }
            }
        }

        // Bot/Crawler detection
        $crawlerDetect = new CrawlerDetect();
        if ($crawlerDetect->isCrawler($request->header('User-Agent'))) {
            // Log::warning('Crawler detected', [
            //     'userAgent' => $request->header('User-Agent'),
            //     'unique_id' => $clientId,
            //     'ip' => $request->ip()
            // ]);
            $client->ban = 1;
            $client->why = 'crawler_detected';
            $client->save();
            return response()->json([
                'success' => false,
                'message' => 'Access denied',
                'error' => 'Crawler detected',
                'reason' => 'crawler_detected'
            ], 403);
        }

        $allowedCountries = $this->safeDecode($antibots->allowed_countries);
        $allowedOperators = $this->safeDecode($antibots->allowed_operators);
        $blockedOperators = $this->safeDecode($antibots->blocker_operators);

        // Country check
        if ($client->country_code && !empty($allowedCountries) && !in_array($client->country_code, $allowedCountries)) {
            $client->ban = 1;
            $client->why = 'country_not_allowed';
            $client->save();
            return response()->json([
                'success' => false,
                'message' => 'Access denied',
                'error' => 'Country not allowed',
                'reason' => 'country_not_allowed'
            ], 403);
        }

        // Operator/ISP checks
        if ($client->isp) {
            $blockedOperatorsLower = array_map('strtolower', $blockedOperators);
            $allowedOperatorsLower = array_map('strtolower', $allowedOperators);
            $clientOperatorLower = strtolower($client->isp);

            // Check if client ISP contains any blocked operators using strpos
            foreach ($blockedOperatorsLower as $blockedOperator) {
                if (strpos($clientOperatorLower, $blockedOperator) !== false) {
                    $client->ban = 1;
                    $client->why = 'operator_blacklist';
                    $client->save();
                    return response()->json([
                        'success' => false,
                        'message' => 'Access denied',
                        'error' => 'Operator blacklisted',
                        'reason' => 'operator_blacklist'
                    ], 403);
                }
            }

            if (!empty($allowedOperatorsLower)) {
                $isAllowed = false;
                // Check if client ISP contains any allowed operators using strpos
                foreach ($allowedOperatorsLower as $allowedOperator) {
                    if (strpos($clientOperatorLower, $allowedOperator) !== false) {
                        $isAllowed = true;
                        break;
                    }
                }
                
                if (!$isAllowed) {
                    $client->ban = 1;
                    $client->why = 'operator_whitelist';
                    $client->save();
                    return response()->json([
                        'success' => false,
                        'message' => 'Access denied',
                        'error' => 'Operator not whitelisted',
                        'reason' => 'operator_whitelist'
                    ], 403);
                }
            }
        }

        // Success
        return response()->json([
            'success' => true,
            'message' => 'Client authorized',
            'client_meta' => [
                'country_code' => $client->country_code,
                'isp' => $client->isp,
            ],
        ], 200);
    }

    /**
     * Decode JSON or return array. Safe against null/invalid input.
     */
    private function safeDecode($value): array
    {
        if (is_array($value)) {
            return $value;
        }
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            return is_array($decoded) ? $decoded : [];
        }
        return [];
    }

    private function isValidClientId(?string $clientId): bool
    {
        if (!is_string($clientId)) {
            return false;
        }

        if (preg_match('/^[a-f0-9]{12}$/i', $clientId) === 1) {
            return true;
        }

        return Str::isUuid($clientId);
    }

    private function captchaStateCacheKey(string $clientId): string
    {
        return "captcha:state:{$clientId}";
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