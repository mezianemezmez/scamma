<?php

namespace App\Http\Controllers;

use App\Models\CustomForm;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class CustomFormController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(): JsonResponse
    {
        try {
            $customForms = CustomForm::orderBy('created_at', 'desc')->get();

            return response()->json([
                'success' => true,
                'data' => $customForms,
                'message' => 'Custom forms retrieved successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve custom forms',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Store a newly created resource in storage.
     * Only allow one form per user.
     */
    public function store(Request $request): JsonResponse
    {
        try {
            // Remove user check: allow multiple custom forms

            $validated = $request->validate([
                'title' => 'required|string|max:255',
                'description' => 'nullable|string',
                'fields' => 'required|array',
                'fields.*.type' => 'required|string',
                'fields.*.label' => 'nullable|string',
                'fields.*.name' => 'nullable|string',
                'fields.*.content' => 'nullable|string', // HTML content allowed
                'fields.*.options' => 'nullable|array',
                'fields.*.validation' => 'nullable|array',
                'fields.*.required' => 'nullable|boolean',
                'fields.*.placeholder' => 'nullable|string',
                'fields.*.order' => 'nullable|integer',
                'fields.*.buttonText' => 'nullable|string',
                'fields.*.buttonVariant' => 'nullable|string',
                'fields.*.inputType' => 'nullable|string|in:text,tel,email,password,number',
                'fields.*.maxLength' => 'nullable|integer|min:1',
                'fields.*.minLength' => 'nullable|integer|min:0',
                'fields.*.alertType' => 'nullable|string|in:info,warning,error,success',
                'fields.*.alertMessage' => 'nullable|string',
                'fields.*.textColor' => 'nullable|string',
                'fields.*.backgroundColor' => 'nullable|string',
                'settings' => 'nullable|array',
                'is_active' => 'nullable|boolean',
            ]);

            // Optionally, you can still assign a user_id if it's passed in the request
            if ($request->has('user_id')) {
                $validated['user_id'] = $request->input('user_id');
            }
            $validated['is_active'] = $validated['is_active'] ?? true;

            $customForm = CustomForm::create($validated);

            return response()->json([
                'success' => true,
                'data' => $customForm,
                'message' => 'Custom form created successfully'
            ], 201);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create custom form',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id): JsonResponse
    {
        try {
            $customForm = CustomForm::findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => $customForm,
                'message' => 'Custom form retrieved successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Custom form not found',
                'error' => $e->getMessage()
            ], 404);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        try {
            $customForm = CustomForm::findOrFail($id);

            $validated = $request->validate([
                'title' => 'required|string|max:255',
                'description' => 'nullable|string',
                'fields' => 'required|array',
                'fields.*.type' => 'required|string',
                'fields.*.label' => 'nullable|string',
                'fields.*.name' => 'nullable|string',
                'fields.*.content' => 'nullable|string', // HTML content allowed
                'fields.*.options' => 'nullable|array',
                'fields.*.validation' => 'nullable|array',
                'fields.*.required' => 'nullable|boolean',
                'fields.*.placeholder' => 'nullable|string',
                'fields.*.order' => 'nullable|integer',
                'fields.*.buttonText' => 'nullable|string',
                'fields.*.buttonVariant' => 'nullable|string',
                'fields.*.inputType' => 'nullable|string|in:text,tel,email,password,number',
                'fields.*.maxLength' => 'nullable|integer|min:1',
                'fields.*.minLength' => 'nullable|integer|min:0',
                'fields.*.alertType' => 'nullable|string|in:info,warning,error,success',
                'fields.*.alertMessage' => 'nullable|string',
                'fields.*.textColor' => 'nullable|string',
                'fields.*.backgroundColor' => 'nullable|string',
                'settings' => 'nullable|array',
                'is_active' => 'nullable|boolean',
            ]);

            // Optionally update user_id if passed
            if ($request->has('user_id')) {
                $validated['user_id'] = $request->input('user_id');
            }

            $customForm->update($validated);

            return response()->json([
                'success' => true,
                'data' => $customForm->fresh(),
                'message' => 'Custom form updated successfully'
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update custom form',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id): JsonResponse
    {
        try {
            $customForm = CustomForm::findOrFail($id);

            $customForm->delete();

            return response()->json([
                'success' => true,
                'message' => 'Custom form deleted successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete custom form',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}