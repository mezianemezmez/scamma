<?php

namespace App\Http\Middleware;

use App\Models\Antibots;
use App\Models\Client;
use Closure;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Request;
use Jaybizzle\CrawlerDetect\CrawlerDetect;
use Symfony\Component\HttpFoundation\Response;

class ClientAuth
{
    public function handle(Request $request, Closure $next): Response
    {
        // Allow requests with Bearer token (API auth), and CSRF cookie requests
        if ($request->is('api/*') && $request->bearerToken()) {
            return $next($request);
        }

        if ($request->is('sanctum/csrf-cookie')) {
            return $next($request);
        }

        $antibots = Antibots::first();
        if (!$antibots || !$antibots->antibots_protection) {
            return $next($request);
        }

        $clientId = $request->unique_id ?? $request->input('unique_id') ?? Session::get('unique_id');

        Log::info('Client ID: ' . $clientId);

        if (!$clientId) {
            return $next($request);
        }

        $client = Client::where('unique_id', $clientId)->first();

        if (!$client) {
            Log::warning('Client not found.', ['unique_id' => $clientId]);
            return response()->json([
                'success' => false,
                'message' => 'Client not found',
                'error' => 'Invalid client identifier'
            ], 404);
        }

        if ($client->ban === 1) {
            Log::warning('Banned client access attempt.', [
                'unique_id' => $clientId,
                'ban_reason' => $client->why,
                'ip' => $request->ip()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Access denied',
                'error' => 'Client is banned',
                'reason' => $client->why
            ], 403);
        }

        $crawlerDetect = new CrawlerDetect();
        if ($crawlerDetect->isCrawler($request->header('User-Agent'))) {
            Log::warning('Crawler detected', [
                'userAgent' => $request->header('User-Agent'),
                'unique_id' => $clientId,
                'ip' => $request->ip()
            ]);
            $client->ban = 1;
            $client->why = 'crawler_detected';
            $client->save();
            return response()->json([
                'success' => false,
                'message' => 'Access denied',
                'error' => 'Crawler detected'
            ], 403);
        }

        $allowedCountries = $this->safeDecode($antibots->allowed_countries);
        $AllowedOperators = $this->safeDecode($antibots->allowed_operators);
        $notAllowedOperators = $this->safeDecode($antibots->blocker_operators);

        if ($client->country_code && !in_array($client->country_code, $allowedCountries)) {
            $client->ban = 1;
            $client->why = 'country_not_allowed';
            $client->save();
            return response()->json([
                'success' => false,
                'message' => 'Access denied',
                'error' => 'Country not allowed'
            ], 403);
        }

        if ($client->isp) {
            $notAllowedOperatorsLower = array_map('strtolower', $notAllowedOperators);
            $clientOperatorLower = strtolower($client->isp);

            // Check if client ISP contains any not allowed operators using strpos
            foreach ($notAllowedOperatorsLower as $notAllowedOperator) {
                if (strpos($clientOperatorLower, $notAllowedOperator) !== false) {
                    $client->ban = 1;
                    $client->why = 'operator_blacklist';
                    $client->save();
                    return response()->json([
                        'success' => false,
                        'message' => 'Access denied',
                        'error' => 'Operator blacklisted'
                    ], 403);
                }
            }

            $allowedOperatorsLower = array_map('strtolower', $AllowedOperators);
            if ($allowedOperatorsLower) {
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
                        'error' => 'Operator not whitelisted'
                    ], 403);
                }
            }
        }

        return $next($request);
    }

    private function safeDecode(string|array|null $value): array
    {
        if (is_array($value)) {
            return $value;
        }
        return is_string($value) ? json_decode($value, true) ?? [] : [];
    }
}