<?php

namespace App\Http\Controllers\Api;

use App\Models\MissionAndVision;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use App\Http\Controllers\Controller;

class MissionAndVisionController extends Controller
{
    /**
     * Display the mission and vision data (Public)
     */
    public function index()
    {
        try {
            $missionVision = MissionAndVision::first();

            if (!$missionVision) {
                return response()->json([
                    'success' => false,
                    'message' => 'No mission and vision data found',
                    'data' => null
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $missionVision,
                'message' => 'Mission and vision data retrieved successfully'
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error fetching mission and vision data: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch mission and vision data',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the mission and vision data for admin
     */
    public function show()
    {
        try {
            $missionVision = MissionAndVision::first();

            if (!$missionVision) {
                return response()->json([
                    'success' => true,
                    'data' => null,
                    'message' => 'No mission and vision data found'
                ], 200);
            }

            return response()->json([
                'success' => true,
                'data' => $missionVision,
                'message' => 'Mission and vision data retrieved successfully'
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error fetching mission and vision data: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch mission and vision data',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Store or update mission and vision data
     * - If table is empty: create new record
     * - If record exists with blank mission and vision columns: update it
     */
    public function store(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'mission_and_vision_header' => 'required|string|max:255',
                'mission_and_vision_title' => 'required|string|max:255',
                'mission_and_vision_description' => 'required|string',
                'mission_content' => 'required|string',
                'vision_content' => 'required|string',
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
                'mission_and_vision_header' => $request->mission_and_vision_header,
                'mission_and_vision_title' => $request->mission_and_vision_title,
                'mission_and_vision_description' => $request->mission_and_vision_description,
                'mission_content' => $request->mission_content,
                'vision_content' => $request->vision_content,
            ];

            $existingRecord = MissionAndVision::first();

            if (!$existingRecord) {
                // Table is empty, create new record
                $missionVision = MissionAndVision::create($data);
                $message = 'Mission and vision data created successfully';
            } else {
                // Record exists, update it
                $existingRecord->update($data);
                $missionVision = $existingRecord->fresh();
                $message = 'Mission and vision data updated successfully';
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => $message,
                'data' => $missionVision
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Error storing mission and vision', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to store mission and vision data',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update the mission and vision data in storage
     */
    public function update(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'mission_and_vision_header' => 'required|string|max:255',
            'mission_and_vision_title' => 'required|string|max:255',
            'mission_and_vision_description' => 'required|string',
            'mission_content' => 'required|string',
            'vision_content' => 'required|string',
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
                'mission_and_vision_header' => $request->mission_and_vision_header,
                'mission_and_vision_title' => $request->mission_and_vision_title,
                'mission_and_vision_description' => $request->mission_and_vision_description,
                'mission_content' => $request->mission_content,
                'vision_content' => $request->vision_content,
            ];

            $missionVision = MissionAndVision::first();

            if (!$missionVision) {
                // If no record exists, create one
                $missionVision = MissionAndVision::create($data);
                $message = 'Mission and vision created successfully';
            } else {
                // Update existing record
                $missionVision->update($data);
                $message = 'Mission and vision updated successfully';
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => $message,
                'data' => $missionVision
            ]);

        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Mission and vision update error', [
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
     * Remove the mission and vision data from storage
     */
    public function destroy()
    {
        try {
            $missionVision = MissionAndVision::first();

            if (!$missionVision) {
                return response()->json([
                    'success' => false,
                    'message' => 'No mission and vision data found'
                ], 404);
            }

            DB::beginTransaction();

            // Only clear mission and vision fields, don't delete the record
            $missionVision->update([
                'mission_and_vision_header' => null,
                'mission_and_vision_title' => null,
                'mission_and_vision_description' => null,
                'mission_content' => null,
                'vision_content' => null,
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Mission and vision data cleared successfully'
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error clearing mission and vision data: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to clear mission and vision data',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}