<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\IndigencyCertificate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class IndigencyCertificateController extends Controller
{
    /**
     * Display a listing of indigency certificates (Admin only).
     */
    public function index(Request $request)
    {
        try {
            $perPage = $request->input('per_page', 15);
            $status = $request->input('status');
            $search = $request->input('search');

            $query = IndigencyCertificate::with(['user'])
                ->orderBy('created_at', 'desc');

            // Filter by status
            if ($status && $status !== 'all') {
                $query->where('status', $status);
            }

            // Search filter
            if ($search) {
                $query->where(function($q) use ($search) {
                    $q->where('full_name', 'like', "%{$search}%")
                      ->orWhere('reference_number', 'like', "%{$search}%");
                });
            }

            $certificates = $query->paginate($perPage);

            return response()->json([
                'success' => true,
                'message' => 'Indigency certificates retrieved successfully',
                'data' => $certificates
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error fetching indigency certificates: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch indigency certificates',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Store a newly created indigency certificate.
     */
    public function store(Request $request)
    {
        try {
            // Validate request
            $validator = Validator::make($request->all(), [
                'fullName' => 'required|string|max:255',
                'email' => 'required|email|max:255',
                'phone' => 'required|string|max:20',
                'address' => 'required|string',
                'birthDate' => 'required|date|before:today',
                'age' => 'required|integer|min:18',
                'sex' => 'required|in:male,female',
                'civilStatus' => 'required|in:single,married,widowed,divorced,separated',
                'barangay' => 'required|string|max:255',
                'yearsOfResidency' => 'required|integer|min:0',
                'monthlyIncome' => 'nullable|string|max:255',
                'numberOfDependents' => 'nullable|integer|min:0',
                'purpose' => 'required|string',
                'reasonForIndigency' => 'nullable|string',
                'validId' => 'required|file|mimes:pdf,jpg,jpeg,png|max:10240', // 10MB
                'supportingDocument' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:10240',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation error',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Get authenticated user
            $user = Auth::user();

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not authenticated'
                ], 401);
            }

            // Handle file uploads - save directly to public directory
            $validIdPath = null;
            $supportingDocPath = null;

            if ($request->hasFile('validId')) {
                $file = $request->file('validId');
                $fileName = time() . '_' . uniqid() . '_validid.' . $file->getClientOriginalExtension();
                $file->move(public_path('uploads/indigency-certificates/valid-ids'), $fileName);
                $validIdPath = 'uploads/indigency-certificates/valid-ids/' . $fileName;
            }

            if ($request->hasFile('supportingDocument')) {
                $file = $request->file('supportingDocument');
                $fileName = time() . '_' . uniqid() . '_support.' . $file->getClientOriginalExtension();
                $file->move(public_path('uploads/indigency-certificates/supporting-docs'), $fileName);
                $supportingDocPath = 'uploads/indigency-certificates/supporting-docs/' . $fileName;
            }

            // Create indigency certificate
            $certificate = IndigencyCertificate::create([
                'user_id' => $user->id,
                'reference_number' => IndigencyCertificate::generateReferenceNumber(), // Assuming this method exists in model
                'full_name' => $request->fullName,
                'email' => $request->email,
                'phone' => $request->phone,
                'address' => $request->address,
                'birth_date' => $request->birthDate,
                'age' => $request->age,
                'sex' => $request->sex,
                'civil_status' => $request->civilStatus,
                'barangay' => $request->barangay,
                'years_of_residency' => $request->yearsOfResidency,
                'monthly_income' => $request->monthlyIncome,
                'number_of_dependents' => $request->numberOfDependents ?? 0,
                'purpose' => $request->purpose,
                'reason_for_indigency' => $request->reasonForIndigency,
                'valid_id_path' => $validIdPath,
                'supporting_document_path' => $supportingDocPath,
                'status' => 'pending',
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Indigency certificate application submitted successfully',
                'data' => $certificate
            ], 201);
        } catch (\Exception $e) {
            Log::error('Error creating indigency certificate: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to submit application',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified indigency certificate.
     */
    public function show($id)
    {
        try {
            $certificate = IndigencyCertificate::with(['user'])->findOrFail($id);

            // Check authorization
            $user = Auth::user();
            if ($user->role !== 'admin' && $certificate->user_id !== $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access'
                ], 403);
            }

            return response()->json([
                'success' => true,
                'message' => 'Indigency certificate retrieved successfully',
                'data' => $certificate
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error fetching indigency certificate: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Indigency certificate not found',
                'error' => $e->getMessage()
            ], 404);
        }
    }

    /**
     * Update the status of an indigency certificate (Admin only).
     */
    public function updateStatus(Request $request, $id)
    {
        try {
            $validator = Validator::make($request->all(), [
                'status' => 'required|in:pending,approved,rejected,released',
                'admin_remarks' => 'nullable|string',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation error',
                    'errors' => $validator->errors()
                ], 422);
            }

            $certificate = IndigencyCertificate::findOrFail($id);
            $user = Auth::user();

            $certificate->status = $request->status;
            if ($request->has('admin_remarks')) {
                $certificate->admin_remarks = $request->admin_remarks;
            }
            $certificate->processed_by = $user->id;

            // Set appropriate timestamp
            switch ($request->status) {
                case 'approved':
                    $certificate->approved_at = now();
                    break;
                case 'rejected':
                    $certificate->rejected_at = now();
                    break;
                case 'released':
                    $certificate->released_at = now();
                    break;
            }

            $certificate->save();

            return response()->json([
                'success' => true,
                'message' => 'Indigency certificate status updated successfully',
                'data' => $certificate->load(['user'])
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error updating indigency certificate status: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to update status',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified indigency certificate (Admin only).
     */
    public function destroy($id)
    {
        try {
            $certificate = IndigencyCertificate::findOrFail($id);

            // Delete associated files from public directory
            if ($certificate->valid_id_path) {
                $filePath = public_path($certificate->valid_id_path);
                if (file_exists($filePath)) {
                    unlink($filePath);
                }
            }
            if ($certificate->supporting_document_path) {
                $filePath = public_path($certificate->supporting_document_path);
                if (file_exists($filePath)) {
                    unlink($filePath);
                }
            }

            $certificate->delete();

            return response()->json([
                'success' => true,
                'message' => 'Indigency certificate deleted successfully'
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error deleting indigency certificate: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete indigency certificate',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get user's own indigency certificates.
     */
    public function myApplications(Request $request)
    {
        try {
            $user = Auth::user();
            $perPage = $request->input('per_page', 15);

            $certificates = IndigencyCertificate::where('user_id', $user->id)
                ->orderBy('created_at', 'desc')
                ->paginate($perPage);

            return response()->json([
                'success' => true,
                'message' => 'Your indigency certificates retrieved successfully',
                'data' => $certificates
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error fetching user indigency certificates: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch your applications',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
