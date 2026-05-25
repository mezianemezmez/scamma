<?php

namespace App\Http\Controllers;

use App\Models\Visits;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class VisitsController extends Controller
{
    /**
     * Display a listing of the visits.
     */
    public function index()
    {
        try {
            $visits = Visits::orderBy('created_at', 'desc')->get();

            return response()->json([
                'success' => true,
                'data' => $visits,
                'total' => $visits->count(),
                'message' => 'Visits retrieved successfully'
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to fetch visits', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch visits'
            ], 500);
        }
    }

    /**
     * Store a newly created visit in storage.
     */
    public function store(Request $request)
    {
        $validatedData = $request->validate([
            'unique_id' => 'required|string|max:255',
            'ip_address' => 'required|ip',
            'country' => 'required|string|max:255',
            'country_code' => 'required|string|max:2',
            'isp' => 'required|string|max:255',
            'language' => 'nullable|string|max:10',
            'user_agent' => 'nullable|string',
        ]);

        try {
            // Check if visit with this unique_id already exists
            $existingVisit = Visits::where('unique_id', $validatedData['unique_id'])->first();
            
            if ($existingVisit) {
                // Update existing visit's timestamp
                $existingVisit->touch();
                
                return response()->json([
                    'success' => true,
                    'data' => $existingVisit->fresh(),
                    'message' => 'Visit updated successfully'
                ]);
            }

            $visit = Visits::create($validatedData);

            return response()->json([
                'success' => true,
                'data' => $visit,
                'message' => 'Visit created successfully'
            ], 201);
        } catch (\Exception $e) {
            Log::error('Failed to create visit', [
                'error' => $e->getMessage(),
                'data' => $validatedData
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to create visit'
            ], 500);
        }
    }

    /**
     * Display the specified visit.
     */
    public function show(Visits $visit)
    {
        try {
            return response()->json([
                'success' => true,
                'data' => $visit,
                'message' => 'Visit retrieved successfully'
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to fetch visit', [
                'error' => $e->getMessage(),
                'visit_id' => $visit->getKey()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch visit'
            ], 500);
        }
    }

    /**
     * Update the specified visit in storage.
     */
    public function update(Request $request, Visits $visit)
    {
        $validatedData = $request->validate([
            'unique_id' => 'sometimes|string|max:255',
            'ip_address' => 'sometimes|ip',
            'country' => 'sometimes|string|max:255',
            'country_code' => 'sometimes|string|max:2',
            'isp' => 'sometimes|string|max:255',
            'language' => 'nullable|string|max:10',
            'user_agent' => 'nullable|string',
        ]);

        try {
            $visit->update($validatedData);

            return response()->json([
                'success' => true,
                'data' => $visit->fresh(),
                'message' => 'Visit updated successfully'
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to update visit', [
                'error' => $e->getMessage(),
                'visit_id' => $visit->getKey(),
                'data' => $validatedData
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to update visit'
            ], 500);
        }
    }

    /**
     * Remove the specified visit from storage.
     */
    public function destroy(Visits $visit)
    {
        try {
            $visit->delete();

            return response()->json([
                'success' => true,
                'message' => 'Visit deleted successfully'
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to delete visit', [
                'error' => $e->getMessage(),
                'visit_id' => $visit->getKey()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to delete visit'
            ], 500);
        }
    }

    /**
     * Remove multiple visits from storage.
     */
    public function bulkDestroy(Request $request)
    {
        $validatedData = $request->validate([
            'visit_ids' => 'required|array',
            'visit_ids.*' => 'integer|exists:visits,id',
        ]);

        try {
            $deletedCount = Visits::whereIn('id', $validatedData['visit_ids'])->delete();

            return response()->json([
                'success' => true,
                'message' => "Successfully deleted {$deletedCount} visit(s)",
                'deleted_count' => $deletedCount
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to bulk delete visits', [
                'error' => $e->getMessage(),
                'visit_ids' => $validatedData['visit_ids']
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to delete visits'
            ], 500);
        }
    }

    /**
     * Get visit statistics.
     */
    public function stats()
    {
        try {
            $totalVisits = Visits::count();
            $uniqueVisitors = Visits::distinct('unique_id')->count();
            $uniqueCountries = Visits::distinct('country')->count();
            $uniqueISPs = Visits::distinct('isp')->count();
            
            // Today's visits
            $todayVisits = Visits::whereDate('created_at', today())->count();
            
            // This week's visits
            $weekVisits = Visits::whereBetween('created_at', [
                now()->startOfWeek(),
                now()->endOfWeek()
            ])->count();

            $stats = [
                'total_visits' => $totalVisits,
                'unique_visitors' => $uniqueVisitors,
                'unique_countries' => $uniqueCountries,
                'unique_isps' => $uniqueISPs,
                'today_visits' => $todayVisits,
                'week_visits' => $weekVisits,
            ];

            return response()->json([
                'success' => true,
                'data' => $stats,
                'message' => 'Statistics retrieved successfully'
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to fetch visit statistics', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch statistics'
            ], 500);
        }
    }
}
