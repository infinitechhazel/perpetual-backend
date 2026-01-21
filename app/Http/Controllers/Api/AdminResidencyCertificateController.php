<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ResidencyCertificate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;

class AdminResidencyCertificateController extends Controller
{
    /**
     * Display a listing of all residency certificates (Admin)
     */
    public function index(Request $request)
    {
        try {
            $perPage = $request->input('per_page', 15);
            $status = $request->input('status');
            $search = $request->input('search');

            $query = ResidencyCertificate::with(['user', 'processor'])
                ->status($status)
                ->search($search)
                ->recent();

            $certificates = $query->paginate($perPage);

            return response()->json([
                'success' => true,
                'data' => $certificates,
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error fetching residency certificates for admin: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch certificates',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Display the specified residency certificate (Admin)
     */
    public function show($id)
    {
        try {
            $certificate = ResidencyCertificate::with(['user', 'processor'])
                ->findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => $certificate,
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error fetching residency certificate details: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Certificate not found',
                'error' => $e->getMessage(),
            ], 404);
        }
    }

    /**
     * Update the status of a residency certificate
     */
    public function updateStatus(Request $request, $id)
    {
        try {
            $validator = Validator::make($request->all(), [
                'status' => 'required|in:pending,approved,rejected',
                'rejection_reason' => 'required_if:status,rejected|nullable|string',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $certificate = ResidencyCertificate::findOrFail($id);

            // Update status
            $certificate->status = $request->status;
            $certificate->processed_by = $request->user()->id;
            $certificate->processed_at = now();

            if ($request->status === 'rejected') {
                $certificate->rejection_reason = $request->rejection_reason;
            } else {
                $certificate->rejection_reason = null;
            }

            $certificate->save();

            Log::info('Residency certificate status updated', [
                'certificate_id' => $id,
                'status' => $request->status,
                'processed_by' => $request->user()->id,
            ]);

            // Load relationships
            $certificate->load(['user', 'processor']);

            return response()->json([
                'success' => true,
                'message' => 'Certificate status updated successfully',
                'data' => $certificate,
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error updating residency certificate status: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to update certificate status',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get statistics for residency certificates
     */
    public function statistics()
    {
        try {
            $stats = [
                'total' => ResidencyCertificate::count(),
                'pending' => ResidencyCertificate::where('status', 'pending')->count(),
                'approved' => ResidencyCertificate::where('status', 'approved')->count(),
                'rejected' => ResidencyCertificate::where('status', 'rejected')->count(),
                'recent' => ResidencyCertificate::where('created_at', '>=', now()->subDays(30))->count(),
            ];

            return response()->json([
                'success' => true,
                'data' => $stats,
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error fetching residency certificate statistics: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch statistics',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Delete a residency certificate (Admin)
     */
    public function destroy($id)
    {
        try {
            $certificate = ResidencyCertificate::findOrFail($id);

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

            Log::info('Residency certificate deleted by admin', [
                'certificate_id' => $id,
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
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}