<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Cedula;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class CedulaController extends Controller
{
    /**
     * Display a listing of cedula applications (User's own)
     */
    public function index(Request $request)
    {
        try {
            $user = $request->user();
            
            $cedulas = Cedula::where('user_id', $user->id)
                ->orderBy('created_at', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $cedulas
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch cedula applications',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Admin view - Get all cedula applications with pagination and filters
     */
    public function adminIndex(Request $request)
    {
        $query = Cedula::with('user');

        // Filter by status
        if ($request->has('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        // Search
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('cedula_number', 'like', "%{$search}%")
                  ->orWhere('full_name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%")
                  ->orWhere('occupation', 'like', "%{$search}%")
                  ->orWhere('citizenship', 'like', "%{$search}%");
            });
        }

        $perPage = $request->get('per_page', 15);
        $cedulas = $query->latest()->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $cedulas
        ]);
    }

    /**
     * Store a newly created cedula application
     */
    public function store(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'full_name' => 'required|string|max:255',
                'email' => 'required|email|max:255',
                'phone' => 'required|string|max:20',
                'address' => 'required|string|max:500',
                'birth_date' => 'required|date',
                'civil_status' => 'required|string|in:single,married,widowed,separated',
                'citizenship' => 'required|string|max:100',
                'occupation' => 'required|string|max:255',
                'tin_number' => 'nullable|string|max:50',
                'height' => 'required|numeric|min:0|max:999.99',
                'height_unit' => 'required|string|in:cm,in,ft',
                'weight' => 'required|numeric|min:0|max:999.99',
                'weight_unit' => 'required|string|in:kg,lbs',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $user = $request->user();

            $cedula = Cedula::create([
                'user_id' => $user->id,
                'full_name' => $request->full_name,
                'email' => $request->email,
                'phone' => $request->phone,
                'address' => $request->address,
                'birth_date' => $request->birth_date,
                'civil_status' => $request->civil_status,
                'citizenship' => $request->citizenship,
                'occupation' => $request->occupation,
                'tin_number' => $request->tin_number,
                'height' => $request->height,
                'height_unit' => $request->height_unit,
                'weight' => $request->weight,
                'weight_unit' => $request->weight_unit,
                'status' => 'pending',
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Cedula application submitted successfully',
                'data' => $cedula
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to submit cedula application',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified cedula application
     */
    public function show(Request $request, $id)
    {
        try {
            $user = $request->user();
            
            $cedula = Cedula::where('id', $id)
                ->where('user_id', $user->id)
                ->first();

            if (!$cedula) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cedula application not found'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $cedula
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch cedula application',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update cedula status (Admin only)
     */
    public function updateStatus(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'status' => 'required|string|in:pending,processing,approved,rejected',
            'rejection_reason' => 'required_if:status,rejected|string|nullable',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $cedula = Cedula::find($id);

        if (!$cedula) {
            return response()->json([
                'success' => false,
                'message' => 'Cedula application not found'
            ], 404);
        }

        try {
            $updateData = [
                'status' => $request->status,
            ];

            // If approved, generate cedula number and set dates
            if ($request->status === 'approved') {
                $updateData['cedula_number'] = 'CED-' . strtoupper(uniqid());
                $updateData['approved_at'] = now();
                $updateData['expires_at'] = now()->addYear(); // Valid for 1 year
                $updateData['rejection_reason'] = null;
            }

            // If rejected, save rejection reason
            if ($request->status === 'rejected') {
                $updateData['rejection_reason'] = $request->rejection_reason;
                $updateData['cedula_number'] = null;
                $updateData['approved_at'] = null;
                $updateData['expires_at'] = null;
            }

            // If set back to pending or processing, clear rejection reason
            if (in_array($request->status, ['pending', 'processing'])) {
                $updateData['rejection_reason'] = null;
            }

            $cedula->update($updateData);

            return response()->json([
                'success' => true,
                'message' => 'Cedula status updated successfully',
                'data' => $cedula->fresh()
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update status: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update the specified cedula application
     */
    public function update(Request $request, $id)
    {
        try {
            $user = $request->user();
            
            $cedula = Cedula::where('id', $id)
                ->where('user_id', $user->id)
                ->first();

            if (!$cedula) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cedula application not found'
                ], 404);
            }

            if ($cedula->status !== 'pending') {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot update cedula application with status: ' . $cedula->status
                ], 403);
            }

            $validator = Validator::make($request->all(), [
                'full_name' => 'required|string|max:255',
                'email' => 'required|email|max:255',
                'phone' => 'required|string|max:20',
                'address' => 'required|string|max:500',
                'birth_date' => 'required|date',
                'civil_status' => 'required|string|in:single,married,widowed,separated',
                'citizenship' => 'required|string|max:100',
                'occupation' => 'required|string|max:255',
                'tin_number' => 'nullable|string|max:50',
                'height' => 'required|numeric|min:0|max:999.99',
                'height_unit' => 'required|string|in:cm,in,ft',
                'weight' => 'required|numeric|min:0|max:999.99',
                'weight_unit' => 'required|string|in:kg,lbs',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $cedula->update([
                'full_name' => $request->full_name,
                'email' => $request->email,
                'phone' => $request->phone,
                'address' => $request->address,
                'birth_date' => $request->birth_date,
                'civil_status' => $request->civil_status,
                'citizenship' => $request->citizenship,
                'occupation' => $request->occupation,
                'tin_number' => $request->tin_number,
                'height' => $request->height,
                'height_unit' => $request->height_unit,
                'weight' => $request->weight,
                'weight_unit' => $request->weight_unit,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Cedula application updated successfully',
                'data' => $cedula
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update cedula application',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified cedula application
     */
    public function destroy(Request $request, $id)
    {
        try {
            $user = $request->user();
            
            $cedula = Cedula::where('id', $id)
                ->where('user_id', $user->id)
                ->first();

            if (!$cedula) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cedula application not found'
                ], 404);
            }

            if ($cedula->status !== 'pending') {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot delete cedula application with status: ' . $cedula->status
                ], 403);
            }

            $cedula->delete();

            return response()->json([
                'success' => true,
                'message' => 'Cedula application deleted successfully'
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete cedula application',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get user's last cedula data for auto-fill
     */
    public function getLastCedulaData(Request $request)
    {
        try {
            $user = $request->user();
            
            $lastCedula = Cedula::where('user_id', $user->id)
                ->orderBy('created_at', 'desc')
                ->first();

            if (!$lastCedula) {
                return response()->json([
                    'success' => true,
                    'data' => null
                ], 200);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'full_name' => $lastCedula->full_name,
                    'email' => $lastCedula->email,
                    'phone' => $lastCedula->phone,
                    'address' => $lastCedula->address,
                    'birth_date' => $lastCedula->birth_date,
                    'civil_status' => $lastCedula->civil_status,
                    'citizenship' => $lastCedula->citizenship,
                    'occupation' => $lastCedula->occupation,
                    'tin_number' => $lastCedula->tin_number,
                    'height' => $lastCedula->height,
                    'height_unit' => $lastCedula->height_unit,
                    'weight' => $lastCedula->weight,
                    'weight_unit' => $lastCedula->weight_unit,
                ]
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch auto-fill data',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}