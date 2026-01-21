<?php
// app/Http/Controllers/Admin/AdminGoodMoralCertificateController.php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\GoodMoralCertificate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;

class AdminGoodMoralCertificateController extends Controller
{
    /**
     * Display a listing of all certificates (Admin view)
     */
    public function index(Request $request)
    {
        try {
            $query = GoodMoralCertificate::with(['user', 'approvedBy'])
                ->latest();

            // Apply filters
            if ($request->has('status') && $request->status !== 'all') {
                $query->where('status', $request->status);
            }

            if ($request->has('barangay')) {
                $query->byBarangay($request->barangay);
            }

            if ($request->has('search')) {
                $query->search($request->search);
            }

            $certificates = $query->paginate($request->get('per_page', 15));

            return response()->json([
                'success' => true,
                'data' => $certificates,
            ]);
        } catch (\Exception $e) {
            Log::error('Admin Error fetching certificates: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch certificates',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Display the specified certificate (Admin view)
     */
    public function show($id)
    {
        try {
            $certificate = GoodMoralCertificate::with(['user', 'approvedBy'])
                ->findOrFail($id);

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
     * Update certificate status (approve/reject/release)
     */
    public function updateStatus(Request $request, $id)
    {
        try {
            $validator = Validator::make($request->all(), [
                'status' => 'required|in:approved,rejected,released',
                'remarks' => 'nullable|string',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $certificate = GoodMoralCertificate::findOrFail($id);
            $admin = $request->user();

            $certificate->status = $request->status;
            $certificate->remarks = $request->remarks;

            if ($request->status === 'approved') {
                $certificate->approved_at = now();
                $certificate->approved_by = $admin->id;
            } elseif ($request->status === 'released') {
                $certificate->released_at = now();
            }

            $certificate->save();

            return response()->json([
                'success' => true,
                'message' => "Certificate {$request->status} successfully",
                'data' => $certificate->load(['user', 'approvedBy']),
            ]);
        } catch (\Exception $e) {
            Log::error('Error updating certificate status: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to update certificate status',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get certificate statistics
     */
    public function statistics()
    {
        try {
            $stats = [
                'total' => GoodMoralCertificate::count(),
                'pending' => GoodMoralCertificate::pending()->count(),
                'approved' => GoodMoralCertificate::approved()->count(),
                'rejected' => GoodMoralCertificate::rejected()->count(),
                'released' => GoodMoralCertificate::released()->count(),
                'this_month' => GoodMoralCertificate::whereMonth('created_at', now()->month)->count(),
                'today' => GoodMoralCertificate::whereDate('created_at', today())->count(),
            ];

            return response()->json([
                'success' => true,
                'data' => $stats,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch statistics',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}