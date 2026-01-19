<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MedicalAssistance;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class MedicalAssistanceController extends Controller
{
    /**
     * Display a listing of medical assistance applications.
     */
    public function index(Request $request)
    {
        $query = MedicalAssistance::with('user')->where('user_id', $request->user()->id);

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Search by name, email, or reference number
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('full_name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('reference_number', 'like', "%{$search}%");
            });
        }

        $applications = $query->latest()->paginate(10);
        
        return response()->json([
            'success' => true,
            'data' => $applications,
        ]);
    }

    /**
     * Admin view - Get all medical assistance applications with pagination and filters
     */
    public function adminIndex(Request $request)
    {
        $query = MedicalAssistance::with('user');

        // Filter by status
        if ($request->has('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        // Search by name, email, diagnosis, or reference number
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('full_name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('reference_number', 'like', "%{$search}%")
                  ->orWhere('diagnosis', 'like', "%{$search}%")
                  ->orWhere('hospital_name', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        $perPage = $request->get('per_page', 15);
        $applications = $query->latest()->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $applications
        ]);
    }

    /**
     * Store a newly created medical assistance application.
     */
    public function store(Request $request)
    {
        // Check if user is authenticated
        if (!$request->user()) {
            return response()->json([
                'success' => false,
                'message' => 'Authentication required',
            ], 401);
        }

        $validator = Validator::make($request->all(), [
            'fullName' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'phone' => 'required|string|max:20',
            'address' => 'required|string',
            'birthDate' => 'required|date',
            'age' => 'required|integer|min:0|max:150',
            'sex' => 'required|in:male,female',
            'diagnosis' => 'required|string',
            'hospitalName' => 'required|string|max:255',
            'doctorName' => 'required|string|max:255',
            'estimatedCost' => 'required|numeric|min:0',
            'monthlyIncome' => 'required|numeric|min:0',
            'assistanceAmountRequested' => 'required|numeric|min:0',
            'supportingDocuments' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:10240', // 10MB max
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $data = [
                'reference_number' => MedicalAssistance::generateReferenceNumber(),
                'user_id' => $request->user()->id, // Always use authenticated user's ID
                'full_name' => $request->fullName,
                'email' => $request->email,
                'phone' => $request->phone,
                'address' => $request->address,
                'birth_date' => $request->birthDate,
                'age' => $request->age,
                'sex' => $request->sex,
                'diagnosis' => $request->diagnosis,
                'hospital_name' => $request->hospitalName,
                'doctor_name' => $request->doctorName,
                'estimated_cost' => $request->estimatedCost,
                'monthly_income' => $request->monthlyIncome,
                'assistance_amount_requested' => $request->assistanceAmountRequested,
                'status' => 'pending',
            ];

            // Handle file upload to public directory
            if ($request->hasFile('supportingDocuments')) {
                $file = $request->file('supportingDocuments');
                $filename = time() . '_' . $file->getClientOriginalName();
                $destinationPath = public_path('uploads/medical-assistance-documents');
                
                // Create directory if it doesn't exist
                if (!file_exists($destinationPath)) {
                    mkdir($destinationPath, 0755, true);
                }
                
                $file->move($destinationPath, $filename);
                $data['supporting_documents'] = 'uploads/medical-assistance-documents/' . $filename;
            }

            $application = MedicalAssistance::create($data);

            return response()->json([
                'success' => true,
                'message' => 'Medical assistance application submitted successfully',
                'data' => [
                    'reference_number' => $application->reference_number,
                    'application' => $application,
                ],
            ], 201);

        } catch (\Exception $e) {
            // Clean up uploaded file if database insert fails
            if (isset($data['supporting_documents']) && file_exists(public_path($data['supporting_documents']))) {
                unlink(public_path($data['supporting_documents']));
            }

            return response()->json([
                'success' => false,
                'message' => 'Failed to submit application',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Display the specified medical assistance application.
     */
    public function show(Request $request, $id)
    {
        $application = MedicalAssistance::where('user_id', $request->user()->id)
            ->where('id', $id)
            ->with('user')
            ->first();

        if (!$application) {
            return response()->json([
                'success' => false,
                'message' => 'Application not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $application,
        ]);
    }

    /**
     * Get application by reference number.
     */
    public function getByReferenceNumber(Request $request, $referenceNumber)
    {
        $application = MedicalAssistance::where('reference_number', $referenceNumber)->first();

        if (!$application) {
            return response()->json([
                'success' => false,
                'message' => 'Application not found',
            ], 404);
        }

        // Check if user owns this application
        if ($request->user() && $application->user_id !== $request->user()->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access',
            ], 403);
        }

        return response()->json([
            'success' => true,
            'data' => $application,
        ]);
    }

    /**
     * Update medical assistance status (Admin only)
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

        $application = MedicalAssistance::find($id);

        if (!$application) {
            return response()->json([
                'success' => false,
                'message' => 'Application not found'
            ], 404);
        }

        try {
            $updateData = [
                'status' => $request->status,
            ];

            // If approved, set approved date
            if ($request->status === 'approved') {
                $updateData['approved_at'] = now();
                $updateData['rejection_reason'] = null;
            }

            // If rejected, save rejection reason
            if ($request->status === 'rejected') {
                $updateData['rejection_reason'] = $request->rejection_reason;
                $updateData['approved_at'] = null;
            }

            // If set back to pending or processing, clear rejection reason
            if (in_array($request->status, ['pending', 'processing'])) {
                $updateData['rejection_reason'] = null;
            }

            $application->update($updateData);

            return response()->json([
                'success' => true,
                'message' => 'Application status updated successfully',
                'data' => $application->fresh()
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update status: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update the specified medical assistance application.
     */
    public function update(Request $request, $id)
    {
        $application = MedicalAssistance::where('user_id', $request->user()->id)
            ->where('id', $id)
            ->first();

        if (!$application) {
            return response()->json([
                'success' => false,
                'message' => 'Application not found',
            ], 404);
        }

        // Only allow updates if status is pending
        if ($application->status !== 'pending') {
            return response()->json([
                'success' => false,
                'message' => 'Cannot update application that is already being processed'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'fullName' => 'sometimes|string|max:255',
            'email' => 'sometimes|email|max:255',
            'phone' => 'sometimes|string|max:20',
            'address' => 'sometimes|string',
            'birthDate' => 'sometimes|date',
            'age' => 'sometimes|integer|min:0|max:150',
            'sex' => 'sometimes|in:male,female',
            'diagnosis' => 'sometimes|string',
            'hospitalName' => 'sometimes|string|max:255',
            'doctorName' => 'sometimes|string|max:255',
            'estimatedCost' => 'sometimes|numeric|min:0',
            'monthlyIncome' => 'sometimes|numeric|min:0',
            'assistanceAmountRequested' => 'sometimes|numeric|min:0',
            'supportingDocuments' => 'sometimes|file|mimes:pdf,jpg,jpeg,png|max:10240',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $updateData = [];

            // Map camelCase to snake_case
            if ($request->has('fullName')) $updateData['full_name'] = $request->fullName;
            if ($request->has('email')) $updateData['email'] = $request->email;
            if ($request->has('phone')) $updateData['phone'] = $request->phone;
            if ($request->has('address')) $updateData['address'] = $request->address;
            if ($request->has('birthDate')) $updateData['birth_date'] = $request->birthDate;
            if ($request->has('age')) $updateData['age'] = $request->age;
            if ($request->has('sex')) $updateData['sex'] = $request->sex;
            if ($request->has('diagnosis')) $updateData['diagnosis'] = $request->diagnosis;
            if ($request->has('hospitalName')) $updateData['hospital_name'] = $request->hospitalName;
            if ($request->has('doctorName')) $updateData['doctor_name'] = $request->doctorName;
            if ($request->has('estimatedCost')) $updateData['estimated_cost'] = $request->estimatedCost;
            if ($request->has('monthlyIncome')) $updateData['monthly_income'] = $request->monthlyIncome;
            if ($request->has('assistanceAmountRequested')) $updateData['assistance_amount_requested'] = $request->assistanceAmountRequested;

            // Handle file upload to public directory
            if ($request->hasFile('supportingDocuments')) {
                // Delete old file
                if ($application->supporting_documents && file_exists(public_path($application->supporting_documents))) {
                    unlink(public_path($application->supporting_documents));
                }
                
                $file = $request->file('supportingDocuments');
                $filename = time() . '_' . $file->getClientOriginalName();
                $destinationPath = public_path('uploads/medical-assistance-documents');
                
                if (!file_exists($destinationPath)) {
                    mkdir($destinationPath, 0755, true);
                }
                
                $file->move($destinationPath, $filename);
                $updateData['supporting_documents'] = 'uploads/medical-assistance-documents/' . $filename;
            }

            $application->update($updateData);

            return response()->json([
                'success' => true,
                'message' => 'Application updated successfully',
                'data' => $application,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update application',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Remove the specified medical assistance application.
     */
    public function destroy(Request $request, $id)
    {
        $application = MedicalAssistance::where('user_id', $request->user()->id)
            ->where('id', $id)
            ->first();

        if (!$application) {
            return response()->json([
                'success' => false,
                'message' => 'Application not found',
            ], 404);
        }

        // Only allow deletion if status is pending
        if ($application->status !== 'pending') {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete application that is already being processed'
            ], 403);
        }

        try {
            // Delete the file if exists
            if ($application->supporting_documents && file_exists(public_path($application->supporting_documents))) {
                unlink(public_path($application->supporting_documents));
            }

            $application->delete();

            return response()->json([
                'success' => true,
                'message' => 'Application deleted successfully',
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete application',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get statistics for medical assistance applications.
     */
    public function statistics(Request $request)
    {
        $query = MedicalAssistance::query();

        // Filter by user if authenticated
        if ($request->user()) {
            $query->where('user_id', $request->user()->id);
        }

        $stats = [
            'total' => (clone $query)->count(),
            'pending' => (clone $query)->where('status', 'pending')->count(),
            'processing' => (clone $query)->where('status', 'processing')->count(),
            'approved' => (clone $query)->where('status', 'approved')->count(),
            'rejected' => (clone $query)->where('status', 'rejected')->count(),
            'total_assistance_requested' => (clone $query)->sum('assistance_amount_requested'),
            'total_assistance_approved' => (clone $query)->where('status', 'approved')
                ->sum('assistance_amount_requested'),
        ];

        return response()->json([
            'success' => true,
            'data' => $stats,
        ]);
    }
}