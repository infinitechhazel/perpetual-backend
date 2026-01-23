<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Gallery;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class GalleryController extends Controller
{
    private $uploadFolder = 'gallery/images';

    /**
     * List all galleries
     */
    public function index()
    {
        $galleries = Gallery::all()->map(function ($g) {
            return $this->appendImageUrl($g);
        });

        return response()->json($galleries);
    }

    /**
     * Store a new gallery
     */
    public function store(Request $request)
    {
        $admin = Auth::user();
        if (! $admin || ! $admin->isAdmin()) {
            return response()->json(['success' => false, 'message' => 'Only admins can add galleries.'], 403);
        }

        $request->validate([
            'title' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'image' => 'required|image|max:12288', // 12MB max
        ]);

        $imagePath = $this->saveImage($request->file('image'));

        $gallery = Gallery::create([
            'title' => $request->title,
            'description' => $request->description,
            'image_path' => $imagePath,
        ]);

        return response()->json($this->appendImageUrl($gallery), 201);
    }

    public function update(Request $request, $id)
    {
        $admin = Auth::user();
        if (! $admin || ! $admin->isAdmin()) {
            return response()->json(['success' => false, 'message' => 'Only admins can update galleries.'], 403);
        }

        $gallery = Gallery::findOrFail($id);

        $request->validate([
            'title' => 'sometimes|nullable|string|max:255',
            'description' => 'nullable|string',
            'image' => 'nullable|image|max:12288', // 12MB max
        ]);

        if ($request->hasFile('image')) {
            $this->deleteImage($gallery->image_path);
            $gallery->image_path = $this->saveImage($request->file('image'));
        }

        $gallery->update([
            'title' => $request->title ?? $gallery->title,
            'description' => $request->description ?? $gallery->description,
        ]);

        return response()->json($this->appendImageUrl($gallery));
    }

    public function destroy($id)
    {
        $admin = Auth::user();
        if (! $admin || ! $admin->isAdmin()) {
            return response()->json(['success' => false, 'message' => 'Only admins can delete galleries.'], 403);
        }

        $gallery = Gallery::findOrFail($id);

        // Delete image
        $path = public_path($gallery->image_path);
        if (file_exists($path)) {
            unlink($path);
        }

        $gallery->delete();

        return response()->json(['message' => 'Gallery deleted']);
    }

    /**
     * Save uploaded image to public/gallery/images
     */
    private function saveImage($file)
    {
        $folderPath = public_path($this->uploadFolder);

        if (! is_dir($folderPath)) {
            mkdir($folderPath, 0755, true);
        }

        $filename = time().'_'.preg_replace('/\s+/', '_', $file->getClientOriginalName());
        $file->move($folderPath, $filename);

        return $this->uploadFolder.'/'.$filename;
    }

    /**
     * Delete an image file safely
     */
    private function deleteImage($path)
    {
        $fullPath = public_path($path);
        if ($path && file_exists($fullPath)) {
            unlink($fullPath);
        }
    }

    /**
     * Append full URL for the frontend
     */
    private function appendImageUrl(Gallery $gallery)
    {
        return [
            'id' => $gallery->id,
            'title' => $gallery->title,
            'description' => $gallery->description,
            'image_url' => url($gallery->image_path), // full URL
            'created_at' => $gallery->created_at,
        ];
    }
}
