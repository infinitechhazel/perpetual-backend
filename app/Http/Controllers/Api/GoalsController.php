<?php

namespace App\Http\Controllers\Api;

use App\Models\Goals;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use App\Http\Controllers\Controller;

class GoalsController extends Controller
{
    /**
     * Display the goals data (Public)
     */
    public function index()
    {
        try {
            $goals = Goals::first();

            if (!$goals) {
                return response()->json([
                    'success' => false,
                    'message' => 'No goals data found',
                    'data' => null
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $goals,
                'message' => 'Goals data retrieved successfully'
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error fetching goals data: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch goals data',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the goals data for admin
     */
    public function show()
    {
        try {
            $goals = Goals::first();

            if (!$goals) {
                return response()->json([
                    'success' => true,
                    'data' => null,
                    'message' => 'No goals data found'
                ], 200);
            }

            return response()->json([
                'success' => true,
                'data' => $goals,
                'message' => 'Goals data retrieved successfully'
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error fetching goals data: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch goals data',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Store or update goals data
     * - If table is empty: create new record
     * - If record exists with blank goals columns: update it
     */
    public function store(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'goals_header' => 'required|string|max:255',
                'goals_title' => 'required|string|max:255',
                'goals_description' => 'required|string',
                'goals_card_icon' => 'nullable|array',
                'goals_card_icon.*' => 'nullable|string|max:100',
                'goals_card_title' => 'nullable|array',
                'goals_card_title.*' => 'nullable|string|max:255',
                'goals_card_content' => 'nullable|array',
                'goals_card_content.*' => 'nullable|string',
                'goals_card_list' => 'nullable|array',
                'goals_card_list.*' => 'nullable|string',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            DB::beginTransaction();

            $data = [
                'goals_header' => $request->goals_header,
                'goals_title' => $request->goals_title,
                'goals_description' => $request->goals_description,
                'goals_card_icon' => json_encode($request->goals_card_icon ?? []),
                'goals_card_title' => json_encode($request->goals_card_title ?? []),
                'goals_card_content' => json_encode($request->goals_card_content ?? []),
                'goals_card_list' => json_encode($request->goals_card_list ?? []),
            ];

            $existingRecord = Goals::first();

            if (!$existingRecord) {
                // Table is empty, create new record
                $goals = Goals::create($data);
                $message = 'Goals data created successfully';
            } else {
                // Record exists, update it
                $existingRecord->update($data);
                $goals = $existingRecord->fresh();
                $message = 'Goals data updated successfully';
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => $message,
                'data' => $goals
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Error storing goals', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to store goals data',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update the goals data in storage
     */
    public function update(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'goals_header' => 'required|string|max:255',
            'goals_title' => 'required|string|max:255',
            'goals_description' => 'required|string',
            'goals_card_icon' => 'nullable|array',
            'goals_card_icon.*' => 'nullable|string|max:100',
            'goals_card_title' => 'nullable|array',
            'goals_card_title.*' => 'nullable|string|max:255',
            'goals_card_content' => 'nullable|array',
            'goals_card_content.*' => 'nullable|string',
            'goals_card_list' => 'nullable|array',
            'goals_card_list.*' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            DB::beginTransaction();

            $data = [
                'goals_header' => $request->goals_header,
                'goals_title' => $request->goals_title,
                'goals_description' => $request->goals_description,
                'goals_card_icon' => json_encode($request->goals_card_icon ?? []),
                'goals_card_title' => json_encode($request->goals_card_title ?? []),
                'goals_card_content' => json_encode($request->goals_card_content ?? []),
                'goals_card_list' => json_encode($request->goals_card_list ?? []),
            ];

            $goals = Goals::first();

            if (!$goals) {
                // If no record exists, create one
                $goals = Goals::create($data);
                $message = 'Goals created successfully';
            } else {
                // Update existing record
                $goals->update($data);
                $message = 'Goals updated successfully';
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => $message,
                'data' => $goals
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Goals update error', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Update failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the goals data from storage
     */
    public function destroy()
    {
        try {
            $goals = Goals::first();

            if (!$goals) {
                return response()->json([
                    'success' => false,
                    'message' => 'No goals data found'
                ], 404);
            }

            DB::beginTransaction();

            $goals->delete();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Goals data deleted successfully'
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error deleting goals data: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete goals data',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}