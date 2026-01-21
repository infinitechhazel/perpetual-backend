<?php

namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use App\Models\BusinessPermit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
class BusinessPermitController extends Controller
{
    /**
     * Get all business permits for the authenticated user
     */
    public function index(Request $request)
    {
        $permits = BusinessPermit::where('user_id', $request->user()->id)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $permits,
        ], 200);
    }

    /**
     * Get a specific business permit
     */
    public function show(Request $request, $id)
    {
        $permit = BusinessPermit::where('user_id', $request->user()->id)
            ->find($id);

        if (!$permit) {
            return response()->json([
                'success' => false,
                'message' => 'Business permit not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $permit,
        ], 200);
    }

    /**
     * Store a new business permit application
     */
   public function store(Request $request)
{
    // First, check if user is authenticated
    $user = auth('sanctum')->user();
    
    if (!$user) {
        Log::error('Business Permit - User not authenticated', [
            'has_bearer_token' => $request->bearerToken() ? 'yes' : 'no',
            'auth_header' => $request->header('Authorization') ? 'present' : 'missing',
        ]);
        
        return response()->json([
            'success' => false,
            'message' => 'User not authenticated. Please log in again.',
        ], 401);
    }

    $validator = Validator::make($request->all(), [
        'businessName' => 'required|string|max:255',
        'businessType' => 'required|string|in:sole-proprietorship,partnership,corporation',
        'businessCategory' => 'required|string|max:100',
        'businessCategoryOther' => 'nullable|string|max:100|required_if:businessCategory,other',
        'businessDescription' => 'required|string|max:1000',
        'ownerName' => 'required|string|max:255',
        'ownerEmail' => 'required|email|max:255',
        'ownerPhone' => 'required|string|max:20',
        'ownerAddress' => 'required|string|max:500',
        'businessAddress' => 'required|string|max:500',
        'barangay' => 'required|string|max:100',
        'lotNumber' => 'nullable|string|max:50',
        'floorArea' => 'required|numeric|min:1',
    ]);

    if ($validator->fails()) {
        return response()->json([
            'success' => false,
            'message' => 'Validation failed',
            'errors' => $validator->errors(),
        ], 422);
    }

    try {
        $permit = BusinessPermit::create([
            'user_id' => $user->id, // Use $user->id instead of $request->user()->id
            'business_name' => $request->businessName,
            'business_type' => $request->businessType,
            'business_category' => $request->businessCategory,
            'business_category_other' => $request->businessCategoryOther,
            'business_description' => $request->businessDescription,
            'owner_name' => $request->ownerName,
            'owner_email' => $request->ownerEmail,
            'owner_phone' => $request->ownerPhone,
            'owner_address' => $request->ownerAddress,
            'business_address' => $request->businessAddress,
            'barangay' => $request->barangay,
            'lot_number' => $request->lotNumber,
            'floor_area' => $request->floorArea,
            'status' => 'pending',
        ]);

        Log::info('Business Permit created successfully', [
            'permit_id' => $permit->id,
            'user_id' => $user->id,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Business permit application submitted successfully',
            'data' => $permit,
        ], 201);

    } catch (\Exception $e) {
        Log::error('Business Permit creation failed', [
            'error' => $e->getMessage(),
            'user_id' => $user->id,
        ]);
        
        return response()->json([
            'success' => false,
            'message' => 'Failed to submit application: ' . $e->getMessage(),
        ], 500);
    }
}
    /**
     * Admin: Get all business permit applications
     */
    /**
 * Admin: Get all business permit applications with pagination
 */
public function adminIndex(Request $request)
{
    $query = BusinessPermit::with('user:id,name,email');

    // Filter by status
    if ($request->has('status') && $request->status !== 'all') {
        $query->where('status', $request->status);
    }

    // Search
    if ($request->has('search')) {
        $search = $request->search;
        $query->where(function($q) use ($search) {
            $q->where('business_name', 'like', "%{$search}%")
              ->orWhere('owner_name', 'like', "%{$search}%")
              ->orWhere('owner_email', 'like', "%{$search}%")
              ->orWhere('barangay', 'like', "%{$search}%");
        });
    }

    // Order by most recent
    $query->orderBy('created_at', 'desc');

    // Paginate
    $perPage = $request->get('per_page', 15);
    $permits = $query->paginate($perPage);

    return response()->json([
        'success' => true,
        'data' => $permits,
    ], 200);
}

    /**
     * Admin: Update business permit status
     */
    public function updateStatus(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'status' => 'required|string|in:pending,processing,approved,rejected',
            'rejection_reason' => 'nullable|string|max:500|required_if:status,rejected',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $permit = BusinessPermit::find($id);

        if (!$permit) {
            return response()->json([
                'success' => false,
                'message' => 'Business permit not found',
            ], 404);
        }

        try {
            $permit->status = $request->status;
            $permit->rejection_reason = $request->rejection_reason;

            // If approved, generate permit number and set expiry
            if ($request->status === 'approved' && !$permit->permit_number) {
                $permit->permit_number = BusinessPermit::generatePermitNumber();
                $permit->approved_at = now();
                $permit->expires_at = now()->addYear(); // Valid for 1 year
            }

            $permit->save();

            return response()->json([
                'success' => true,
                'message' => 'Business permit status updated successfully',
                'data' => $permit,
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update status: ' . $e->getMessage(),
            ], 500);
        }
    }
}