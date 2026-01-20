<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class UserController extends Controller
{
    public function index(Request $request)
    {
        try {
            $authUser = auth()->user();

            // ✅ Admin-only access
            if (! $authUser || $authUser->role !== 'admin') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized',
                ], 403);
            }

            $query = User::query()->select([
                'id',
                'name',
                'email',
                'phone_number',
                'address',
                'school_registration_number',
                'fraternity_number',
                'status',
                'role',
                'rejection_reason',
                'created_at',
                'updated_at',
                'email_verified_at',
            ]);

            // Filter by status
            if ($request->filled('status') && $request->status !== 'all') {
                $query->where('status', $request->status);
            }

            // Search
            if ($request->filled('search')) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('phone_number', 'like', "%{$search}%")
                        ->orWhere('address', 'like', "%{$search}%")
                        ->orWhere('school_registration_number', 'like', "%{$search}%")
                        ->orWhere('fraternity_number', 'like', "%{$search}%");
                });
            }

            $perPage = $request->get('per_page', 15);
            $users = $query->latest()->paginate($perPage);

            return response()->json([
                'success' => true,
                'data' => $users,
            ]);

        } catch (\Throwable $e) {
            Log::error('Error fetching users', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch users',
            ], 500);
        }
    }

    public function show($id)
    {
        try {
            $user = User::users()
                ->select([
                    'id',
                    'name',
                    'email',
                    'phone_number',
                    'address',
                    'school_registration_number',
                    'fraternity_number',
                    'status',
                    'role',
                    'rejection_reason',
                    'created_at',
                    'updated_at',
                    'email_verified_at',
                ])
                ->find($id);

            if (! $user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not found',
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $user,
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching user', [
                'user_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch user',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function updateStatus(Request $request, $id)
    {
        $validated = $request->validate([
            'status' => 'required|in:pending,approved,rejected,deactivated',
            'rejection_reason' => 'nullable|required_if:status,rejected,deactivated|string',
        ]);

        $user = User::find($id);

        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'User not found',
            ], 404);
        }

        $user->update([
            'status' => $validated['status'],
            'rejection_reason' => in_array($validated['status'], ['rejected', 'deactivated'])
                ? $validated['rejection_reason']
                : null,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'User status updated successfully',
            'data' => $user->fresh(),
        ]);
    }

    public function statistics()
    {
        try {
            $stats = [
                'total' => User::users()->count(),
                'pending' => User::users()->pending()->count(),
                'approved' => User::users()->approved()->count(),
                'rejected' => User::users()->where('status', 'rejected')->count(),
                'deactivated' => User::users()->deactivated()->count(),
            ];

            return response()->json([
                'success' => true,
                'data' => $stats,
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching user statistics', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch statistics',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
