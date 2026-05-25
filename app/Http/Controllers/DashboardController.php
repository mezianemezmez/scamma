<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Visits;
use App\Models\Stats;
use App\Models\CustomForm;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function stats()
    {
        // Get stats from Stats model using modern Laravel syntax
        $stats = Stats::first();
        $clientVisits = $stats?->client ?? 0;
        $botVisits = $stats?->bot ?? 0;
        $cards = $stats?->card ?? 0;
        
        // Basic counts using modern syntax
        $totalClients = Client::count();
        $uniqueClients = Client::distinct('unique_id')->count();
        $totalVisits = Visits::count();
        $customForms = CustomForm::where('is_active', true)->count();

        // Recent activity (last 24 hours) using now() helper
        $recentClients = Client::whereDate('created_at', '>=', now()->subDay())->count();
        $recentVisits = Visits::whereDate('created_at', '>=', now()->subDay())->count();

        // Top countries using modern aggregation
        $topCountries = Visits::query()
            ->select('country')
            ->selectRaw('count(*) as total')
            ->groupBy('country')
            ->orderByDesc('total')
            ->limit(5)
            ->get();

        // Top ISPs using modern aggregation
        $topIsps = Visits::query()
            ->select('isp')
            ->selectRaw('count(*) as total')
            ->groupBy('isp')
            ->orderByDesc('total')
            ->limit(5)
            ->get();

        // Daily visits for the last 7 days using modern date functions
        $dailyVisits = Visits::query()
            ->selectRaw('DATE(created_at) as date')
            ->selectRaw('count(*) as total')
            ->whereDate('created_at', '>=', now()->subDays(7))
            ->groupByRaw('DATE(created_at)')
            ->orderByDesc('date')
            ->get();

        // Client actions stats using modern query builder
        $clientActions = Client::query()
            ->select('action')
            ->selectRaw('count(*) as total')
            ->whereNotNull('action')
            ->groupBy('action')
            ->orderByDesc('total')
            ->get();

        // Languages distribution using modern query builder
        $languages = Visits::query()
            ->select('language')
            ->selectRaw('count(*) as total')
            ->groupBy('language')
            ->orderByDesc('total')
            ->limit(10)
            ->get();

        // Banned clients using where method
        $bannedClients = Client::where('ban', true)->count();

        return [
            'success' => true,
            'data' => [
                'overview' => [
                    'total_clients' => $totalClients,
                    'unique_clients' => $uniqueClients,
                    'total_visits' => $totalVisits,
                    'client_visits' => $clientVisits,
                    'bot_visits' => $botVisits,
                    'cards' => $cards,
                    'active_forms' => $customForms,
                    'banned_clients' => $bannedClients,
                    'recent_clients_24h' => $recentClients,
                    'recent_visits_24h' => $recentVisits,
                ],
                'charts' => [
                    'daily_visits' => $dailyVisits,
                    'top_countries' => $topCountries,
                    'top_isps' => $topIsps,
                    'client_actions' => $clientActions,
                    'languages' => $languages,
                ],
                'stats' => $stats ? [
                    'id' => $stats->id,
                    'client' => $stats->client,
                    'bot' => $stats->bot,
                    'click' => $stats->click,
                    'created_at' => $stats->created_at,
                    'updated_at' => $stats->updated_at
                ] : [
                    'id' => 1,
                    'client' => 0,
                    'bot' => 0,
                    'click' => 0,
                    'created_at' => null,
                    'updated_at' => null
                ]
            ]
        ];
    }

    public function resetStats()
    {
        // Delete all data from tables using modern Laravel syntax
        Stats::truncate();
        Client::truncate();
        Visits::truncate();
        CustomForm::truncate();
        
        // Create the initial stats row with ID 1 using modern syntax
        Stats::create([
            'id' => 1,
            'click' => 0,
            'client' => 0,
            'bot' => 0,
            'card' => 0,
        ]);
        
        return [
            'success' => true,
            'message' => 'Statistics, client data, and visits have been reset successfully. All IDs will start from 1.'
        ];
    }
}
