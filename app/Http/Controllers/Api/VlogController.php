<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Vlog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class VlogController extends Controller
{
    /** Public list */
    public function index()
    {
        $vlogs = Vlog::where('is_active', 1)->get();

        return response()->json([
            'success' => true,
            'data' => $vlogs,
        ]);
    }

    /** Admin-only list */
    public function adminIndex(Request $request)
    {
        $admin = Auth::user();
        if (! $admin || ! $admin->isAdmin()) {
            return response()->json(['success' => false, 'message' => 'Only admins can view vlogs.'], 403);
        }

        $perPage = $request->integer('per_page', 10);

        $vlogs = Vlog::query()
            ->search($request->search)
            ->when($request->category, fn ($q) => $q->where('category', $request->category))
            ->latest()
            ->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $vlogs,
        ]);
    }

    /** Chunked video upload */
    public function uploadChunk(Request $request, $vlogId = null)
    {
        $admin = $request->user();
        if (! $admin || ! $admin->isAdmin()) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        $request->validate([
            'chunk' => 'required|file',
            'chunk_index' => 'required|integer',
            'total_chunks' => 'required|integer',
            'filename' => 'required|string',
        ]);

        $chunkIndex = (int) $request->chunk_index;
        $totalChunks = (int) $request->total_chunks;
        $filename = $request->filename;

        $hash = md5($filename);
        $tmpDir = storage_path("app/tmp_videos/{$hash}");

        if (! is_dir($tmpDir)) {
            mkdir($tmpDir, 0755, true);
        }

        // Save chunk
        $request->file('chunk')->move($tmpDir, $chunkIndex);

        // Save metadata on first chunk
        if ($chunkIndex === 0) {
            $metaData = [
                'title' => $request->title,
                'category' => $request->category,
                'date' => $request->date,
                'content' => $request->content,
                'description' => $request->description,
                'is_active' => $request->is_active ?? 1,
            ];

            // Poster handling
            if ($request->hasFile('poster')) {
                $posterFile = $request->file('poster');
                $posterName = time().'_'.$posterFile->getClientOriginalName();
                $posterPath = public_path('vlogs/posters');
                if (! file_exists($posterPath)) {
                    mkdir($posterPath, 0777, true);
                }
                $posterFile->move($posterPath, $posterName);
                $metaData['poster'] = '/vlogs/posters/'.$posterName;
            }

            // Save metadata to tmp
            file_put_contents("{$tmpDir}/meta.json", json_encode($metaData));
        }

        // Not last chunk → return
        if ($chunkIndex + 1 < $totalChunks) {
            return response()->json(['success' => true, 'message' => 'Chunk uploaded']);
        }

        // Assemble final video
        $finalDir = public_path('vlogs/videos');
        if (! is_dir($finalDir)) {
            mkdir($finalDir, 0755, true);
        }

        $finalName = time().'_'.$filename;
        $finalPath = "{$finalDir}/{$finalName}";
        $out = fopen($finalPath, 'wb');

        for ($i = 0; $i < $totalChunks; $i++) {
            $in = fopen("{$tmpDir}/{$i}", 'rb');
            stream_copy_to_stream($in, $out);
            fclose($in);
            unlink("{$tmpDir}/{$i}");
        }

        fclose($out);

        // Load metadata
        $meta = json_decode(file_get_contents("{$tmpDir}/meta.json"), true);
        unlink("{$tmpDir}/meta.json");
        rmdir($tmpDir);

        // Create or update vlog
        if ($vlogId) {
            $vlog = Vlog::findOrFail($vlogId);
            $vlog->update([
                ...$meta,
                'video' => "/vlogs/videos/{$finalName}",
            ]);
        } else {
            $vlog = Vlog::create([
                ...$meta,
                'video' => "/vlogs/videos/{$finalName}",
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Vlog saved successfully',
            'data' => $vlog,
        ]);
    }

    /** Admin-only store */
    public function store(Request $request)
    {
        $admin = Auth::user();
        if (! $admin || ! $admin->isAdmin()) {
            return response()->json(['success' => false, 'message' => 'Only admins can create vlogs.'], 403);
        }

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'date' => 'required|date',
            'category' => 'required|string|max:100',
            'description' => 'nullable|string',
            'content' => 'required|string',
            'is_active' => 'boolean',
            'video' => 'nullable|string', // path returned from chunked upload
            'poster' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:10240',
        ]);

        if ($request->hasFile('poster')) {
            $posterFile = $request->file('poster');
            $posterName = time().'_'.$posterFile->getClientOriginalName();
            $posterPath = public_path('vlogs/posters');
            if (! file_exists($posterPath)) {
                mkdir($posterPath, 0777, true);
            }
            $posterFile->move($posterPath, $posterName);
            $validated['poster'] = '/vlogs/posters/'.$posterName;
        }

        $vlog = Vlog::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Vlog created successfully',
            'data' => $vlog,
        ], 201);
    }

    /** Admin-only update via chunked upload */
    public function update(Request $request, $id)
    {
        $admin = Auth::user();
        if (! $admin || ! $admin->isAdmin()) {
            return response()->json(['success' => false, 'message' => 'Only admins can update vlogs.'], 403);
        }

        $vlog = Vlog::findOrFail($id);

        // ------------------------
        // CHUNKED UPLOAD FLOW
        // ------------------------
        if ($request->hasFile('chunk')) {
            // Only validate chunk fields if a file exists
            $request->validate([
                'chunk' => 'required|file',
                'chunk_index' => 'required|integer',
                'total_chunks' => 'required|integer',
                'filename' => 'required|string',
            ]);

            $chunkIndex = (int) $request->chunk_index;
            $totalChunks = (int) $request->total_chunks;
            $filename = $request->filename;

            $hash = md5($filename);
            $tmpDir = storage_path("app/tmp_videos/{$hash}");
            if (! is_dir($tmpDir)) {
                mkdir($tmpDir, 0755, true);
            }

            // Save chunk
            $request->file('chunk')->move($tmpDir, $chunkIndex);

            // Save metadata on first chunk
            if ($chunkIndex === 0) {
                file_put_contents(
                    "{$tmpDir}/meta.json",
                    json_encode([
                        'title' => $request->title,
                        'category' => $request->category,
                        'date' => $request->date,
                        'content' => $request->content,
                        'description' => $request->description,
                        'is_active' => $request->is_active ?? 1,
                    ])
                );
            }

            // Not last chunk → return
            if ($chunkIndex + 1 < $totalChunks) {
                return response()->json(['success' => true, 'message' => 'Chunk uploaded']);
            }

            // Assemble final video
            $finalDir = public_path('vlogs/videos');
            if (! is_dir($finalDir)) {
                mkdir($finalDir, 0755, true);
            }
            $finalName = time().'_'.$filename;
            $finalPath = "{$finalDir}/{$finalName}";

            $out = fopen($finalPath, 'wb');
            for ($i = 0; $i < $totalChunks; $i++) {
                $in = fopen("{$tmpDir}/{$i}", 'rb');
                stream_copy_to_stream($in, $out);
                fclose($in);
                unlink("{$tmpDir}/{$i}");
            }
            fclose($out);

            $meta = json_decode(file_get_contents("{$tmpDir}/meta.json"), true);
            unlink("{$tmpDir}/meta.json");
            rmdir($tmpDir);

            // Delete old video if exists
            if ($vlog->video && file_exists(public_path($vlog->video))) {
                unlink(public_path($vlog->video));
            }

            // Update vlog with video + metadata
            $vlog->update([
                ...$meta,
                'video' => "/vlogs/videos/{$finalName}",
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Vlog updated successfully',
                'data' => $vlog->fresh(),
            ]);
        }

        // ------------------------
        // NORMAL METADATA UPDATE
        // ------------------------
        $validated = $request->validate([
            'title' => 'sometimes|string|max:255',
            'date' => 'sometimes|date',
            'category' => 'sometimes|string|max:100',
            'description' => 'nullable|string',
            'content' => 'sometimes|string',
            'is_active' => 'boolean',
            'video' => 'nullable|file|mimes:mp4,mov,avi,webm|max:102400',
            'poster' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:10240',
        ]);

        // Only update video if new file uploaded
        if ($request->hasFile('video')) {
            if ($vlog->video && file_exists(public_path($vlog->video))) {
                unlink(public_path($vlog->video));
            }

            $file = $request->file('video');
            $filename = time().'_'.$file->getClientOriginalName();
            $destination = public_path('vlogs/videos');
            if (! file_exists($destination)) {
                mkdir($destination, 0777, true);
            }

            $file->move($destination, $filename);
            $validated['video'] = '/vlogs/videos/'.$filename;
        }

        // Handle poster upload
        if ($request->hasFile('poster')) {
            // Delete old poster
            if ($vlog->poster && file_exists(public_path($vlog->poster))) {
                unlink(public_path($vlog->poster));
            }

            $posterFile = $request->file('poster');
            $posterName = time().'_'.$posterFile->getClientOriginalName();
            $posterPath = public_path('vlogs/posters');
            if (! file_exists($posterPath)) {
                mkdir($posterPath, 0777, true);
            }
            $posterFile->move($posterPath, $posterName);
            $validated['poster'] = '/vlogs/posters/'.$posterName;
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
            return response()->json(['success' => false, 'message' => 'Only admins can delete vlogs.'], 403);
        }

        // Find the vlog or fail
        $vlog = Vlog::findOrFail($id);
        if ($vlog->video && file_exists(public_path($vlog->video))) {
            unlink(public_path($vlog->video));
        }

        // Delete poster file if exists
        if ($vlog->poster && file_exists(public_path($vlog->poster))) {
            unlink(public_path($vlog->poster));
        }

        $vlog->delete();

        return response()->json([
            'success' => true,
            'message' => 'Vlog deleted successfully',
        ]);
    }
}
