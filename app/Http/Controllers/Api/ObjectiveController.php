<?php

namespace App\Http\Controllers\Api;

use App\Models\Objective;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use App\Http\Controllers\Controller;

class ObjectiveController extends Controller
{
    /**
     * Display the objectives data (Public)
     */
    public function index()
    {
        try {
            $objective = Objective::first();

            if (!$objective) {
                return response()->json([
                    'success' => false,
                    'message' => 'No objectives data found',
                    'data' => null
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $objective,
                'message' => 'Objectives data retrieved successfully'
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error fetching objectives data: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch objectives data',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the objectives data for admin
     */
    public function show()
    {
        try {
            $objective = Objective::first();

            if (!$objective) {
                return response()->json([
                    'success' => true,
                    'data' => null,
                    'message' => 'No objectives data found'
                ], 200);
            }

            return response()->json([
                'success' => true,
                'data' => $objective,
                'message' => 'Objectives data retrieved successfully'
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error fetching objectives data: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch objectives data',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Store or update objectives data
     */
    public function store(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'objectives_header' => 'required|string|max:255',
                'objectives_title' => 'required|string|max:255',
                'objectives_description' => 'required|string',
                'objectives_card_title' => 'required|array',
                'objectives_card_title.*' => 'required|string|max:255',
                'objectives_card_content' => 'required|array',
                'objectives_card_content.*' => 'required|string',
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
                'objectives_header' => $request->objectives_header,
                'objectives_title' => $request->objectives_title,
                'objectives_description' => $request->objectives_description,
                'objectives_card_title' => json_encode($request->objectives_card_title),
                'objectives_card_content' => json_encode($request->objectives_card_content),
            ];

            $existingRecord = Objective::first();

            if (!$existingRecord) {
                $objective = Objective::create($data);
                $message = 'Objectives data created successfully';
            } else {
                $existingRecord->update($data);
                $objective = $existingRecord->fresh();
                $message = 'Objectives data updated successfully';
            }

            DB::commit();

            // Parse JSON back to arrays for response
            $objective->objectives_card_title = json_decode($objective->objectives_card_title, true);
            $objective->objectives_card_content = json_decode($objective->objectives_card_content, true);

            return response()->json([
                'success' => true,
                'message' => $message,
                'data' => $objective
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Error storing objectives', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to store objectives data',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update the objectives data in storage
     */
    public function update(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'objectives_header' => 'required|string|max:255',
            'objectives_title' => 'required|string|max:255',
            'objectives_description' => 'required|string',
            'objectives_card_title' => 'required|array',
            'objectives_card_title.*' => 'required|string|max:255',
            'objectives_card_content' => 'required|array',
            'objectives_card_content.*' => 'required|string',
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
                'objectives_header' => $request->objectives_header,
                'objectives_title' => $request->objectives_title,
                'objectives_description' => $request->objectives_description,
                'objectives_card_title' => json_encode($request->objectives_card_title),
                'objectives_card_content' => json_encode($request->objectives_card_content),
            ];

            $objective = Objective::first();

            if (!$objective) {
                $objective = Objective::create($data);
                $message = 'Objectives created successfully';
            } else {
                $objective->update($data);
                $message = 'Objectives updated successfully';
            }

            DB::commit();

            // Parse JSON back to arrays for response
            $objective->objectives_card_title = json_decode($objective->objectives_card_title, true);
            $objective->objectives_card_content = json_decode($objective->objectives_card_content, true);

            return response()->json([
                'success' => true,
                'message' => $message,
                'data' => $objective
            ]);

        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Objectives update error', [
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
     * Remove the objectives data from storage
     */
    public function destroy()
    {
        try {
            $objective = Objective::first();

            if (!$objective) {
                return response()->json([
                    'success' => false,
                    'message' => 'No objectives data found'
                ], 404);
            }

            DB::beginTransaction();

            $objective->update([
                'objectives_header' => null,
                'objectives_title' => null,
                'objectives_description' => null,
                'objectives_card_title' => null,
                'objectives_card_content' => null,
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Objectives data cleared successfully'
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error clearing objectives data: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to clear objectives data',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}