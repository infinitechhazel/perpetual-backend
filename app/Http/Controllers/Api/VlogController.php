<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Vlog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class VlogController extends Controller
{
    public function index(Request $request)
    {
        $perPage = $request->integer('per_page', 10);

        $vlogs = Vlog::query()
            ->where('is_active', true)
            ->search($request->search)
            ->when($request->category, fn ($q) => $q->where('category', $request->category)
            )
            ->latest('date')
            ->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $vlogs,
        ]);
    }

    /** List vlogs */
    public function adminIndex(Request $request)
    {
        $admin = Auth::user();

        if (! $admin || ! $admin->isAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Only admins can view vlogs.',
            ], 403);
        }

        $perPage = $request->integer('per_page', 10);

        $vlogs = Vlog::query()
            ->search($request->search)
            ->when($request->category, fn ($q) => $q->where('category', $request->category)
            )
            ->latest()
            ->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $vlogs,
        ]);
    }

    /** Admin-only store */
    public function store(Request $request)
    {
        $admin = Auth::user();
        if (! $admin || ! $admin->isAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Only admins can create vlogs.',
            ], 403);
        }

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'date' => 'required|date',
            'category' => 'required|string|max:100',
            'description' => 'nullable|string',
            'content' => 'required|string',
            'is_active' => 'boolean',
            'video' => 'nullable|file|mimes:mp4,mov,avi,webm|max:51200',
        ]);

        if ($request->hasFile('video')) {
            $validated['video'] = $request->file('video')
                ->store('vlogs/videos', 'public');
        }

        $vlog = Vlog::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Vlog created successfully',
            'data' => $vlog,
        ], 201);
    }

    /** Admin-only update */
    public function update(Request $request, $id)
    {
        $admin = Auth::user();
        if (! $admin || ! $admin->isAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Only admins can update vlogs.',
            ], 403);
        }

        $vlog = Vlog::findOrFail($id);

        $validated = $request->validate([
            'title' => 'sometimes|string|max:255',
            'date' => 'sometimes|date',
            'category' => 'sometimes|string|max:100',
            'description' => 'nullable|string',
            'content' => 'sometimes|string',
            'is_active' => 'boolean',
            'video' => 'nullable|file|mimes:mp4,mov,avi,webm|max:51200', // 50 MB
        ]);

        if ($request->hasFile('video')) {
            if ($vlog->video) {
                Storage::disk('public')->delete($vlog->video);
            }

            $validated['video'] = $request->file('video')
                ->store('vlogs/videos', 'public');
        }

        $vlog->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Vlog updated successfully',
            'data' => $vlog->fresh(),
        ]);
    }

    /** Admin-only delete */
    public function destroy($id)
    {
        $admin = Auth::user();
        if (! $admin || ! $admin->isAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Only admins can delete vlogs.',
            ], 403);
        }

        $vlog = Vlog::findOrFail($id);

        if ($vlog->video) {
            Storage::disk('public')->delete($vlog->video);
        }

        $vlog->delete();

        return response()->json([
            'success' => true,
            'message' => 'Vlog deleted successfully',
        ]);
    }
}
