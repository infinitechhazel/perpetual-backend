<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BuildingPermit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class BuildingPermitController extends Controller
{
    public function index(Request $request)
    {
        $query = BuildingPermit::with('user')->where('user_id', $request->user()->id);

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Search
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('reference_number', 'like', "%{$search}%")
                  ->orWhere('owner_name', 'like', "%{$search}%")
                  ->orWhere('project_type', 'like', "%{$search}%");
            });
        }

        $permits = $query->latest()->paginate(10);

        return response()->json([
            'success' => true,
            'data' => $permits
        ]);
    }

    /**
     * Admin view - Get all building permits with pagination and filters
     */
    public function adminIndex(Request $request)
    {
        $query = BuildingPermit::with('user');

        // Filter by status
        if ($request->has('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        // Search
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('reference_number', 'like', "%{$search}%")
                  ->orWhere('owner_name', 'like', "%{$search}%")
                  ->orWhere('project_type', 'like', "%{$search}%")
                  ->orWhere('project_scope', 'like', "%{$search}%")
                  ->orWhere('barangay', 'like', "%{$search}%")
                  ->orWhere('owner_email', 'like', "%{$search}%")
                  ->orWhere('owner_phone', 'like', "%{$search}%");
            });
        }

        $perPage = $request->get('per_page', 15);
        $permits = $query->latest()->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $permits
        ]);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'projectType' => 'required|string|in:new-construction,renovation,addition,repair',
            'projectScope' => 'required|string|in:residential,commercial,industrial',
            'projectDescription' => 'required|string',
            'lotArea' => 'nullable|numeric|min:0',
            'floorArea' => 'nullable|numeric|min:0',
            'numberOfFloors' => 'required|integer|min:1',
            'estimatedCost' => 'required|numeric|min:0',
            'ownerName' => 'required|string|max:255',
            'ownerEmail' => 'required|email|max:255',
            'ownerPhone' => 'required|string|max:20',
            'ownerAddress' => 'required|string',
            'propertyAddress' => 'required|string',
            'barangay' => 'required|string|max:255',
            'buildingPlans' => 'required|file|mimes:pdf,jpg,jpeg,png|max:10240',
            'landTitle' => 'required|file|mimes:pdf,jpg,jpeg,png|max:10240',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // Handle file uploads to public directory
            $buildingPlansPath = null;
            $landTitlePath = null;

            if ($request->hasFile('buildingPlans')) {
                $file = $request->file('buildingPlans');
                $fileName = time() . '_plans_' . $file->getClientOriginalName();
                $destinationPath = public_path('uploads/building-permits/plans');
                
                // Create directory if it doesn't exist
                if (!file_exists($destinationPath)) {
                    mkdir($destinationPath, 0755, true);
                }
                
                $file->move($destinationPath, $fileName);
                $buildingPlansPath = 'uploads/building-permits/plans/' . $fileName;
            }

            if ($request->hasFile('landTitle')) {
                $file = $request->file('landTitle');
                $fileName = time() . '_title_' . $file->getClientOriginalName();
                $destinationPath = public_path('uploads/building-permits/titles');
                
                // Create directory if it doesn't exist
                if (!file_exists($destinationPath)) {
                    mkdir($destinationPath, 0755, true);
                }
                
                $file->move($destinationPath, $fileName);
                $landTitlePath = 'uploads/building-permits/titles/' . $fileName;
            }

            $permit = BuildingPermit::create([
                'user_id' => $request->user()->id,
                'project_type' => $request->projectType,
                'project_scope' => $request->projectScope,
                'project_description' => $request->projectDescription,
                'lot_area' => $request->lotArea,
                'floor_area' => $request->floorArea,
                'number_of_floors' => $request->numberOfFloors,
                'estimated_cost' => $request->estimatedCost,
                'owner_name' => $request->ownerName,
                'owner_email' => $request->ownerEmail,
                'owner_phone' => $request->ownerPhone,
                'owner_address' => $request->ownerAddress,
                'property_address' => $request->propertyAddress,
                'barangay' => $request->barangay,
                'building_plans_path' => $buildingPlansPath,
                'land_title_path' => $landTitlePath,
                'status' => 'pending',
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Building permit application submitted successfully',
                'data' => [
                    'reference_number' => $permit->reference_number,
                    'permit' => $permit
                ]
            ], 201);
        } catch (\Exception $e) {
            // Clean up uploaded files if database insert fails
            if ($buildingPlansPath && file_exists(public_path($buildingPlansPath))) {
                unlink(public_path($buildingPlansPath));
            }
            if ($landTitlePath && file_exists(public_path($landTitlePath))) {
                unlink(public_path($landTitlePath));
            }

            return response()->json([
                'success' => false,
                'message' => 'Failed to submit application: ' . $e->getMessage()
            ], 500);
        }
    }

    public function show(Request $request, $id)
    {
        $permit = BuildingPermit::where('user_id', $request->user()->id)
            ->where('id', $id)
            ->with('user')
            ->first();

        if (!$permit) {
            return response()->json([
                'success' => false,
                'message' => 'Building permit not found'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $permit
        ]);
    }

    /**
     * Update building permit status (Admin only)
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

        $permit = BuildingPermit::find($id);

        if (!$permit) {
            return response()->json([
                'success' => false,
                'message' => 'Building permit not found'
            ], 404);
        }

        try {
            $updateData = [
                'status' => $request->status,
            ];

            // If approved, generate permit number and set dates
            if ($request->status === 'approved') {
                $updateData['permit_number'] = 'BP-' . strtoupper(uniqid());
                $updateData['approved_at'] = now();
                $updateData['expires_at'] = now()->addYear(); // Valid for 1 year
                $updateData['rejection_reason'] = null;
            }

            // If rejected, save rejection reason
            if ($request->status === 'rejected') {
                $updateData['rejection_reason'] = $request->rejection_reason;
                $updateData['permit_number'] = null;
                $updateData['approved_at'] = null;
                $updateData['expires_at'] = null;
            }

            // If set back to pending or processing, clear rejection reason
            if (in_array($request->status, ['pending', 'processing'])) {
                $updateData['rejection_reason'] = null;
            }

            $permit->update($updateData);

            return response()->json([
                'success' => true,
                'message' => 'Building permit status updated successfully',
                'data' => $permit->fresh()
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update status: ' . $e->getMessage()
            ], 500);
        }
    }

    public function update(Request $request, $id)
    {
        $permit = BuildingPermit::where('user_id', $request->user()->id)
            ->where('id', $id)
            ->first();

        if (!$permit) {
            return response()->json([
                'success' => false,
                'message' => 'Building permit not found'
            ], 404);
        }

        // Only allow updates if status is pending
        if ($permit->status !== 'pending') {
            return response()->json([
                'success' => false,
                'message' => 'Cannot update application that is already being processed'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'projectType' => 'sometimes|string|in:new-construction,renovation,addition,repair',
            'projectScope' => 'sometimes|string|in:residential,commercial,industrial',
            'projectDescription' => 'sometimes|string',
            'lotArea' => 'nullable|numeric|min:0',
            'floorArea' => 'nullable|numeric|min:0',
            'numberOfFloors' => 'sometimes|integer|min:1',
            'estimatedCost' => 'sometimes|numeric|min:0',
            'ownerName' => 'sometimes|string|max:255',
            'ownerEmail' => 'sometimes|email|max:255',
            'ownerPhone' => 'sometimes|string|max:20',
            'ownerAddress' => 'sometimes|string',
            'propertyAddress' => 'sometimes|string',
            'barangay' => 'sometimes|string|max:255',
            'buildingPlans' => 'sometimes|file|mimes:pdf,jpg,jpeg,png|max:10240',
            'landTitle' => 'sometimes|file|mimes:pdf,jpg,jpeg,png|max:10240',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $updateData = [];

            // Map camelCase to snake_case
            if ($request->has('projectType')) $updateData['project_type'] = $request->projectType;
            if ($request->has('projectScope')) $updateData['project_scope'] = $request->projectScope;
            if ($request->has('projectDescription')) $updateData['project_description'] = $request->projectDescription;
            if ($request->has('lotArea')) $updateData['lot_area'] = $request->lotArea;
            if ($request->has('floorArea')) $updateData['floor_area'] = $request->floorArea;
            if ($request->has('numberOfFloors')) $updateData['number_of_floors'] = $request->numberOfFloors;
            if ($request->has('estimatedCost')) $updateData['estimated_cost'] = $request->estimatedCost;
            if ($request->has('ownerName')) $updateData['owner_name'] = $request->ownerName;
            if ($request->has('ownerEmail')) $updateData['owner_email'] = $request->ownerEmail;
            if ($request->has('ownerPhone')) $updateData['owner_phone'] = $request->ownerPhone;
            if ($request->has('ownerAddress')) $updateData['owner_address'] = $request->ownerAddress;
            if ($request->has('propertyAddress')) $updateData['property_address'] = $request->propertyAddress;
            if ($request->has('barangay')) $updateData['barangay'] = $request->barangay;

            // Handle file uploads to public directory
            if ($request->hasFile('buildingPlans')) {
                // Delete old file
                if ($permit->building_plans_path && file_exists(public_path($permit->building_plans_path))) {
                    unlink(public_path($permit->building_plans_path));
                }
                
                $file = $request->file('buildingPlans');
                $fileName = time() . '_plans_' . $file->getClientOriginalName();
                $destinationPath = public_path('uploads/building-permits/plans');
                
                if (!file_exists($destinationPath)) {
                    mkdir($destinationPath, 0755, true);
                }
                
                $file->move($destinationPath, $fileName);
                $updateData['building_plans_path'] = 'uploads/building-permits/plans/' . $fileName;
            }

            if ($request->hasFile('landTitle')) {
                // Delete old file
                if ($permit->land_title_path && file_exists(public_path($permit->land_title_path))) {
                    unlink(public_path($permit->land_title_path));
                }
                
                $file = $request->file('landTitle');
                $fileName = time() . '_title_' . $file->getClientOriginalName();
                $destinationPath = public_path('uploads/building-permits/titles');
                
                if (!file_exists($destinationPath)) {
                    mkdir($destinationPath, 0755, true);
                }
                
                $file->move($destinationPath, $fileName);
                $updateData['land_title_path'] = 'uploads/building-permits/titles/' . $fileName;
            }

            $permit->update($updateData);

            return response()->json([
                'success' => true,
                'message' => 'Building permit application updated successfully',
                'data' => $permit
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update application: ' . $e->getMessage()
            ], 500);
        }
    }

    public function destroy(Request $request, $id)
    {
        $permit = BuildingPermit::where('user_id', $request->user()->id)
            ->where('id', $id)
            ->first();

        if (!$permit) {
            return response()->json([
                'success' => false,
                'message' => 'Building permit not found'
            ], 404);
        }

        // Only allow deletion if status is pending
        if ($permit->status !== 'pending') {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete application that is already being processed'
            ], 403);
        }

        try {
            // Delete associated files from public directory
            if ($permit->building_plans_path && file_exists(public_path($permit->building_plans_path))) {
                unlink(public_path($permit->building_plans_path));
            }
            if ($permit->land_title_path && file_exists(public_path($permit->land_title_path))) {
                unlink(public_path($permit->land_title_path));
            }

            $permit->delete();

            return response()->json([
                'success' => true,
                'message' => 'Building permit application deleted successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete application: ' . $e->getMessage()
            ], 500);
        }
    }
}