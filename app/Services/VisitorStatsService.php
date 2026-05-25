<?php

namespace App\Services;

use App\Models\Visits;
use Jaybizzle\CrawlerDetect\CrawlerDetect;
use Jenssegers\Agent\Agent;
use Illuminate\Support\Facades\DB;

class VisitorStatsService
{
    /**
     * Get visitor statistics (bots vs real clients)
     * @param array $filters - Optional filters (date range, unique_id, etc.)
     * @return array
     */
    public function getVisitorStats($filters = [])
    {
        $query = Visits::query();

        // Apply filters if provided
        if (isset($filters['start_date'])) {
            $query->where('created_at', '>=', $filters['start_date']);
        }
        
        if (isset($filters['end_date'])) {
            $query->where('created_at', '<=', $filters['end_date']);
        }
        
        if (isset($filters['unique_id'])) {
            $query->where('unique_id', $filters['unique_id']);
        }

        $visits = $query->get();
        
        $botCount = 0;
        $clientCount = 0;
        $botDetails = [];
        $clientDetails = [];

        foreach ($visits as $visit) {
            $isBot = $this->isBotVisit($visit->user_agent);
            
            if ($isBot) {
                $botCount++;
                $botDetails[] = [
                    'unique_id' => $visit->unique_id,
                    'ip_address' => $visit->ip_address,
                    'user_agent' => $visit->user_agent,
                    'country' => $visit->country,
                    'created_at' => $visit->created_at,
                    'detection_reason' => $this->getDetectionReason($visit->user_agent)
                ];
            } else {
                $clientCount++;
                $clientDetails[] = [
                    'unique_id' => $visit->unique_id,
                    'ip_address' => $visit->ip_address,
                    'user_agent' => $visit->user_agent,
                    'country' => $visit->country,
                    'created_at' => $visit->created_at
                ];
            }
        }

        return [
            'total_visits' => $visits->count(),
            'bot_count' => $botCount,
            'client_count' => $clientCount,
            'bot_percentage' => $visits->count() > 0 ? round(($botCount / $visits->count()) * 100, 2) : 0,
            'client_percentage' => $visits->count() > 0 ? round(($clientCount / $visits->count()) * 100, 2) : 0,
            'bot_details' => $botDetails,
            'client_details' => $clientDetails
        ];
    }

    /**
     * Get daily visitor statistics
     * @param int $days - Number of days to analyze
     * @return array
     */
    public function getDailyStats($days = 7)
    {
        $visits = Visits::where('created_at', '>=', now()->subDays($days))
            ->select('user_agent', 'created_at')
            ->get()
            ->groupBy(function($visit) {
                return $visit->created_at->format('Y-m-d');
            });

        $dailyStats = [];

        foreach ($visits as $date => $dayVisits) {
            $botCount = 0;
            $clientCount = 0;

            foreach ($dayVisits as $visit) {
                if ($this->isBotVisit($visit->user_agent)) {
                    $botCount++;
                } else {
                    $clientCount++;
                }
            }

            $dailyStats[$date] = [
                'date' => $date,
                'total' => $dayVisits->count(),
                'bots' => $botCount,
                'clients' => $clientCount,
                'bot_percentage' => $dayVisits->count() > 0 ? round(($botCount / $dayVisits->count()) * 100, 2) : 0
            ];
        }

        return $dailyStats;
    }

    /**
     * Get top bot user agents
     * @param int $limit
     * @return array
     */
    public function getTopBotUserAgents($limit = 10)
    {
        $visits = Visits::select('user_agent', DB::raw('COUNT(*) as count'))
            ->groupBy('user_agent')
            ->orderBy('count', 'desc')
            ->get();

        $botUserAgents = [];

        foreach ($visits as $visit) {
            if ($this->isBotVisit($visit->user_agent)) {
                $botUserAgents[] = [
                    'user_agent' => $visit->user_agent,
                    'count' => $visit->count,
                    'detection_reason' => $this->getDetectionReason($visit->user_agent)
                ];
            }
        }

        return array_slice($botUserAgents, 0, $limit);
    }

    /**
     * Determine if a visit is from a bot based on user agent
     * @param string $userAgent
     * @return bool
     */
    private function isBotVisit($userAgent)
    {
        // Use CrawlerDetect library (same as middlewares)
        $crawlerDetect = new CrawlerDetect();
        if ($crawlerDetect->isCrawler($userAgent)) {
            return true;
        }

        // Use Jenssegers Agent for robot detection
        $agent = new Agent();
        $agent->setUserAgent($userAgent);
        if ($agent->isRobot()) {
            return true;
        }

        // Additional bot patterns
        $botPatterns = [
            'bot', 'crawler', 'spider', 'scraper', 'curl', 'wget', 
            'python', 'java', 'go-http', 'php', 'requests', 'axios',
            'okhttp', 'apache-httpclient', 'node-fetch', 'postman'
        ];

        foreach ($botPatterns as $pattern) {
            if (stripos($userAgent, $pattern) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get the reason why a user agent was detected as bot
     * @param string $userAgent
     * @return string
     */
    private function getDetectionReason($userAgent)
    {
        $crawlerDetect = new CrawlerDetect();
        if ($crawlerDetect->isCrawler($userAgent)) {
            return 'CrawlerDetect Library';
        }

        $agent = new Agent();
        $agent->setUserAgent($userAgent);
        if ($agent->isRobot()) {
            return 'Jenssegers Agent Robot';
        }

        $botPatterns = [
            'bot' => 'Contains "bot"',
            'crawler' => 'Contains "crawler"',
            'spider' => 'Contains "spider"',
            'scraper' => 'Contains "scraper"',
            'curl' => 'cURL tool',
            'wget' => 'wget tool',
            'python' => 'Python script',
            'java' => 'Java application',
            'go-http' => 'Go HTTP client',
            'php' => 'PHP script',
            'requests' => 'Python requests',
            'axios' => 'Axios HTTP client',
            'okhttp' => 'OkHttp client',
            'apache-httpclient' => 'Apache HTTP client',
            'node-fetch' => 'Node.js fetch',
            'postman' => 'Postman tool'
        ];

        foreach ($botPatterns as $pattern => $reason) {
            if (stripos($userAgent, $pattern) !== false) {
                return $reason;
            }
        }

        return 'Unknown';
    }
}
