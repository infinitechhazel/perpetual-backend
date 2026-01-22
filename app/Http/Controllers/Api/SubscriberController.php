<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Subscriber;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;

class SubscriberController extends Controller
{
    /**
     * Subscribe to announcements
     */
    public function subscribe(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $email = strtolower(trim($request->email));

            // Check if already subscribed
            $subscriber = Subscriber::where('email', $email)->first();

            if ($subscriber) {
                if ($subscriber->is_active) {
                    return response()->json([
                        'success' => false,
                        'message' => 'This email is already subscribed.',
                    ], 409);
                } else {
                    // Resubscribe
                    $subscriber->resubscribe();
                    $subscriber->regenerateToken();

                    return response()->json([
                        'success' => true,
                        'message' => 'Successfully resubscribed! Please check your email to verify your subscription.',
                        'data' => [
                            'email' => $subscriber->email,
                            'token' => $subscriber->token,
                        ],
                    ]);
                }
            }

            // Create new subscriber
            $subscriber = Subscriber::create([
                'email' => $email,
                'is_verified' => false,
                'is_active' => true,
            ]);

            Log::info('New subscriber created', [
                'email' => $email,
                'id' => $subscriber->id,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Successfully subscribed! Please check your email to verify your subscription.',
                'data' => [
                    'email' => $subscriber->email,
                    'token' => $subscriber->token,
                ],
            ], 201);

        } catch (\Exception $e) {
            Log::error('Error subscribing', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to subscribe. Please try again later.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Verify email subscription
     */
    public function verify($token)
    {
        try {
            $subscriber = Subscriber::where('token', $token)->first();

            if (!$subscriber) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid verification token.',
                ], 404);
            }

            if ($subscriber->is_verified) {
                return response()->json([
                    'success' => true,
                    'message' => 'Email already verified.',
                ]);
            }

            $subscriber->markAsVerified();

            Log::info('Subscriber verified', [
                'email' => $subscriber->email,
                'id' => $subscriber->id,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Email verified successfully! You will now receive updates.',
            ]);

        } catch (\Exception $e) {
            Log::error('Error verifying subscriber', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to verify email.',
            ], 500);
        }
    }

    /**
     * Unsubscribe from announcements
     */
    public function unsubscribe($token)
    {
        try {
            $subscriber = Subscriber::where('token', $token)->first();

            if (!$subscriber) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid unsubscribe token.',
                ], 404);
            }

            if (!$subscriber->is_active) {
                return response()->json([
                    'success' => true,
                    'message' => 'Already unsubscribed.',
                ]);
            }

            $subscriber->unsubscribe();

            Log::info('Subscriber unsubscribed', [
                'email' => $subscriber->email,
                'id' => $subscriber->id,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Successfully unsubscribed from updates.',
            ]);

        } catch (\Exception $e) {
            Log::error('Error unsubscribing', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to unsubscribe.',
            ], 500);
        }
    }

    /**
     * Get all subscribers (Admin only)
     */
    public function index(Request $request)
    {
        try {
            $query = Subscriber::query();

            // Filter by status
            if ($request->has('is_active')) {
                $query->where('is_active', $request->boolean('is_active'));
            }

            if ($request->has('is_verified')) {
                $query->where('is_verified', $request->boolean('is_verified'));
            }

            // Search
            if ($request->has('search') && $request->search) {
                $query->where('email', 'like', '%' . $request->search . '%');
            }

            $perPage = $request->get('per_page', 15);
            $subscribers = $query->latest()->paginate($perPage);

            return response()->json([
                'success' => true,
                'data' => $subscribers,
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching subscribers', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch subscribers.',
            ], 500);
        }
    }

    /**
     * Get subscriber statistics (Admin only)
     */
    public function statistics()
    {
        try {
            $stats = [
                'total' => Subscriber::count(),
                'active' => Subscriber::active()->count(),
                'verified' => Subscriber::verified()->count(),
                'active_and_verified' => Subscriber::activeAndVerified()->count(),
                'unsubscribed' => Subscriber::where('is_active', false)->count(),
                'unverified' => Subscriber::where('is_verified', false)->count(),
            ];

            return response()->json([
                'success' => true,
                'data' => $stats,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch statistics.',
            ], 500);
        }
    }

    /**
     * Delete subscriber (Admin only)
     */
    public function destroy($id)
    {
        try {
            $subscriber = Subscriber::findOrFail($id);
            $email = $subscriber->email;
            
            $subscriber->delete();

            Log::info('Subscriber deleted', [
                'email' => $email,
                'id' => $id,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Subscriber deleted successfully.',
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete subscriber.',
            ], 500);
        }
    }

    /**
     * Get all active and verified subscribers for email notification
     */
    public function getActiveSubscribers()
    {
        try {
            $subscribers = Subscriber::activeAndVerified()
                ->select('email', 'token')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $subscribers,
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching active subscribers', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch subscribers.',
            ], 500);
        }
    }
}