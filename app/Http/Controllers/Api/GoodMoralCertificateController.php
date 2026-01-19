<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\GoodMoralCertificate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Auth;

class GoodMoralCertificateController extends Controller
{
    /**
     * Display a listing of certificates (for citizens - their own)
     */
    public function index(Request $request)
    {
        try {
            $user = $request->user();
            
            $query = GoodMoralCertificate::where('user_id', $user->id)
                ->with(['user', 'approvedBy'])
                ->latest();

            // Apply filters
            if ($request->has('status') && $request->status !== 'all') {
                $query->where('status', $request->status);
            }

            if ($request->has('search')) {
                 $query->where(function($q) use ($request) {
                    $q->where('full_name', 'like', "%{$request->search}%")
                      ->orWhere('reference_number', 'like', "%{$request->search}%");
                });
            }

            $certificates = $query->paginate($request->get('per_page', 15));

            return response()->json([
                'success' => true,
                'data' => $certificates,
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching certificates: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch certificates',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Display a listing of all certificates for Admin.
     */
    public function adminIndex(Request $request)
    {
        try {
            $perPage = $request->input('per_page', 15);
            $status = $request->input('status');
            $search = $request->input('search');

            $query = GoodMoralCertificate::with(['user'])
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
                'message' => 'Good Moral certificates retrieved successfully',
                'data' => $certificates
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error fetching good moral certificates (admin): ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch good moral certificates',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update the status of a good moral certificate (Admin only).
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

            $certificate = GoodMoralCertificate::findOrFail($id);
            $user = Auth::user();

            $certificate->status = $request->status;
            if ($request->has('admin_remarks')) {
                $certificate->admin_remarks = $request->admin_remarks;
            }
            $certificate->approved_by = $user->id;

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
                'message' => 'Certificate status updated successfully',
                'data' => $certificate->load(['user'])
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error updating certificate status: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to update status',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Store a newly created certificate
     */
    public function store(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'fullName' => 'required|string|max:255',
                'email' => 'required|email|max:255',
                'phone' => 'required|string|max:20',
                'address' => 'required|string',
                'birthDate' => 'required|date|before:today',
                'age' => 'required|integer|min:18|max:150',
                'sex' => 'required|in:male,female',
                'civilStatus' => 'required|in:single,married,widowed,divorced,separated',
                'barangay' => 'required|string|max:255',
                'yearsOfResidency' => 'required|integer|min:0',
                'occupation' => 'nullable|string|max:255',
                'purpose' => 'required|string',
                'validId' => 'required|file|mimes:pdf,jpg,jpeg,png|max:10240',
                'proofOfResidency' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:10240',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors(),
                ], 422);
            }

            DB::beginTransaction();

            $user = $request->user();

            // Handle file uploads to public path
            $validIdPath = $this->uploadFileToPublic($request->file('validId'), 'good-moral-certificates/valid-ids');
            
            $proofOfResidencyPath = null;
            if ($request->hasFile('proofOfResidency')) {
                $proofOfResidencyPath = $this->uploadFileToPublic($request->file('proofOfResidency'), 'good-moral-certificates/proof-of-residency');
            }

            // Create certificate
            $certificate = GoodMoralCertificate::create([
                'user_id' => $user->id,
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
                'occupation' => $request->occupation,
                'purpose' => $request->purpose,
                'valid_id_path' => $validIdPath,
                'proof_of_residency_path' => $proofOfResidencyPath,
                'status' => 'pending',
                'reference_number' => 'GMC-' . strtoupper(uniqid()),
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Certificate application submitted successfully',
                'data' => [
                    'reference_number' => $certificate->reference_number,
                    'certificate' => $certificate->load(['user', 'approvedBy']),
                ],
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Error creating certificate: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to submit application',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Display the specified certificate
     */
    public function show(Request $request, $id)
    {
        try {
            $user = $request->user();
            
            $certificate = GoodMoralCertificate::with(['user', 'approvedBy'])
                ->where('id', $id);
                
            if ($user->role !== 'admin') {
                $certificate->where('user_id', $user->id);
            }
            
            $certificate = $certificate->firstOrFail();

            return response()->json([
                'success' => true,
                'data' => $certificate,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Certificate not found',
            ], 404);
        }
    }

    /**
     * Remove the specified certificate
     */
    public function destroy(Request $request, $id)
    {
        try {
            $user = $request->user();
            
            $query = GoodMoralCertificate::where('id', $id);
            
            if ($user->role !== 'admin') {
                $query->where('user_id', $user->id)
                      ->where('status', 'pending');
            }
                
            $certificate = $query->firstOrFail();

            // Delete uploaded files from public directory
            if ($certificate->valid_id_path) {
                $this->deleteFileFromPublic($certificate->valid_id_path);
            }
            if ($certificate->proof_of_residency_path) {
                $this->deleteFileFromPublic($certificate->proof_of_residency_path);
            }

            $certificate->delete();

            return response()->json([
                'success' => true,
                'message' => 'Certificate application deleted successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete certificate',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Upload file to public directory
     */
    private function uploadFileToPublic($file, $folder)
    {
        // Create directory if it doesn't exist
        $uploadPath = public_path($folder);
        if (!file_exists($uploadPath)) {
            mkdir($uploadPath, 0755, true);
        }

        // Generate unique filename
        $filename = time() . '_' . Str::random(10) . '.' . $file->getClientOriginalExtension();
        
        // Move file to public directory
        $file->move($uploadPath, $filename);

        // Return the relative path
        return $folder . '/' . $filename;
    }

    /**
     * Delete file from public directory
     */
    private function deleteFileFromPublic($path)
    {
        $fullPath = public_path($path);
        if (file_exists($fullPath)) {
            unlink($fullPath);
        }
    }
}
