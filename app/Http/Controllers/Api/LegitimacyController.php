<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LegitimacyRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Barryvdh\DomPDF\Facade\Pdf;

class LegitimacyController extends Controller
{
    /**
     * List legitimacy requests for the authenticated user
     */
    public function userIndex(Request $request)
    {
        $user = Auth::user();

        if (!$user->isMember()) {
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
    public function userStore(Request $request)
    {
        $user = Auth::user();

        if (!$user->isMember()) {
            return response()->json([
                'success' => false,
                'message' => 'Only users can submit legitimacy requests.',
            ], 403);
        }

        // Prevent multiple pending requests
        $existing = LegitimacyRequest::where('user_id', $user->id)
            ->where('status', 'pending')
            ->first();

        if ($existing) {
            return response()->json([
                'success' => false,
                'message' => 'You already have a pending legitimacy request.',
            ], 400);
        }

        $validator = Validator::make($request->all(), [
            'alias' => 'required|string|max:255',
            'chapter' => 'required|string|max:255',
            'position' => 'required|string|max:255',
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
     * Update own legitimacy request
     */
    public function userUpdate(Request $request, $id)
    {
        $user = Auth::user();

        if (!$user->isMember()) {
            return response()->json([
                'success' => false,
                'message' => 'Only users can update legitimacy requests.',
            ], 403);
        }

        $legitimacy = LegitimacyRequest::where('id', $id)
            ->where('user_id', $user->id)
            ->first();

        if (!$legitimacy) {
            return response()->json([
                'success' => false,
                'message' => 'Legitimacy request not found or unauthorized.',
            ], 404);
        }

        // only pending can be edited
        if ($legitimacy->status !== 'pending') {
            return response()->json([
                'success' => false,
                'message' => 'Only pending legitimacy requests can be edited.',
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'alias' => 'sometimes|string|max:255',
            'chapter' => 'sometimes|string|max:255',
            'position' => 'sometimes|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $legitimacy->update($request->only(['alias', 'chapter', 'position']));

        return response()->json([
            'success' => true,
            'message' => 'Legitimacy request updated successfully.',
            'data' => $legitimacy,
        ]);
    }



    /**
     * Admin: list all legitimacy requests (with search/filter)
     */
    public function adminIndex(Request $request)
    {
        $admin = Auth::user();

        if (!$admin->isAdmin()) {
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

        if (!$admin->isAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Only admins can create legitimacy requests.',
            ], 403);
        }

        // Log incoming request for debugging
        Log::info('Admin legitimacy create request', [
            'data' => $request->all(),
            'files' => $request->allFiles(),
        ]);

        $validator = Validator::make($request->all(), [
            'alias' => 'required|string|max:255',
            'chapter' => 'required|string|max:255',
            'position' => 'required|string|max:255',
            'fraternity_number' => 'required|string',
            'status' => 'sometimes|in:pending,approved,rejected',
            'admin_note' => 'nullable|string|max:500',
            'certificate_date' => 'required|date',
            'signatories' => 'nullable|array',
            'signatories.*.name' => 'required_with:signatories|string|max:255',
            'signatories.*.role' => 'nullable|string|max:255',
            'signatories.*.signed_date' => 'nullable|date',
            'signatories.*.signature_file' => 'nullable|image|mimes:png,jpg,jpeg|max:5120',
        ]);

        if ($validator->fails()) {
            Log::error('Validation failed', ['errors' => $validator->errors()]);
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            // Find the user by fraternity number
            $user = \App\Models\User::where('fraternity_number', (int) $request->fraternity_number)->first();
            if (!$user) {
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
            if ($request->has('signatories')) {
                $signatories = $request->input('signatories', []);

                foreach ($signatories as $index => $sig) {
                    $signatureUrl = null;

                    // Check if signature file exists
                    if ($request->hasFile("signatories.{$index}.signature_file")) {
                        $file = $request->file("signatories.{$index}.signature_file");
                        $fileName = time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension();
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
            Log::error('Failed to create legitimacy request', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
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

        if (!$admin->isAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Only admins can update legitimacy requests.',
            ], 403);
        }

        $legitimacy = LegitimacyRequest::find($id);
        if (!$legitimacy) {
            return response()->json([
                'success' => false,
                'message' => 'Legitimacy request not found.',
            ], 404);
        }

        Log::info('Admin legitimacy update request', [
            'id' => $id,
            'data' => $request->all(),
            'files' => $request->allFiles(),
        ]);

        $validator = Validator::make($request->all(), [
            'alias' => 'sometimes|string|max:255',
            'chapter' => 'sometimes|string|max:255',
            'position' => 'sometimes|string|max:255',
            'fraternity_number' => 'sometimes|string|exists:users,fraternity_number',
            'status' => 'sometimes|in:pending,approved,rejected',
            'admin_note' => 'nullable|string|max:500',
            'certificate_date' => 'sometimes|date',
            'signatories' => 'nullable|array',
            'signatories.*.id' => 'sometimes|exists:signatories,id',
            'signatories.*.name' => 'required_with:signatories|string|max:255',
            'signatories.*.role' => 'nullable|string|max:255',
            'signatories.*.signed_date' => 'nullable|date',
            'signatories.*.signature_file' => 'nullable|image|mimes:png,jpg,jpeg|max:5120',
            'deleted_signatories' => 'nullable|array',
            'deleted_signatories.*' => 'exists:signatories,id',
        ]);

        if ($validator->fails()) {
            Log::error('Validation failed', ['errors' => $validator->errors()]);
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $fieldsToUpdate = $request->only([
            'alias',
            'chapter',
            'position',
            'fraternity_number',
            'status',
            'admin_note',
            'certificate_date',
        ]);

        $legitimacy->fill($fieldsToUpdate);

        if (isset($fieldsToUpdate['status']) && $fieldsToUpdate['status'] === 'approved') {
            $legitimacy->approved_at = now();
        } elseif (isset($fieldsToUpdate['status']) && $fieldsToUpdate['status'] !== 'approved') {
            $legitimacy->approved_at = null;
        }

        $legitimacy->save();

        // Handle deleted signatories
        if ($request->has('deleted_signatories')) {
            $deletedIds = $request->input('deleted_signatories', []);
            foreach ($deletedIds as $deletedId) {
                $signatory = $legitimacy->signatories()->find($deletedId);
                if ($signatory) {
                    // Delete the signature file if it exists
                    if ($signatory->signature_url) {
                        $filePath = public_path($signatory->signature_url);
                        if (file_exists($filePath)) {
                            @unlink($filePath);
                        }
                    }
                    $signatory->delete();
                }
            }
        }

        // Update or create signatories
        if ($request->has('signatories')) {
            $signatories = $request->input('signatories', []);
            $submittedIds = [];

            foreach ($signatories as $index => $sig) {
                $signatureUrl = null;

                // Check if signature file exists
                if ($request->hasFile("signatories.{$index}.signature_file")) {
                    $file = $request->file("signatories.{$index}.signature_file");
                    $fileName = time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension();
                    $file->move(public_path('signatureUrl'), $fileName);
                    $signatureUrl = "/signatureUrl/$fileName";
                }

                if (!empty($sig['id'])) {
                    // Update existing signatory
                    $signatory = $legitimacy->signatories()->find($sig['id']);
                    if ($signatory) {
                        // If new file uploaded, delete old one
                        if ($signatureUrl && $signatory->signature_url) {
                            $oldFilePath = public_path($signatory->signature_url);
                            if (file_exists($oldFilePath)) {
                                @unlink($oldFilePath);
                            }
                        }

                        $signatory->update([
                            'name' => $sig['name'],
                            'role' => $sig['role'] ?? null,
                            'signed_date' => $sig['signed_date'] ?? null,
                            'signature_url' => $signatureUrl ?? $signatory->signature_url,
                        ]);
                        $submittedIds[] = $signatory->id;
                    }
                } else {
                    // Create new signatory
                    $newSignatory = $legitimacy->signatories()->create([
                        'name' => $sig['name'],
                        'role' => $sig['role'] ?? null,
                        'signed_date' => $sig['signed_date'] ?? null,
                        'signature_url' => $signatureUrl,
                    ]);
                    $submittedIds[] = $newSignatory->id;
                }
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Legitimacy request updated successfully.',
            'data' => $legitimacy->load('signatories'),
        ], 200);
    }

    public function adminDestroy($id)
    {
        $admin = Auth::user();

        if (!$admin->isAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Only admins can delete legitimacy requests.',
            ], 403);
        }

        $legitimacy = LegitimacyRequest::find($id);

        if (!$legitimacy) {
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


    /**
     * Generate PDF certificate for a specific legitimacy request
     */
    public function generatePDF($id)
    {
        try {
            $admin = Auth::user();

            if (!$admin || !$admin->isAdmin()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Only admins can generate certificates.',
                ], 403);
            }

            $legitimacy = LegitimacyRequest::with(['user', 'signatories'])->find($id);

            if (!$legitimacy) {
                return response()->json([
                    'success' => false,
                    'message' => 'Legitimacy request not found.',
                ], 404);
            }

            // Prepare data
            $data = [
                'legitimacy' => $legitimacy,
                'user' => $legitimacy->user,
                'signatories' => $legitimacy->signatories,
                'generatedDate' => now()->format('F d, Y'),
                'certificateDate' => $legitimacy->certificate_date
                    ? \Carbon\Carbon::parse($legitimacy->certificate_date)->format('F d, Y')
                    : now()->format('F d, Y'),
                'statusClass' => 'status-' . $legitimacy->status,
                'logoPath' => public_path('images/logo.png'),
                'logoExists' => file_exists(public_path('images/logo.png')),
            ];

            $pdf = Pdf::loadView('pdf.legitimacy-certificate', $data)
                ->setPaper('a4', 'landscape')
                ->setOption('margin-top', 15)
                ->setOption('margin-bottom', 15)
                ->setOption('margin-left', 15)
                ->setOption('margin-right', 15);

            $filename = 'certificate-' . str_replace(' ', '-', strtolower($legitimacy->alias)) . '-' . now()->format('Y-m-d') . '.pdf';

            return $pdf->download($filename);

        } catch (\Exception $e) {
            Log::error('Error generating legitimacy certificate PDF', [
                'legitimacy_id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to generate certificate',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}