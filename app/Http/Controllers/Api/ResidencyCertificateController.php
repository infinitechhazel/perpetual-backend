<?php

namespace App\Http\Controllers\Api;

use App\Models\ResidencyCertificate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\Controller;

class ResidencyCertificateController extends Controller
{
    /**
     * Display a listing of the user's residency certificates
     */
    public function index(Request $request)
    {
        try {
            $user = $request->user();

            $certificates = ResidencyCertificate::where('user_id', $user->id)
                ->with(['user'])
                ->latest()
                ->paginate(10);

            return response()->json([
                'success' => true,
                'data' => $certificates,
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error fetching residency certificates: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch certificates',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Display a listing of all residency certificates for Admin.
     */
    public function adminIndex(Request $request)
    {
        try {
            $perPage = $request->input('per_page', 15);
            $status = $request->input('status');
            $search = $request->input('search');

            $query = ResidencyCertificate::with(['user'])
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
                'message' => 'Residency certificates retrieved successfully',
                'data' => $certificates
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error fetching residency certificates (admin): ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch residency certificates',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update the status of a residency certificate (Admin only).
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

            $certificate = ResidencyCertificate::findOrFail($id);
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
                'message' => 'Residency certificate status updated successfully',
                'data' => $certificate->load(['user'])
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error updating residency certificate status: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to update status',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Store a newly created residency certificate
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

            // Ensure directories exist
            $validIdDir = public_path('uploads/residency_certificates/valid_ids');
            if (!File::exists($validIdDir)) {
                File::makeDirectory($validIdDir, 0755, true);
            }

            // Move valid ID to public path
            $validIdFile = $request->file('validId');
            $validIdName = time() . '_' . uniqid() . '.' . $validIdFile->getClientOriginalExtension();
            $validIdFile->move($validIdDir, $validIdName);
            $validIdPath = 'uploads/residency_certificates/valid_ids/' . $validIdName;

            // Move proof of residency if provided
            $proofOfResidencyPath = null;
            if ($request->hasFile('proofOfResidency')) {
                $proofOfResidencyDir = public_path('uploads/residency_certificates/proof_of_residency');
                if (!File::exists($proofOfResidencyDir)) {
                    File::makeDirectory($proofOfResidencyDir, 0755, true);
                }

                $proofOfResidencyFile = $request->file('proofOfResidency');
                $proofOfResidencyName = time() . '_' . uniqid() . '.' . $proofOfResidencyFile->getClientOriginalExtension();
                $proofOfResidencyFile->move($proofOfResidencyDir, $proofOfResidencyName);
                $proofOfResidencyPath = 'uploads/residency_certificates/proof_of_residency/' . $proofOfResidencyName;
            }

            // Create certificate
            $certificate = ResidencyCertificate::create([
                'user_id' => $request->user()->id,
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
                'reference_number' => 'RES-' . strtoupper(uniqid()),
            ]);

            Log::info('Residency certificate created', [
                'certificate_id' => $certificate->id,
                'user_id' => $request->user()->id,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Residency certificate application submitted successfully',
                'data' => $certificate,
            ], 201);
        } catch (\Exception $e) {
            Log::error('Error creating residency certificate: ' . $e->getMessage());
            Log::error('Stack trace: ' . $e->getTraceAsString());

            return response()->json([
                'success' => false,
                'message' => 'Failed to submit application',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Display the specified residency certificate
     */
    public function show(Request $request, $id)
    {
        try {
            $user = $request->user();
            
            if ($user->role === 'admin') { 
                 $certificate = ResidencyCertificate::with(['user'])->findOrFail($id);
            } else {
                 $certificate = ResidencyCertificate::where('id', $id)
                    ->where('user_id', $user->id)
                    ->with(['user'])
                    ->firstOrFail();
            }

            return response()->json([
                'success' => true,
                'data' => $certificate,
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error fetching residency certificate: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Certificate not found',
                'error' => $e->getMessage(),
            ], 404);
        }
    }

    /**
     * Remove the specified residency certificate
     */
    public function destroy(Request $request, $id)
    {
        try {
            $user = $request->user();
            
            $query = ResidencyCertificate::where('id', $id);
            
            if ($user->role !== 'admin') {
                $query->where('user_id', $user->id);
            }
            
            $certificate = $query->firstOrFail();

            // Delete files from public path
            if ($certificate->valid_id_path) {
                $validIdFullPath = public_path($certificate->valid_id_path);
                if (file_exists($validIdFullPath)) {
                    unlink($validIdFullPath);
                }
            }
            if ($certificate->proof_of_residency_path) {
                $proofFullPath = public_path($certificate->proof_of_residency_path);
                if (file_exists($proofFullPath)) {
                    unlink($proofFullPath);
                }
            }

            $certificate->delete();

            Log::info('Residency certificate deleted', [
                'certificate_id' => $id,
                'user_id' => $user->id,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Certificate deleted successfully',
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error deleting residency certificate: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to delete certificate',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
