<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\HealthCertificate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class HealthCertificateController extends Controller
{
    /**
     * Display a listing of health certificate applications (user's own).
     */
    public function index(Request $request)
    {
        $query = HealthCertificate::query();

        // Filter by user if authenticated
        if ($request->user()) {
            $query->where('user_id', $request->user()->id);
        }

        // Filter by status
        if ($request->has('status') && $request->status !== 'all') {
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

        // Sort
        $sortBy = $request->get('sort_by', 'created_at');
        $sortOrder = $request->get('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        // Paginate or get all
        if ($request->has('per_page')) {
            $perPage = $request->get('per_page', 15);
            $applications = $query->paginate($perPage);
            
            return response()->json([
                'success' => true,
                'data' => $applications,
            ]);
        } else {
            $applications = $query->get();
            
            return response()->json([
                'success' => true,
                'data' => $applications,
            ]);
        }
    }

    /**
     * Display a listing of all health certificate applications (admin).
     */
    public function adminIndex(Request $request)
    {
        $query = HealthCertificate::query();

        // Don't filter by user - admin sees all applications
        
        // Filter by status
        if ($request->has('status') && $request->status !== 'all') {
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

        // Sort
        $sortBy = $request->get('sort_by', 'created_at');
        $sortOrder = $request->get('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        // Paginate
        $perPage = $request->get('per_page', 15);
        $applications = $query->paginate($perPage);
        
        return response()->json([
            'success' => true,
            'data' => $applications,
        ]);
    }

    /**
     * Store a newly created health certificate application.
     */
    public function store(Request $request)
    {
        // Log the incoming request for debugging
        Log::info('Health Certificate Request Received', [
            'has_user' => $request->user() ? true : false,
            'user_id' => $request->user() ? $request->user()->id : null,
            'request_data' => $request->except(['password']),
        ]);

        // Check if user is authenticated
        $user = $request->user();
        
        if (!$user) {
            Log::warning('Health certificate submission attempted without authentication');
            return response()->json([
                'success' => false,
                'message' => 'Authentication required',
            ], 401);
        }

        // Validate the request
        $validator = Validator::make($request->all(), [
            'fullName' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'phone' => 'required|string|max:20',
            'address' => 'required|string',
            'birthDate' => 'required|date',
            'age' => 'required|integer|min:0|max:150',
            'sex' => 'required|in:male,female',
            'purpose' => 'required|string|max:255',
            'hasAllergies' => 'sometimes|boolean',
            'allergies' => 'nullable|string',
            'hasMedications' => 'sometimes|boolean',
            'medications' => 'nullable|string',
            'hasConditions' => 'sometimes|boolean',
            'conditions' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            Log::warning('Health certificate validation failed', [
                'errors' => $validator->errors()->toArray(),
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        // Start transaction for data integrity
        DB::beginTransaction();
        
        try {
            // Prepare data for insertion
            $data = [
                'reference_number' => HealthCertificate::generateReferenceNumber(),
                'user_id' => $user->id,
                'full_name' => $request->input('fullName'),
                'email' => $request->input('email'),
                'phone' => $request->input('phone'),
                'address' => $request->input('address'),
                'birth_date' => $request->input('birthDate'),
                'age' => $request->input('age'),
                'sex' => $request->input('sex'),
                'purpose' => $request->input('purpose'),
                'has_allergies' => $request->input('hasAllergies', false),
                'allergies' => $request->input('allergies'),
                'has_medications' => $request->input('hasMedications', false),
                'medications' => $request->input('medications'),
                'has_conditions' => $request->input('hasConditions', false),
                'conditions' => $request->input('conditions'),
                'status' => 'pending',
            ];

            Log::info('Attempting to create health certificate', [
                'user_id' => $data['user_id'],
                'reference_number' => $data['reference_number'],
                'full_name' => $data['full_name'],
            ]);

            // Create the application
            $application = HealthCertificate::create($data);

            // Verify the record was created
            if (!$application || !$application->exists) {
                throw new \Exception('Failed to create health certificate record');
            }

            // Verify user_id was saved
            if (!$application->user_id) {
                Log::error('Health certificate created but user_id is null', [
                    'application_id' => $application->id,
                    'expected_user_id' => $user->id,
                    'application_data' => $application->toArray(),
                ]);
            }

            DB::commit();

            Log::info('Health certificate created successfully', [
                'application_id' => $application->id,
                'reference_number' => $application->reference_number,
                'user_id' => $application->user_id,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Health certificate application submitted successfully',
                'data' => [
                    'reference_number' => $application->reference_number,
                    'application' => $application,
                ],
            ], 201);

        } catch (\Illuminate\Database\QueryException $e) {
            DB::rollBack();
            
            Log::error('Database error creating health certificate', [
                'error' => $e->getMessage(),
                'sql' => $e->getSql(),
                'bindings' => $e->getBindings(),
                'user_id' => $user->id,
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Database error occurred',
                'error' => config('app.debug') ? $e->getMessage() : 'Please contact support',
            ], 500);
            
        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Failed to create health certificate', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_id' => $user->id,
                'line' => $e->getLine(),
                'file' => $e->getFile(),
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to submit application',
                'error' => config('app.debug') ? $e->getMessage() : 'An unexpected error occurred',
            ], 500);
        }
    }

    /**
     * Display the specified health certificate application.
     */
    public function show($id)
    {
        $application = HealthCertificate::find($id);

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
    public function getByReferenceNumber($referenceNumber)
    {
        $application = HealthCertificate::where('reference_number', $referenceNumber)->first();

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
     * Update the specified health certificate application.
     */
    public function update(Request $request, $id)
    {
        $application = HealthCertificate::find($id);

        if (!$application) {
            return response()->json([
                'success' => false,
                'message' => 'Application not found',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'status' => 'sometimes|in:pending,under_review,approved,rejected,completed',
            'remarks' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $application->update($request->only(['status', 'remarks']));

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
     * Update status of the health certificate application.
     */
    public function updateStatus(Request $request, $id)
    {
        $application = HealthCertificate::find($id);

        if (!$application) {
            return response()->json([
                'success' => false,
                'message' => 'Application not found',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'status' => 'required|in:pending,under_review,approved,rejected,completed',
            'remarks' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $application->update([
                'status' => $request->status,
                'remarks' => $request->remarks,
            ]);

            Log::info('Health certificate status updated', [
                'application_id' => $application->id,
                'old_status' => $application->getOriginal('status'),
                'new_status' => $application->status,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Application status updated successfully',
                'data' => $application,
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to update health certificate status', [
                'error' => $e->getMessage(),
                'application_id' => $id,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to update application status',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Remove the specified health certificate application.
     */
    public function destroy($id)
    {
        $application = HealthCertificate::find($id);

        if (!$application) {
            return response()->json([
                'success' => false,
                'message' => 'Application not found',
            ], 404);
        }

        try {
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
     * Get statistics for health certificate applications.
     */
    public function statistics(Request $request)
    {
        $query = HealthCertificate::query();

        // Filter by user if authenticated
        if ($request->user()) {
            $query->where('user_id', $request->user()->id);
        }

        $stats = [
            'total' => (clone $query)->count(),
            'pending' => (clone $query)->where('status', 'pending')->count(),
            'under_review' => (clone $query)->where('status', 'under_review')->count(),
            'approved' => (clone $query)->where('status', 'approved')->count(),
            'rejected' => (clone $query)->where('status', 'rejected')->count(),
            'completed' => (clone $query)->where('status', 'completed')->count(),
        ];

        return response()->json([
            'success' => true,
            'data' => $stats,
        ]);
    }
}