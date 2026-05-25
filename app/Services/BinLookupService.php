<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class BinLookupService
{
    protected $apiKey;

    public function __construct()
    {
        $this->apiKey = env('BIN_API_KEY', 'HAS-0YYRXxQgdvMzHL9u9184D');
    }

    public function fetchBinData(string $bin): ?array
    {
        $url = "https://data.handyapi.com/bin/{$bin}";

        try {
            $response = Http::withHeaders([
                'x-api-key' => $this->apiKey,
            ])->get($url);

            if ($response->successful()) {
                return $response->json();
            }
        } catch (\Exception $e) {
            Log::error("BIN API request failed: " . $e->getMessage());
        }

        return null;
    }
}