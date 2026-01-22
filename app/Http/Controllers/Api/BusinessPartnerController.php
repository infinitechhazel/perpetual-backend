<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BusinessPartner;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class BusinessPartnerController extends Controller
{
    // Public: only approved businesses     
    public function index(Request $request)
    {
        $query = BusinessPartner::query();

        // Only approved businesses
        $query->where('status', 'approved');

        // Optional search by name, category, or description
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('business_name', 'like', "%$search%")
                    ->orWhere('category', 'like', "%$search%")
                    ->orWhere('description', 'like', "%$search%");
            });
        }

        $perPage = $request->get('per_page', 10);
        $businesses = $query->latest()->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $businesses,
        ]);
    }

    /**
     * User: List their own businesses
     */
    public function userIndex(Request $request)
    {
        $user = Auth::user();
        if (!$user->isMember()) {
            return response()->json(['success' => false, 'message' => 'Only users can view their businesses.'], 403);
        }

        $query = BusinessPartner::where('user_id', $user->id);

        if ($request->filled('category')) {
            $query->where('category', $request->category);
        }

        $perPage = $request->get('per_page', 10);
        $businesses = $query->latest()->paginate($perPage);

        return response()->json(['success' => true, 'data' => $businesses]);
    }

    /**
     * User: store a new business partnership
     */
    public function store(Request $request)
    {
        $user = Auth::user();

        if (!$user->isMember()) {
            return response()->json([
                'success' => false,
                'message' => 'Only users can create businesses.',
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'business_name' => 'required|string|max:255',
            'website_link' => 'nullable|url|max:255',
            'photo' => 'nullable|image|max:5120', // 5MB
            'description' => 'nullable|string',
            'category' => 'nullable|string|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $photoPath = null;

            // Handle file upload to public folder
            if ($request->hasFile('photo')) {
                $file = $request->file('photo');
                $filename = time() . '_' . $file->getClientOriginalName();
                $destination = public_path('business_photos');

                // Create folder if it doesn't exist
                if (!file_exists($destination)) {
                    mkdir($destination, 0777, true);
                }

                // Move the file
                $file->move($destination, $filename);

                // Relative path for frontend / DB (with leading slash)
                $photoPath = "/business_photos/$filename";
            }

            $business = BusinessPartner::create([
                'user_id' => $user->id,
                'business_name' => $request->business_name,
                'website_link' => $request->website_link,
                'photo' => $photoPath,
                'description' => $request->description,
                'category' => $request->category,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Business created.',
                'data' => $business,
            ], 201);
        } catch (\Exception $e) {
            Log::error('Business creation failed', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to create business',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * User: update own business (PENDING only)
     */
    public function userUpdate(Request $request, $id)
    {
        try {
            $user = Auth::user();

            if (!$user->isMember()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Only users can update businesses.',
                ], 403);
            }

            $business = BusinessPartner::where('id', $id)
                ->where('user_id', $user->id)
                ->first();

            if (!$business) {
                return response()->json([
                    'success' => false,
                    'message' => 'Business not found or unauthorized.',
                ], 404);
            }

            if ($business->status !== 'pending') {
                return response()->json([
                    'success' => false,
                    'message' => 'Only pending businesses can be edited.',
                ], 403);
            }

            // Merge PUT data and files
            $input = array_merge($request->all(), $request->allFiles());

            $validator = Validator::make($input, [
                'business_name' => 'sometimes|string|max:255',
                'website_link' => 'nullable|url|max:255',
                'photo' => 'nullable|image|max:5120', // 5MB
                'description' => 'nullable|string',
                'category' => 'sometimes|string|max:100',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors(),
                ], 422);
            }

            // Handle photo upload
            if ($request->hasFile('photo')) {
                if ($business->photo && file_exists(public_path($business->photo))) {
                    unlink(public_path($business->photo));
                }

                $file = $request->file('photo');
                $filename = time() . '_' . $file->getClientOriginalName();
                $destination = public_path('business_photos');

                if (!file_exists($destination)) {
                    mkdir($destination, 0777, true);
                }

                $file->move($destination, $filename);
                $business->photo = "/business_photos/$filename";
            } elseif ($request->has('photo') && $request->photo === '') {
                if ($business->photo && file_exists(public_path($business->photo))) {
                    unlink(public_path($business->photo));
                }
                $business->photo = null;
            }

            // Fill other fields safely
            $fillable = ['business_name', 'website_link', 'description', 'category'];
            foreach ($fillable as $field) {
                if ($request->has($field)) {
                    $business->$field = $request->$field;
                }
            }

            $business->save();

            return response()->json([
                'success' => true,
                'message' => 'Business updated successfully.',
                'data' => $business,
            ]);
        } catch (\Throwable $e) {
            Log::error('PUT update failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request' => $request->all(),
                'files' => $request->allFiles(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Internal server error',
                'error' => $e->getMessage(),
            ], 500);
        }
    }




    /**
     * User: delete own business 
     */
    public function userDestroy($id)
    {
        $user = Auth::user();

        if (!$user->isMember()) {
            return response()->json([
                'success' => false,
                'message' => 'Only users can delete businesses.',
            ], 403);
        }

        $business = BusinessPartner::where('id', $id)
            ->where('user_id', $user->id)
            ->first();

        if (!$business) {
            return response()->json([
                'success' => false,
                'message' => 'Business not found or unauthorized.',
            ], 404);
        }

        try {
            // Delete photo if exists
            if ($business->photo && file_exists(public_path($business->photo))) {
                unlink(public_path($business->photo));
            }

            $business->delete();

            return response()->json([
                'success' => true,
                'message' => 'Business deleted successfully.',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete business.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }


    /**
     * Admin: list all businesses
     */
    public function adminIndex(Request $request)
    {
        $admin = Auth::user();
        if (!$admin->isAdmin()) {
            return response()->json(['success' => false, 'message' => 'Only admins can view businesses.'], 403);
        }

        $query = BusinessPartner::query();

        if ($request->filled('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('business_name', 'like', "%$search%")
                    ->orWhere('category', 'like', "%$search%")
                    ->orWhere('description', 'like', "%$search%");
            });
        }

        $perPage = $request->get('per_page', 10);
        $businesses = $query->latest()->paginate($perPage);

        return response()->json(['success' => true, 'data' => $businesses]);
    }

    /**
     * Admin: update any business
     */
    public function adminUpdate(Request $request, $id)
    {
        try {
            $admin = Auth::user();
            if (!$admin->isAdmin()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Only admins can update businesses.'
                ], 403);
            }

            $business = BusinessPartner::find($id);
            if (!$business) {
                return response()->json([
                    'success' => false,
                    'message' => 'Business not found.'
                ], 404);
            }

            Log::info('Admin Update Request', [
                'all' => $request->all(),
                'files' => $request->allFiles(),
                'method' => $request->method(),
            ]);

            $validator = Validator::make($request->all(), [
                'business_name' => 'sometimes|string|max:255',
                'website_link' => 'nullable|url|max:255',
                'photo' => 'nullable|image|max:5120',
                'description' => 'nullable|string',
                'category' => 'sometimes|string|max:100',
                'status' => 'sometimes|in:pending,approved,rejected',
                'admin_note' => 'nullable|string|max:500',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Handle photo upload
            if ($request->hasFile('photo')) {
                // Delete old photo if exists
                if ($business->photo && file_exists(public_path($business->photo))) {
                    unlink(public_path($business->photo));
                }

                $file = $request->file('photo');
                $filename = time() . '_' . $file->getClientOriginalName();
                $destination = public_path('business_photos');

                if (!file_exists($destination)) {
                    mkdir($destination, 0777, true);
                }

                $file->move($destination, $filename);
                $business->photo = "/business_photos/$filename";
            } elseif ($request->input('photo') === '' || $request->input('photo') === null) {
                // Handle photo deletion
                if ($business->photo && file_exists(public_path($business->photo))) {
                    unlink(public_path($business->photo));
                }
                $business->photo = null;
            }

            // Update other fields safely
            $fillable = ['business_name', 'website_link', 'description', 'category', 'status', 'admin_note'];
            foreach ($fillable as $field) {
                if ($request->has($field)) {
                    $business->$field = $request->input($field);
                }
            }

            $business->save();

            Log::info('Business updated successfully', ['business' => $business]);

            return response()->json([
                'success' => true,
                'message' => 'Business updated successfully.',
                'data' => $business
            ]);

        } catch (\Throwable $e) {
            Log::error('Admin update failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request' => $request->all(),
                'files' => $request->allFiles(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Internal server error',
                'error' => $e->getMessage(),
            ], 500);
        }
    }


    public function adminDestroy($id)
    {
        $admin = Auth::user();
        if (!$admin->isAdmin()) {
            return response()->json(['success' => false, 'message' => 'Only admins can delete businesses.'], 403);
        }

        $business = BusinessPartner::find($id);
        if (!$business) {
            return response()->json(['success' => false, 'message' => 'Business not found.'], 404);
        }

        try {
            // Delete the photo from public folder if it exists
            if ($business->photo && file_exists(public_path($business->photo))) {
                unlink(public_path($business->photo));
            }

            $business->delete();

            return response()->json(['success' => true, 'message' => 'Business deleted successfully.']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Failed to delete business.', 'error' => $e->getMessage()], 500);
        }
    }
}