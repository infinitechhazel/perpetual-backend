<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BarangayBlotter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
class BarangayBlotterController extends Controller
{
    /**
     * Display a listing of the user's blotter reports
     */
    public function index(Request $request)
    {
        $blotters = BarangayBlotter::where('user_id', $request->user()->id)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $blotters,
        ]);
    }

    /**
     * Admin view - Get all barangay blotters with pagination and filters
     */
    public function adminIndex(Request $request)
    {
        $query = BarangayBlotter::with('user');

        // Filter by status
        if ($request->has('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        // Filter by incident type
        if ($request->has('incident_type') && $request->incident_type !== 'all') {
            $query->where('incident_type', $request->incident_type);
        }

        // Search
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('full_name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('contact_number', 'like', "%{$search}%")
                  ->orWhere('complaint_against', 'like', "%{$search}%")
                  ->orWhere('narrative', 'like', "%{$search}%");
            });
        }

        $perPage = $request->get('per_page', 15);
        $blotters = $query->latest()->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $blotters
        ]);
    }

    /**
     * Store a newly created blotter report
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'fullName' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'age' => 'required|integer|min:1|max:150',
            'gender' => 'required|in:male,female,other',
            'address' => 'required|string',
            'contactNumber' => 'required|string|max:20',
            'incidentType' => 'required|string|in:theft,harassment,disturbance,accident,property_damage,lost_found,other',
            'incidentDate' => 'required|date',
            'incidentTime' => 'required|date_format:H:i',
            'incidentLocation' => 'required|string',
            'complaintAgainst' => 'required|string',
            'narrative' => 'required|string',
            'witness1Name' => 'nullable|string|max:255',
            'witness1Contact' => 'nullable|string|max:20',
            'witness2Name' => 'nullable|string|max:255',
            'witness2Contact' => 'nullable|string|max:20',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $blotter = BarangayBlotter::create([
                'user_id' => auth()->id(),
                'full_name' => $request->fullName,
                'email' => $request->email,
                'age' => $request->age,
                'gender' => $request->gender,
                'address' => $request->address,
                'contact_number' => $request->contactNumber,
                'incident_type' => $request->incidentType,
                'incident_date' => $request->incidentDate,
                'incident_time' => $request->incidentTime,
                'incident_location' => $request->incidentLocation,
                'complaint_against' => $request->complaintAgainst,
                'narrative' => $request->narrative,
                'witness1_name' => $request->witness1Name,
                'witness1_contact' => $request->witness1Contact,
                'witness2_name' => $request->witness2Name,
                'witness2_contact' => $request->witness2Contact,
                'status' => 'filed',
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Barangay blotter report submitted successfully',
                'data' => $blotter,
            ], 201);
        } catch (\Exception $e) {
            Log::error('Blotter creation error:', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to submit blotter report',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Display the specified blotter report
     */
    public function show(Request $request, $id)
    {
        $blotter = BarangayBlotter::where('user_id', auth()->id())
            ->where('id', $id)
            ->first();

        if (!$blotter) {
            return response()->json([
                'success' => false,
                'message' => 'Blotter report not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $blotter,
        ]);
    }

    /**
     * Update the specified blotter report status (admin only)
     */
    public function updateStatus(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'status' => 'required|in:filed,under_investigation,resolved,closed',
            'remarks' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $blotter = BarangayBlotter::find($id);

        if (!$blotter) {
            return response()->json([
                'success' => false,
                'message' => 'Blotter report not found',
            ], 404);
        }

        try {
            $blotter->update([
                'status' => $request->status,
                'remarks' => $request->remarks,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Blotter report status updated successfully',
                'data' => $blotter->fresh()
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update status: ' . $e->getMessage()
            ], 500);
        }
    }

    public function destroy(Request $request, $id)
    {
        $blotter = BarangayBlotter::where('user_id', auth()->id())
            ->where('id', $id)
            ->first();

        if (!$blotter) {
            return response()->json([
                'success' => false,
                'message' => 'Blotter report not found',
            ], 404);
        }

        $blotter->delete();

        return response()->json([
            'success' => true,
            'message' => 'Blotter report deleted successfully',
        ]);
    }
}
