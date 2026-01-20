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

        $query = LegitimacyRequest::with([
            'user:id,name,email',
        ])->where('user_id', $user->id);

        // Optional: filter by status
        if ($request->filled('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        $perPage = $request->get('per_page', 10);
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

        $query = LegitimacyRequest::with([
            'user:id,name,email',   
            'signatories',          
        ]);

        // Filter by status
        if ($request->filled('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        // Search across main fields and signatories
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('alias', 'like', "%{$search}%")
                    ->orWhere('chapter', 'like', "%{$search}%")
                    ->orWhere('position', 'like', "%{$search}%")
                    ->orWhere('fraternity_number', 'like', "%{$search}%")
                    ->orWhereHas('signatories', function ($sq) use ($search) {
                        $sq->where('name', 'like', "%{$search}%");
                    });
            });
        }

        $perPage = $request->get('per_page', 10);
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
            'alias' => 'required|string|max:255',
            'chapter' => 'required|string|max:255',
            'position' => 'required|string|max:255',
            'fraternity_number' => 'required|string|exists:users,fraternity_number',
            'status' => 'sometimes|in:pending,approved,rejected',
            'admin_note' => 'nullable|string|max:500',
            'certificate_date' => 'required|date',
            'signatories' => 'nullable|array',
            'signatories.*.name' => 'required_with:signatories|string|max:255',
            'signatories.*.signed_date' => 'nullable|date',
            'signatories.*.signature_file' => 'nullable|image|mimes:png,jpg,jpeg|max:5120',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            // Find the user by fraternity number
            $user = \App\Models\User::where('fraternity_number', $request->fraternity_number)->first();
            if (! $user) {
                return response()->json([
                    'success' => false,
                    'message' => 'No user found with that fraternity number.',
                ], 404);
            }

            $legitimacy = LegitimacyRequest::create([
                'user_id' => $user->id,
                'alias' => $request->alias,
                'chapter' => $request->chapter,
                'position' => $request->position,
                'fraternity_number' => $request->fraternity_number,
                'status' => $request->status ?? 'pending',
                'admin_note' => $request->admin_note,
                'certificate_date' => $request->certificate_date,
                'approved_at' => $request->status === 'approved' ? now() : null,
            ]);

            // Handle signatories
            if ($request->filled('signatories')) {
                foreach ($request->signatories as $sig) {
                    $signatureUrl = null;
                    if (isset($sig['signature_file'])) {
                        $file = $sig['signature_file'];
                        $fileName = time().'_'.$file->getClientOriginalName();
                        $file->move(public_path('signatureUrl'), $fileName);
                        $signatureUrl = "/signatureUrl/$fileName";
                    }

                    $legitimacy->signatories()->create([
                        'name' => $sig['name'],
                        'role' => $sig['role'] ?? null,
                        'signed_date' => $sig['signed_date'] ?? null,
                        'signature_url' => $signatureUrl,
                    ]);
                }
            }

            return response()->json([
                'success' => true,
                'message' => 'Legitimacy request created successfully by admin.',
                'data' => $legitimacy->load('signatories'),
            ], 201);

        } catch (\Exception $e) {
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

        $validator = Validator::make($request->all(), [
            'alias' => 'sometimes|string|max:255',
            'chapter' => 'sometimes|string|max:255',
            'position' => 'sometimes|string|max:255',
            'fraternity_number' => 'sometimes|string|max:255',
            'status' => 'sometimes|in:pending,approved,rejected',
            'admin_note' => 'nullable|string|max:500',
            'certificate_date' => 'sometimes|date',
            'signatories' => 'nullable|array',
            'signatories.*.id' => 'sometimes|exists:signatories,id',
            'signatories.*.name' => 'required_with:signatories|string|max:255',
            'signatories.*.signed_date' => 'nullable|date',
            'signatories.*.signature_file' => 'nullable|image|mimes:png,jpg,jpeg|max:5120',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $fieldsToUpdate = $request->only([
            'alias', 'chapter', 'position', 'fraternity_number', 'status', 'admin_note', 'certificate_date',
        ]);

        $legitimacy->fill($fieldsToUpdate);

        if (isset($fieldsToUpdate['status']) && $fieldsToUpdate['status'] === 'approved') {
            $legitimacy->approved_at = now();
        } elseif (isset($fieldsToUpdate['status']) && $fieldsToUpdate['status'] !== 'approved') {
            $legitimacy->approved_at = null;
        }

        $legitimacy->save();

        // Update or create signatories
        if ($request->filled('signatories')) {
            foreach ($request->signatories as $sig) {
                $signatureUrl = null;
                if (isset($sig['signature_file'])) {
                    $file = $sig['signature_file'];
                    $fileName = time().'_'.$file->getClientOriginalName();
                    $file->move(public_path('signatureUrl'), $fileName);
                    $signatureUrl = "/signatureUrl/$fileName";
                }

                if (! empty($sig['id'])) {
                    $signatory = $legitimacy->signatories()->find($sig['id']);
                    if ($signatory) {
                        $signatory->update([
                            'name' => $sig['name'],
                            'role' => $sig['role'] ?? null,
                            'signed_date' => $sig['signed_date'] ?? null,
                            'signature_url' => $signatureUrl ?? $signatory->signature_url,
                        ]);
                    }
                } else {
                    $legitimacy->signatories()->create([
                        'name' => $sig['name'],
                        'role' => $sig['role'] ?? null,
                        'signed_date' => $sig['signed_date'] ?? null,
                        'signature_url' => $signatureUrl,
                    ]);
                }
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Legitimacy request updated successfully.',
            'data' => $legitimacy->load('signatories'),
        ]);
    }

    public function adminDestroy($id)
    {
        $admin = Auth::user();

        if (! $admin->isAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Only admins can delete legitimacy requests.',
            ], 403);
        }

        $legitimacy = LegitimacyRequest::find($id);

        if (! $legitimacy) {
            return response()->json([
                'success' => false,
                'message' => 'Legitimacy request not found.',
            ], 404);
        }

        try {
            // Delete related signatory files first
            foreach ($legitimacy->signatories as $signatory) {
                if ($signatory->signature_url) {
                    $filePath = public_path($signatory->signature_url);
                    if (file_exists($filePath)) {
                        @unlink($filePath); // suppress error if file doesn't exist
                    }
                }
            }

            // Delete related signatories
            $legitimacy->signatories()->delete();

            // Delete the legitimacy request
            $legitimacy->delete();

            return response()->json([
                'success' => true,
                'message' => 'Legitimacy request and its signatory files deleted successfully.',
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete legitimacy request.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
