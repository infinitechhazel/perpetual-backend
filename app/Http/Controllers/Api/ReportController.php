<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Report;
use App\Models\ReportFile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class ReportController extends Controller
{
    public function submit(Request $request)
    {
        // Validate the request
        $validator = Validator::make($request->all(), [
            'category' => 'required|in:road,streetlight,garbage,drainage,traffic,vandalism,noise,other',
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'location' => 'required|string|max:255',
            'urgency' => 'required|in:low,medium,high',
            'timestamp' => 'required|date',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            DB::beginTransaction();

            // Create the report
            $report = Report::create([
                'user_id' => $request->user()->id,
                'category' => $request->category,
                'title' => $request->title,
                'description' => $request->description,
                'location' => $request->location,
                'urgency' => $request->urgency,
                'status' => 'pending',
                'timestamp' => $request->timestamp,
            ]);

            // Handle file uploads
            $uploadedFiles = [];
            foreach ($request->allFiles() as $key => $file) {
                if (strpos($key, 'file_') === 0) {
                    // Validate file
                    $validator = Validator::make(
                        [$key => $file],
                        [$key => 'required|file|max:10240|mimes:jpg,jpeg,png,gif,mp4,mov,avi']
                    );

                    if ($validator->fails()) {
                        continue; // Skip invalid files
                    }

                    // Determine file type
                    $mimeType = $file->getMimeType();
                    $type = str_starts_with($mimeType, 'image/') ? 'image' : 'video';

                    // Store the file
                    $path = $file->store('reports/' . $report->id, 'public');

                    // Create file record
                    $reportFile = ReportFile::create([
                        'report_id' => $report->id,
                        'name' => $file->getClientOriginalName(),
                        'path' => $path,
                        'type' => $type,
                        'size' => $file->getSize(),
                    ]);

                    $uploadedFiles[] = [
                        'id' => $reportFile->id,
                        'name' => $reportFile->name,
                        'url' => $reportFile->url,
                    ];
                }
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Report submitted successfully',
                'data' => [
                    'report_id' => $report->report_id,
                    'id' => $report->id,
                    'status' => $report->status,
                    'files' => $uploadedFiles,
                ]
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to submit report: ' . $e->getMessage()
            ], 500);
        }
    }

    public function index(Request $request)
    {
        $reports = Report::with(['files', 'user'])
            ->where('user_id', $request->user()->id)
            ->orderBy('created_at', 'desc')
            ->paginate(10);

        return response()->json([
            'success' => true,
            'data' => $reports
        ]);
    }

    public function show(Request $request, $id)
    {
        $report = Report::with(['files', 'user'])
            ->where('id', $id)
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        return response()->json([
            'success' => true,
            'data' => $report
        ]);
    }
}