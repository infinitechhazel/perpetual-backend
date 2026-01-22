<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\News;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class NewsController extends Controller
{
    /**
     * Display a listing of news
     */
    public function index(Request $request)
    {
        try {
            $query = News::with('author:id,name,email');

            // Filter by status
            if ($request->has('status')) {
                $query->where('status', $request->status);
            }

            // Filter by category
            if ($request->has('category')) {
                $query->byCategory($request->category);
            }

            // Search
            if ($request->has('search')) {
                $search = $request->search;
                $query->where(function($q) use ($search) {
                    $q->where('title', 'like', "%{$search}%")
                      ->orWhere('content', 'like', "%{$search}%");
                });
            }

            // Sort
            $sortBy = $request->get('sort_by', 'created_at');
            $sortOrder = $request->get('sort_order', 'desc');
            $query->orderBy($sortBy, $sortOrder);

            // Paginate
            $perPage = $request->get('per_page', 15);
            $news = $query->paginate($perPage);

            return response()->json([
                'success' => true,
                'data' => $news,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch news',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Store a newly created news
     */
    public function store(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'title' => 'required|string|max:255',
                'content' => 'required|string',
                'category' => 'required|string|max:100',
                'image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:10240', // 10MB
                'status' => 'nullable|in:draft,published,archived',
                'published_at' => 'nullable|date',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $data = $validator->validated();
            $data['author_id'] = auth()->id();

            // Handle image upload
            if ($request->hasFile('image')) {
                $image = $request->file('image');
                $imageName = time() . '_' . Str::random(10) . '.' . $image->getClientOriginalExtension();
                
                // Move to public/images/news directory
                $destinationPath = public_path('images/news');
                
                // Create directory if it doesn't exist
                if (!file_exists($destinationPath)) {
                    mkdir($destinationPath, 0755, true);
                }
                
                $image->move($destinationPath, $imageName);
                $data['image'] = 'images/news/' . $imageName;
            }

            // Set published_at if status is published
            if (isset($data['status']) && $data['status'] === 'published' && !isset($data['published_at'])) {
                $data['published_at'] = now();
            }

            $news = News::create($data);
            $news->load('author:id,name,email');

            return response()->json([
                'success' => true,
                'message' => 'News created successfully',
                'data' => $news,
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create news',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Display the specified news
     */
    public function show($id)
    {
        try {
            $news = News::with('author:id,name,email')->findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => $news,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'News not found',
            ], 404);
        }
    }

    /**
     * Update the specified news
     */
    public function update(Request $request, $id)
    {
        try {
            $news = News::findOrFail($id);

            $validator = Validator::make($request->all(), [
                'title' => 'sometimes|required|string|max:255',
                'content' => 'sometimes|required|string',
                'category' => 'sometimes|required|string|max:100',
                'image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:10240',
                'status' => 'nullable|in:draft,published,archived',
                'published_at' => 'nullable|date',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $data = $validator->validated();

            // Handle image upload
            if ($request->hasFile('image')) {
                // Delete old image
                if ($news->image && file_exists(public_path($news->image))) {
                    unlink(public_path($news->image));
                }

                $image = $request->file('image');
                $imageName = time() . '_' . Str::random(10) . '.' . $image->getClientOriginalExtension();
                
                // Move to public/images/news directory
                $destinationPath = public_path('images/news');
                
                // Create directory if it doesn't exist
                if (!file_exists($destinationPath)) {
                    mkdir($destinationPath, 0755, true);
                }
                
                $image->move($destinationPath, $imageName);
                $data['image'] = 'images/news/' . $imageName;
            }

            // Update published_at if changing to published status
            if (isset($data['status']) && $data['status'] === 'published' && !$news->published_at) {
                $data['published_at'] = $data['published_at'] ?? now();
            }

            $news->update($data);
            $news->load('author:id,name,email');

            return response()->json([
                'success' => true,
                'message' => 'News updated successfully',
                'data' => $news,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update news',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Remove the specified news
     */
    public function destroy($id)
    {
        try {
            $news = News::findOrFail($id);

            // Delete image if exists
            if ($news->image && file_exists(public_path($news->image))) {
                unlink(public_path($news->image));
            }

            $news->delete();

            return response()->json([
                'success' => true,
                'message' => 'News deleted successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete news',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get published news for public view
     */
    public function published(Request $request)
    {
        try {
            $query = News::published()->with('author:id,name');

            // Filter by category
            if ($request->has('category')) {
                $query->byCategory($request->category);
            }

            // Search
            if ($request->has('search')) {
                $search = $request->search;
                $query->where(function($q) use ($search) {
                    $q->where('title', 'like', "%{$search}%")
                      ->orWhere('content', 'like', "%{$search}%");
                });
            }

            $query->orderBy('published_at', 'desc');

            $perPage = $request->get('per_page', 12);
            $news = $query->paginate($perPage);

            return response()->json([
                'success' => true,
                'data' => $news,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch published news',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}