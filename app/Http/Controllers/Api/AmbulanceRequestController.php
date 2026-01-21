<?php
   
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AmbulanceRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
class AmbulanceRequestController extends Controller
{
    public function index(Request $request)
    {
        try {
            // Fetch ALL ambulance requests for admin
            $query = AmbulanceRequest::with('user')
                ->orderBy('requested_at', 'desc');

            // Filter by status
            if ($request->has('status') && $request->status !== 'all') {
                $query->where('status', $request->status);
            }

            // Search functionality
            if ($request->has('search') && !empty($request->search)) {
                $search = $request->search;
                $query->where(function($q) use ($search) {
                    $q->where('request_id', 'like', "%{$search}%")
                      ->orWhere('name', 'like', "%{$search}%")
                      ->orWhere('phone', 'like', "%{$search}%")
                      ->orWhere('address', 'like', "%{$search}%");
                });
            }

            $perPage = $request->input('per_page', 15);
            $requests = $query->paginate($perPage);

            return response()->json([
                'success' => true,
                'data' => $requests
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to fetch ambulance requests', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch ambulance requests'
            ], 500);
        }
    }
 public function store(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255',
                'phone' => 'required|string|max:20',
                'address' => 'required|string',
                'emergency' => 'required|string|in:medical,accident,cardiac,breathing,injury,other',
                'notes' => 'nullable|string',
                'location.lat' => 'nullable|numeric|between:-90,90',
                'location.lng' => 'nullable|numeric|between:-180,180',
                'timestamp' => 'required|date',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $user = $request->user();

            // Calculate estimated arrival time (5-15 minutes based on priority)
            $priorityMinutes = match($request->emergency) {
                'cardiac', 'breathing' => 5,
                'medical', 'accident' => 10,
                default => 15
            };

            $estimatedArrival = now()->addMinutes($priorityMinutes)->format('g:i A');

            $ambulanceRequest = AmbulanceRequest::create([
                'user_id' => $user->id,
                'name' => $request->name,
                'phone' => $request->phone,
                'address' => $request->address,
                'emergency' => $request->emergency,
                'notes' => $request->notes,
                'latitude' => $request->input('location.lat'),
                'longitude' => $request->input('location.lng'),
                'requested_at' => $request->timestamp,
                'estimated_arrival' => $estimatedArrival,
                'status' => 'pending',
            ]);

            Log::info('Ambulance request created', [
                'request_id' => $ambulanceRequest->request_id,
                'user_id' => $user->id,
                'emergency_type' => $request->emergency
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Ambulance request submitted successfully',
                'data' => [
                    'requestId' => $ambulanceRequest->request_id,
                    'estimatedArrival' => $estimatedArrival,
                    'status' => $ambulanceRequest->status,
                ]
            ], 201);

        } catch (\Exception $e) {
            Log::error('Ambulance request failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to submit ambulance request',
            ], 500);
        }
    }

    public function show(Request $request, $id)
    {
        try {
            $ambulanceRequest = AmbulanceRequest::with('user')
                ->where('id', $id)
                ->firstOrFail();

            return response()->json([
                'success' => true,
                'data' => $ambulanceRequest
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to fetch ambulance request', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Ambulance request not found'
            ], 404);
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $ambulanceRequest = AmbulanceRequest::findOrFail($id);

            $validatedData = $request->validate([
                'status' => 'required|in:pending,dispatched,en_route,arrived,completed,cancelled'
            ]);

            // Update timestamps based on status
            $updateData = ['status' => $validatedData['status']];
            
            switch ($validatedData['status']) {
                case 'dispatched':
                    if (!$ambulanceRequest->dispatched_at) {
                        $updateData['dispatched_at'] = now();
                    }
                    break;
                case 'arrived':
                    if (!$ambulanceRequest->arrived_at) {
                        $updateData['arrived_at'] = now();
                    }
                    break;
                case 'completed':
                    if (!$ambulanceRequest->completed_at) {
                        $updateData['completed_at'] = now();
                    }
                    break;
            }

            $ambulanceRequest->update($updateData);

            Log::info('Ambulance request status updated', [
                'request_id' => $ambulanceRequest->request_id,
                'status' => $validatedData['status'],
                'admin_user_id' => $request->user()->id
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Ambulance request status updated successfully',
                'data' => $ambulanceRequest
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to update ambulance request', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to update ambulance request'
            ], 500);
        }
    }
}