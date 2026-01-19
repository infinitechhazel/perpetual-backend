<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LegitimacyRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class LegitimacyController extends Controller
{
    /**
     * List legitimacy requests for the authenticated user
     */
    public function index(Request $request)
    {
        $user = Auth::user();

        if (! $user->isMember()) {
            return response()->json([
                'success' => false,
                'message' => 'Only users can view their own legitimacy requests.',
            ], 403);
        }

        $query = LegitimacyRequest::where('user_id', $user->id);

        // Optional: filter by status
        if ($request->filled('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        $perPage = $request->get('per_page', 15);
        $requests = $query->latest()->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $requests,
        ]);
    }

    /**
     * Submit a legitimacy request (only users)
     */
    public function store(Request $request)
    {
        $user = Auth::user();

        if (! $user->isMember()) {
            return response()->json([
                'success' => false,
                'message' => 'Only users can submit legitimacy requests.',
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'alias' => 'required|string|max:255',
            'chapter' => 'required|string|max:255',
            'position' => 'required|string|max:255',
            'fraternity_number' => 'required|string|max:255|exists:users,fraternity_number',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $legitimacy = LegitimacyRequest::create([
                'user_id' => $user->id,
                'alias' => $request->alias,
                'chapter' => $request->chapter,
                'position' => $request->position,
                'fraternity_number' => $request->fraternity_number,
                'status' => 'pending',
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Legitimacy request submitted successfully.',
                'data' => $legitimacy,
            ], 201);

        } catch (\Exception $e) {
            Log::error('Legitimacy submission failed', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to submit legitimacy request',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Admin: list all legitimacy requests (with search/filter)
     */
    public function adminIndex(Request $request)
    {
        $admin = Auth::user();

        if (! $admin->isAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Only admins can view all legitimacy requests.',
            ], 403);
        }

        $query = LegitimacyRequest::query();

        // Search and filter
        if ($request->filled('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('alias', 'like', "%{$search}%")
                    ->orWhere('chapter', 'like', "%{$search}%")
                    ->orWhere('position', 'like', "%{$search}%")
                    ->orWhere('fraternity_number', 'like', "%{$search}%")
                    ->orWhere('signatory_name', 'like', "%{$search}%");
            });
        }

        $perPage = $request->get('per_page', 15);
        $requests = $query->latest()->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $requests,
        ]);
    }

    /**
     * Admin: create legitimacy request
     */
    public function adminStore(Request $request)
    {
        $admin = Auth::user();

        if (! $admin->isAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Only admins can create legitimacy requests.',
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'user_id' => 'required|exists:users,id',
            'alias' => 'required|string|max:255',
            'chapter' => 'required|string|max:255',
            'position' => 'required|string|max:255',
            'fraternity_number' => 'required|string|max:255|exists:users,fraternity_number',
            'status' => 'sometimes|in:pending,approved,rejected',
            'admin_note' => 'nullable|string|max:500',
            'signatory_name' => 'sometimes|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $legitimacy = LegitimacyRequest::create([
                'user_id' => $request->user_id,
                'alias' => $request->alias,
                'chapter' => $request->chapter,
                'position' => $request->position,
                'fraternity_number' => $request->fraternity_number,
                'status' => $request->status ?? 'pending',
                'admin_note' => $request->admin_note,
                'signatory_name' => $request->signatory_name,
                'approved_at' => $request->status === 'approved' ? now() : null,
            ]);

            Log::info('Legitimacy request created by admin', [
                'request_id' => $legitimacy->id,
                'admin_id' => $admin->id,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Legitimacy request created successfully by admin.',
                'data' => $legitimacy,
            ], 201);

        } catch (\Exception $e) {
            Log::error('Admin legitimacy creation failed', [
                'admin_id' => $admin->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to create legitimacy request',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Admin can update any fields of a legitimacy request
     */
    public function adminUpdate(Request $request, $id)
    {
        $admin = Auth::user();

        if (! $admin->isAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Only admins can update legitimacy requests.',
            ], 403);
        }

        $legitimacy = LegitimacyRequest::find($id);
        if (! $legitimacy) {
            return response()->json([
                'success' => false,
                'message' => 'Legitimacy request not found.',
            ], 404);
        }

        // Validation: any field can be updated
        $validator = Validator::make($request->all(), [
            'alias' => 'sometimes|string|max:255',
            'chapter' => 'sometimes|string|max:255',
            'position' => 'sometimes|string|max:255',
            'fraternity_number' => 'sometimes|string|max:255',
            'status' => 'sometimes|in:pending,approved,rejected',
            'admin_note' => 'nullable|string|max:500',
            'signatory_name' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        // Update only fields provided in request
        $fieldsToUpdate = $request->only([
            'alias',
            'chapter',
            'position',
            'fraternity_number',
            'status',
            'admin_note',
            'signatory_name',
        ]);

        $legitimacy->fill($fieldsToUpdate);

        // Automatically set approved_at if status is 'approved'
        if (isset($fieldsToUpdate['status']) && $fieldsToUpdate['status'] === 'approved') {
            $legitimacy->approved_at = now();
        } elseif (isset($fieldsToUpdate['status']) && $fieldsToUpdate['status'] !== 'approved') {
            // Clear approved_at if status changes away from approved
            $legitimacy->approved_at = null;
        }

        $legitimacy->save();

        Log::info('Legitimacy request updated by admin', [
            'request_id' => $legitimacy->id,
            'admin_id' => $admin->id,
            'fields_updated' => $fieldsToUpdate,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Legitimacy request updated successfully.',
            'data' => $legitimacy,
        ]);
    }
}
