<?php

namespace App\Http\Controllers\Api;  // Changed from App\Http\Controllers

use App\Models\Community;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use App\Http\Controllers\Controller;  // Add this line

class CommunityController extends Controller

{
    /**
     * Display the community data (Public)
     */
    public function index()
    {
        try {
            // Fixed: Added ->first() to actually execute the query
            $community = Community::first();

            if (!$community) {
                return response()->json([
                    'success' => false,
                    'message' => 'No community data found',
                    'data' => null
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $community,
                'message' => 'Community data retrieved successfully'
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error fetching community data: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch community data',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the community data for admin
     */
    public function show()
    {
        try {
            $community = Community::first();

            if (!$community) {
                return response()->json([
                    'success' => true,
                    'data' => null,
                    'message' => 'No community data found'
                ], 200);
            }

            return response()->json([
                'success' => true,
                'data' => $community,
                'message' => 'Community data retrieved successfully'
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error fetching community data: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch community data',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Store a newly created community in storage
     */
    public function store(Request $request)
    {
        try {
            // Only one community allowed
            if (Community::exists()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Community data already exists. Please use update instead.'
                ], 400);
            }

            $validator = Validator::make($request->all(), [
                'community_header' => 'required|string|max:255',
                'community_title' => 'required|string|max:255',
                'community_content' => 'required|string',
                'community_list' => 'nullable|string|max:255',
                'community_card_icon' => 'nullable|string|max:100',
                'community_card_number' => 'nullable|string|max:50',
                'community_card_category' => 'nullable|string|max:100',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            DB::beginTransaction();

            $community = Community::create([
                'community_header' => $request->community_header,
                'community_title' => $request->community_title,
                'community_content' => $request->community_content,
                'community_list' => $request->community_list,
                'community_card_icon' => $request->community_card_icon,
                'community_card_number' => $request->community_card_number,
                'community_card_category' => $request->community_card_category,
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Community data created successfully',
                'data' => $community
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Error creating community', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to create community data',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update the community data in storage
     */
    public function update(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'community_header' => 'required|string|max:255',
            'community_title' => 'required|string|max:255',
            'community_content' => 'required|string',
            'community_list' => 'nullable|string|max:255',
            'community_card_icon' => 'nullable|string|max:100',
            'community_card_number' => 'nullable|string|max:50',
            'community_card_category' => 'nullable|string|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $community = Community::first();

            if (!$community) {
                return response()->json([
                    'success' => false,
                    'message' => 'Community not found'
                ], 404);
            }

            $community->update($request->only([
                'community_header',
                'community_title',
                'community_content',
                'community_list',
                'community_card_icon',
                'community_card_number',
                'community_card_category',
            ]));

            return response()->json([
                'success' => true,
                'message' => 'Community updated successfully',
                'data' => $community
            ]);

        } catch (\Exception $e) {
            Log::error('Community update error', [
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
     * Remove the community data from storage
     */
    public function destroy()
    {
        try {
            $community = Community::first();

            if (!$community) {
                return response()->json([
                    'success' => false,
                    'message' => 'No community data found'
                ], 404);
            }

            DB::beginTransaction();

            // Removed lists relationship deletion since it doesn't exist
            $community->delete();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Community data deleted successfully'
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error deleting community data: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete community data',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}