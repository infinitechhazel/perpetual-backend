<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BarangayClearance;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class BarangayClearanceController extends Controller
{
    /**
     * Display a listing of the user's clearance applications
     */
    public function index(Request $request)
    {
        $clearances = BarangayClearance::where('user_id', $request->user()->id)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $clearances,
        ]);
    }

    /**
     * Admin view - Get all barangay clearances with pagination and filters
     */
    public function adminIndex(Request $request)
    {
        $query = BarangayClearance::with('user');

        // Filter by status
        if ($request->has('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        // Search
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('reference_number', 'like', "%{$search}%")
                  ->orWhere('full_name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%")
                  ->orWhere('barangay', 'like', "%{$search}%")
                  ->orWhere('purpose', 'like', "%{$search}%");
            });
        }

        $perPage = $request->get('per_page', 15);
        $clearances = $query->latest()->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $clearances
        ]);
    }

    /**
     * Store a newly created clearance application
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'fullName' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'phone' => 'required|string|max:20',
            'address' => 'required|string',
            'birthDate' => 'required|date',
            'age' => 'required|integer|min:1|max:150',
            'sex' => 'required|in:male,female',
            'civilStatus' => 'required|in:single,married,widowed,divorced,separated',
            'yearsOfResidency' => 'required|integer|min:0',
            'barangay' => 'required|string|max:255',
            'purpose' => 'required|string',
            'validId' => 'required|file|mimes:pdf,jpg,jpeg,png|max:10240', // 10MB max
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            // Store the valid ID file
            $validIdPath = $request->file('validId')->store('barangay-clearances/valid-ids', 'public');

            // Generate reference number
            $referenceNumber = BarangayClearance::generateReferenceNumber();

            // Create clearance application
            $clearance = BarangayClearance::create([
                'user_id' => $request->user()->id,
                'reference_number' => $referenceNumber,
                'full_name' => $request->fullName,
                'email' => $request->email,
                'phone' => $request->phone,
                'address' => $request->address,
                'birth_date' => $request->birthDate,
                'age' => $request->age,
                'sex' => $request->sex,
                'civil_status' => $request->civilStatus,
                'years_of_residency' => $request->yearsOfResidency,
                'barangay' => $request->barangay,
                'purpose' => $request->purpose,
                'valid_id_path' => $validIdPath,
                'status' => 'pending',
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Barangay clearance application submitted successfully',
                'data' => $clearance,
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to submit application',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Display the specified clearance application
     */
    public function show(Request $request, $id)
    {
        $clearance = BarangayClearance::where('user_id', $request->user()->id)
            ->where('id', $id)
            ->first();

        if (!$clearance) {
            return response()->json([
                'success' => false,
                'message' => 'Clearance application not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $clearance,
        ]);
    }

    /**
     * Update the specified clearance application status (admin only)
     */
    public function updateStatus(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'status' => 'required|in:pending,approved,rejected,processing',
            'rejection_reason' => 'required_if:status,rejected|string|nullable',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $clearance = BarangayClearance::find($id);

        if (!$clearance) {
            return response()->json([
                'success' => false,
                'message' => 'Clearance application not found',
            ], 404);
        }

        try {
            $updateData = [
                'status' => $request->status,
            ];

            // If approved, generate clearance number and set dates
            if ($request->status === 'approved') {
                $updateData['clearance_number'] = 'BC-' . strtoupper(uniqid());
                $updateData['approved_at'] = now();
                $updateData['expires_at'] = now()->addMonths(6); // Valid for 6 months
                $updateData['rejection_reason'] = null;
            }

            // If rejected, save rejection reason
            if ($request->status === 'rejected') {
                $updateData['rejection_reason'] = $request->rejection_reason;
                $updateData['clearance_number'] = null;
                $updateData['approved_at'] = null;
                $updateData['expires_at'] = null;
            }

            // If set back to pending or processing, clear rejection reason
            if (in_array($request->status, ['pending', 'processing'])) {
                $updateData['rejection_reason'] = null;
            }

            $clearance->update($updateData);

            return response()->json([
                'success' => true,
                'message' => 'Barangay clearance status updated successfully',
                'data' => $clearance->fresh()
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update status: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified clearance application
     */
    public function destroy(Request $request, $id)
    {
        $clearance = BarangayClearance::where('user_id', $request->user()->id)
            ->where('id', $id)
            ->first();

        if (!$clearance) {
            return response()->json([
                'success' => false,
                'message' => 'Clearance application not found',
            ], 404);
        }

        // Delete the valid ID file
        if (Storage::disk('public')->exists($clearance->valid_id_path)) {
            Storage::disk('public')->delete($clearance->valid_id_path);
        }

        $clearance->delete();

        return response()->json([
            'success' => true,
            'message' => 'Clearance application deleted successfully',
        ]);
    }
}