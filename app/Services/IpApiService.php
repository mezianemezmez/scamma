<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class IpApiService
{
    /**
     * API Key for the IP API.
     *
     * @var string
     */
    private $apiKey;

    /**
     * Base URL for the IP API.
     *
     * @var string
     */
    private $baseUrl = 'https://pro.ip-api.com/json';

    /**
     * Constructor to initialize the API key from the environment file.
     */
    public function __construct()
    {
        $this->apiKey = env('IP_API_KEY', 'NCx0PDVOcBsh1pQ');
    }

    /**
     * Fetch IP-related data from the API.
     *
     * @param string $ipAddress
     * @return array|null
     */
    public function getIpDetails(string $ipAddress): ?array
    {
        $fields = implode(',', [
            'status',
            'country',
            'countryCode',
            'city',
            'timezone',
            'query',
            'as',
            'org',
            'isp',
        ]);

        $url = "{$this->baseUrl}/{$ipAddress}?key={$this->apiKey}&fields={$fields}";

        $response = Http::get($url);

        // Check if the response is successful
        if ($response->successful()) {
            $data = $response->json();

            if (isset($data['status']) && $data['status'] === 'success') {
                return $data;
            }
        }

        // Return null in case of a failure
        return null;
    }
}