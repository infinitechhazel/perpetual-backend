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
    /**
     * User: List their own businesses
     */
    public function index(Request $request)
    {
        $user = Auth::user();
        if (! $user->isMember()) {
            return response()->json(['success' => false, 'message' => 'Only users can view their businesses.'], 403);
        }

        $query = BusinessPartner::where('user_id', $user->id);

        if ($request->filled('category')) {
            $query->where('category', $request->category);
        }

        $perPage = $request->get('per_page', 15);
        $businesses = $query->latest()->paginate($perPage);

        return response()->json(['success' => true, 'data' => $businesses]);
    }

    /**
     * User: store a new business partnership
     */
    public function store(Request $request)
    {
        $user = Auth::user();

        if (! $user->isMember()) {
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
                $filename = time().'_'.$file->getClientOriginalName();
                $destination = public_path('business_photos');

                // Create folder if it doesn't exist
                if (! file_exists($destination)) {
                    mkdir($destination, 0777, true);
                }

                // Move the file
                $file->move($destination, $filename);

                // Relative path for frontend / DB
                $photoPath = 'business_photos/'.$filename;
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
     * Admin: list all businesses
     */
    public function adminIndex(Request $request)
    {
        $admin = Auth::user();
        if (! $admin->isAdmin()) {
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

        $perPage = $request->get('per_page', 15);
        $businesses = $query->latest()->paginate($perPage);

        return response()->json(['success' => true, 'data' => $businesses]);
    }

    /**
     * Admin: update any business
     */
    public function adminUpdate(Request $request, $id)
    {
        $admin = Auth::user();
        if (! $admin->isAdmin()) {
            return response()->json(['success' => false, 'message' => 'Only admins can update businesses.'], 403);
        }

        $business = BusinessPartner::find($id);
        if (! $business) {
            return response()->json(['success' => false, 'message' => 'Business not found.'], 404);
        }

        $validator = Validator::make($request->all(), [
            'business_name' => 'sometimes|string|max:255',
            'website_link' => 'nullable|url|max:255',
            'photo' => 'nullable|image|max:2048',
            'description' => 'nullable|string',
            'category' => 'sometimes|string|max:100',
            'status' => 'sometimes|in:pending,approved,rejected',
            'admin_note' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'message' => 'Validation failed', 'errors' => $validator->errors()], 422);
        }

        if ($request->file('photo')) {
            $business->photo = $request->file('photo')->store('business_photos', 'public');
        }

        $business->fill($request->only([
            'business_name', 'website_link', 'description', 'category', 'status', 'admin_note',
        ]));

        $business->save();

        return response()->json(['success' => true, 'message' => 'Business updated', 'data' => $business]);
    }
}
