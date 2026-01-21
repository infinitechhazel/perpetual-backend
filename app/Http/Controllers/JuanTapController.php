<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\JuanTapProfile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;

class JuanTapController extends Controller
{
    /**
     * Get authenticated user's JuanTap profile
     */
    public function show(Request $request)
    {
        $profile = $request->user()->juantapProfile;

        if (!$profile) {
            return response()->json([
                'success' => false,
                'message' => 'No JuanTap profile found',
                'data' => null
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $profile
        ]);
    }

    /**
     * Create JuanTap profile
     */
    public function store(Request $request)
    {
        if ($request->user()->juantapProfile) {
            return response()->json([
                'success' => false,
                'message' => 'JuanTap profile already exists'
            ], 409);
        }

        $validator = Validator::make($request->all(), [
            'profile_url'  => 'nullable|url|max:255',
            'qr_code'      => 'nullable|string',
            'status'       => 'nullable|in:active,inactive',
            'subscription' => 'nullable|in:free,basic,premium',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $profile = $request->user()->juantapProfile()->create([
            'profile_url'  => $request->profile_url,
            'qr_code'      => $request->qr_code,
            'status'       => $request->status ?? 'inactive',
            'subscription' => $request->subscription ?? 'free',
        ]);

        Log::info('JuanTap profile created', [
            'user_id' => $request->user()->id,
            'profile_id' => $profile->id
        ]);

        return response()->json([
            'success' => true,
            'message' => 'JuanTap profile created successfully',
            'data' => $profile
        ], 201);
    }

    /**
     * Update JuanTap profile
     */
    public function update(Request $request)
    {
        $profile = $request->user()->juantapProfile;

        if (!$profile) {
            return response()->json([
                'success' => false,
                'message' => 'No JuanTap profile found'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'profile_url'  => 'nullable|url|max:255',
            'qr_code'      => 'nullable|string',
            'status'       => 'nullable|in:active,inactive',
            'subscription' => 'nullable|in:free,basic,premium',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        // 🔒 Only update fields that are actually sent
        $profile->update(array_filter(
            $request->only([
                'profile_url',
                'qr_code',
                'status',
                'subscription'
            ]),
            fn ($value) => !is_null($value)
        ));

        Log::info('JuanTap profile updated', [
            'user_id' => $request->user()->id,
            'profile_id' => $profile->id
        ]);

        return response()->json([
            'success' => true,
            'message' => 'JuanTap profile updated successfully',
            'data' => $profile
        ]);
    }

    /**
     * Delete JuanTap profile
     */
    public function destroy(Request $request)
    {
        $profile = $request->user()->juantapProfile;

        if (!$profile) {
            return response()->json([
                'success' => false,
                'message' => 'No JuanTap profile found'
            ], 404);
        }

        $profile->delete();

        Log::info('JuanTap profile deleted', [
            'user_id' => $request->user()->id
        ]);

        return response()->json([
            'success' => true,
            'message' => 'JuanTap profile deleted successfully'
        ]);
    }
}
