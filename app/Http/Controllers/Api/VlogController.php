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
        // $admin = $request->user();
        // if (! $admin || ! $admin->isAdmin()) {
        //     return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        // }

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

        // ✅ Save chunk
        $request->file('chunk')->move($tmpDir, $chunkIndex);

        // ✅ SAVE METADATA ON FIRST CHUNK
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

        // ⏳ Not last chunk → done
        if ($chunkIndex + 1 < $totalChunks) {
            return response()->json(['success' => true, 'message' => 'Chunk uploaded']);
        }

        // ==========================
        // ✅ FINAL CHUNK — ASSEMBLE
        // ==========================

        $finalDir = public_path('vlogs/videos');
        if (! is_dir($finalDir)) {
            mkdir($finalDir, 0755, true);
        }

        $finalName = time().'_'.$filename;
        $finalPath = "{$finalDir}/{$finalName}";

        $out = fopen($finalPath, 'wb');

        for ($i = 0; $i < $totalChunks; $i++) {
            $chunkFile = "{$tmpDir}/{$i}";
            $in = fopen($chunkFile, 'rb');
            stream_copy_to_stream($in, $out);
            fclose($in);
            unlink($chunkFile);
        }

        fclose($out);

        // ✅ LOAD METADATA
        $meta = json_decode(file_get_contents("{$tmpDir}/meta.json"), true);
        unlink("{$tmpDir}/meta.json");
        rmdir($tmpDir);

        // ==========================
        // ✅ CREATE OR UPDATE VLOG
        // ==========================

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
        ]);

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
        // $admin = Auth::user();
        // if (! $admin || ! $admin->isAdmin()) {
        //     return response()->json(['success' => false, 'message' => 'Only admins can update vlogs.'], 403);
        // }

        $vlog = Vlog::findOrFail($id);

        $validated = $request->validate([
            'title' => 'sometimes|string|max:255',
            'date' => 'sometimes|date',
            'category' => 'sometimes|string|max:100',
            'description' => 'nullable|string',
            'content' => 'sometimes|string',
            'is_active' => 'boolean',
            'video' => 'nullable|file|mimes:mp4,mov,avi,webm|max:102400',
        ]);

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
        // $admin = Auth::user();
        // if (! $admin || ! $admin->isAdmin()) {
        //     return response()->json(['success' => false, 'message' => 'Only admins can delete vlogs.'], 403);
        // }

        $vlog = Vlog::findOrFail($id);
        // if ($vlog->video && file_exists(public_path($vlog->video))) {
        //     unlink(public_path($vlog->video));
        // }

        $vlog->delete();

        return response()->json(['success' => true, 'message' => 'Vlog deleted successfully']);
    }
}
