<?php

namespace App\Http\Controllers;

use App\Models\Antibots;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class AntibotsController extends Controller
{
    public function index(Request $request)
    {
        $antibots = Antibots::all();

        if ($antibots->isEmpty()) {
            return response()->json([
                'message' => 'No antibots configuration found.',
                'data' => []
            ]);
        }

        return response()->json([
            'message' => 'Antibots configuration retrieved successfully.',
            'data' => $antibots
        ]);
    }

        public function storeOrUpdate(Request $request)
    {
        $validatedData = $request->validate([
            'allowed_countries' => 'nullable|array',
            'allowed_countries.*' => 'string|max:2',
            'blocker_operators' => 'nullable|array',
            'blocker_operators.*' => 'string|max:100',
            'allowed_operators' => 'nullable|array',
            'allowed_operators.*' => 'string|max:100',
            'proxy_protection' => 'nullable|boolean',
            'antibots_protection' => 'nullable|boolean',
            'captcha_protection' => 'nullable|boolean',
        ]);

        try {
            $antibots = Antibots::firstOrNew([]);
            $normalized = $this->normalizeConfiguration($validatedData);
            $antibots->fill($normalized);
            $antibots->save();

            return response()->json([
                'message' => 'Antibots configuration has been updated successfully.',
                'data' => $antibots
            ]);
            
        } catch (\Exception $e) {
            Log::error('Failed to update antibots configuration', [
                'error' => $e->getMessage(),
                'data' => $validatedData
            ]);
            
            return response()->json([
                'message' => 'Failed to update configuration. Please try again.',
            ], 500);
        }
    }

    public function addOperator(Request $request)
    {
        $validatedData = $request->validate([
            'operator' => 'required|string|max:100',
            'type' => ['required', Rule::in(['blocked', 'allowed'])],
        ]);

        try {
            $antibots = Antibots::firstOrNew([]);
            $fieldName = $validatedData['type'] === 'blocked' ? 'blocker_operators' : 'allowed_operators';
            $currentOperators = $this->normalizeOperators($antibots->{$fieldName} ?? []);
            
            $operator = $this->normalizeOperator($validatedData['operator']);

            if (!in_array($operator, $currentOperators)) {
                $currentOperators[] = $operator;
                $antibots->{$fieldName} = array_values($currentOperators);
                $antibots->save();

                return response()->json([
                    'message' => 'Operator added successfully.',
                    'operator' => $operator,
                    'type' => $validatedData['type']
                ]);
            }

            return response()->json([
                'message' => 'Operator already exists.',
            ], 409);
            
        } catch (\Exception $e) {
            Log::error('Failed to add operator', [
                'error' => $e->getMessage(),
                'data' => $validatedData
            ]);
            
            return response()->json([
                'message' => 'Failed to add operator. Please try again.',
            ], 500);
        }
    }

    public function removeOperator(Request $request, $type, $operator)
    {
        $request->validate([
            'type' => Rule::in(['blocked', 'allowed']),
        ]);

        try {
            $antibots = Antibots::first();
            if (!$antibots) {
                return response()->json(['message' => 'Configuration not found.'], 404);
            }

            $fieldName = $type === 'blocked' ? 'blocker_operators' : 'allowed_operators';
            $currentOperators = $this->normalizeOperators($antibots->{$fieldName} ?? []);
            $operatorNormalized = $this->normalizeOperator($operator);
            
            $updatedOperators = array_filter($currentOperators, function($op) use ($operator) {
                return $op !== $this->normalizeOperator($operator);
            });

            $antibots->{$fieldName} = array_values($updatedOperators);
            $antibots->save();

            return response()->json([
                'message' => 'Operator removed successfully.',
                'operator' => $operatorNormalized,
                'type' => $type
            ]);
            
        } catch (\Exception $e) {
            Log::error('Failed to remove operator', [
                'error' => $e->getMessage(),
                'operator' => $operator,
                'type' => $type
            ]);
            
            return response()->json([
                'message' => 'Failed to remove operator. Please try again.',
            ], 500);
        }
    }

    public function bulkOperators(Request $request)
    {
        $validatedData = $request->validate([
            'action' => ['required', Rule::in(['clear_all', 'add_all_predefined'])],
            'type' => ['required', Rule::in(['blocked', 'allowed'])],
            'operators' => 'nullable|array',
            'operators.*' => 'string|max:100',
        ]);

        try {
            $antibots = Antibots::firstOrNew([]);
            $fieldName = $validatedData['type'] === 'blocked' ? 'blocker_operators' : 'allowed_operators';

            switch ($validatedData['action']) {
                case 'clear_all':
                    $antibots->{$fieldName} = [];
                    break;
                
                case 'add_all_predefined':
                    $currentOperators = $this->normalizeOperators($antibots->{$fieldName} ?? []);
                    $newOperators = $this->normalizeOperators($validatedData['operators'] ?? []);
                    $mergedOperators = array_unique(array_merge($currentOperators, $newOperators));
                    $antibots->{$fieldName} = array_values($mergedOperators);
                    break;
            }

            $antibots->save();

            return response()->json([
                'message' => 'Bulk operation completed successfully.',
                'action' => $validatedData['action'],
                'type' => $validatedData['type']
            ]);
            
        } catch (\Exception $e) {
            Log::error('Failed to perform bulk operation', [
                'error' => $e->getMessage(),
                'data' => $validatedData
            ]);
            
            return response()->json([
                'message' => 'Failed to perform bulk operation. Please try again.',
            ], 500);
        }
    }

    public function addCountry(Request $request)
    {
        $validatedData = $request->validate([
            'country' => 'required|string|max:2',
        ]);

        try {
            $antibots = Antibots::firstOrNew([]);
            $currentCountries = $this->normalizeCountries($antibots->allowed_countries ?? []);
            $country = $this->normalizeCountry($validatedData['country']);
            
            if (!in_array($country, $currentCountries)) {
                $currentCountries[] = $country;
                $antibots->allowed_countries = $currentCountries;
                $antibots->save();

                return response()->json([
                    'message' => 'Country added successfully.',
                    'country' => $country
                ]);
            }

            return response()->json([
                'message' => 'Country already exists.',
            ], 409);
            
        } catch (\Exception $e) {
            Log::error('Failed to add country', [
                'error' => $e->getMessage(),
                'data' => $validatedData
            ]);
            
            return response()->json([
                'message' => 'Failed to add country. Please try again.',
            ], 500);
        }
    }

    public function removeCountry(Request $request, $country)
    {
        try {
            $antibots = Antibots::first();
            if (!$antibots) {
                return response()->json(['message' => 'Configuration not found.'], 404);
            }

            $currentCountries = $this->normalizeCountries($antibots->allowed_countries ?? []);
            $countryNormalized = $this->normalizeCountry($country);
            $updatedCountries = array_filter($currentCountries, function($c) use ($countryNormalized) {
                return $c !== $countryNormalized;
            });

            $antibots->allowed_countries = array_values($updatedCountries);
            $antibots->save();

            return response()->json([
                'message' => 'Country removed successfully.',
                'country' => $countryNormalized
            ]);
            
        } catch (\Exception $e) {
            Log::error('Failed to remove country', [
                'error' => $e->getMessage(),
                'country' => $country
            ]);
            
            return response()->json([
                'message' => 'Failed to remove country. Please try again.',
            ], 500);
        }
    }

    private function normalizeConfiguration(array $data): array
    {
        if (array_key_exists('allowed_countries', $data)) {
            $data['allowed_countries'] = $this->normalizeCountries($data['allowed_countries'] ?? []);
        }

        if (array_key_exists('blocker_operators', $data)) {
            $data['blocker_operators'] = $this->normalizeOperators($data['blocker_operators'] ?? []);
        }

        if (array_key_exists('allowed_operators', $data)) {
            $data['allowed_operators'] = $this->normalizeOperators($data['allowed_operators'] ?? []);
        }

        return $data;
    }

    private function normalizeCountries(null|array $countries): array
    {
        return collect($countries ?? [])
            ->filter(fn ($country) => is_string($country) && $country !== '')
            ->map(fn ($country) => $this->normalizeCountry($country))
            ->unique()
            ->values()
            ->all();
    }

    private function normalizeCountry(string $country): string
    {
        return strtoupper(substr(trim($country), 0, 2));
    }

    private function normalizeOperators(null|array $operators): array
    {
        return collect($operators ?? [])
            ->filter(fn ($operator) => is_string($operator) && $operator !== '')
            ->map(fn ($operator) => $this->normalizeOperator($operator))
            ->unique()
            ->values()
            ->all();
    }

    private function normalizeOperator(string $operator): string
    {
        return mb_strtolower(trim($operator), 'UTF-8');
    }
}
