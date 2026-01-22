<?php

namespace App\Http\Controllers\Api;

use App\Models\OfficeContact;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use App\Http\Controllers\Controller;

class OfficeContactController extends Controller
{
    /**
     * Display the office contact data (Public)
     */
    public function index()
    {
        try {
            $officeContact = OfficeContact::first();

            if (!$officeContact) {
                return response()->json([
                    'success' => false,
                    'message' => 'No office contact data found',
                    'data' => null
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $officeContact,
                'message' => 'Office contact data retrieved successfully'
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error fetching office contact data: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch office contact data',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the office contact data for admin
     */
    public function show()
    {
        try {
            $officeContact = OfficeContact::first();

            if (!$officeContact) {
                return response()->json([
                    'success' => true,
                    'data' => null,
                    'message' => 'No office contact data found'
                ], 200);
            }

            return response()->json([
                'success' => true,
                'data' => $officeContact,
                'message' => 'Office contact data retrieved successfully'
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error fetching office contact data: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch office contact data',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Store or update office contact data
     */
    public function store(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'office_location' => 'required|string',
                'phone_number' => 'required|string|max:255',
                'email' => 'required|email|max:255',
                'office_hours' => 'required|string',
                'map_link' => 'nullable|url',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            DB::beginTransaction();

            $data = [
                'office_location' => $request->office_location,
                'phone_number' => $request->phone_number,
                'email' => $request->email,
                'office_hours' => $request->office_hours,
                'map_link' => $request->map_link,
            ];

            $existingRecord = OfficeContact::first();

            if (!$existingRecord) {
                $officeContact = OfficeContact::create($data);
                $message = 'Office contact data created successfully';
            } else {
                $existingRecord->update($data);
                $officeContact = $existingRecord->fresh();
                $message = 'Office contact data updated successfully';
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => $message,
                'data' => $officeContact
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Error storing office contact', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to store office contact data',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update the office contact data in storage
     */
    public function update(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'office_location' => 'required|string',
            'phone_number' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'office_hours' => 'required|string',
            'map_link' => 'nullable|url',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            DB::beginTransaction();

            $data = [
                'office_location' => $request->office_location,
                'phone_number' => $request->phone_number,
                'email' => $request->email,
                'office_hours' => $request->office_hours,
                'map_link' => $request->map_link,
            ];

            $officeContact = OfficeContact::first();

            if (!$officeContact) {
                $officeContact = OfficeContact::create($data);
                $message = 'Office contact created successfully';
            } else {
                $officeContact->update($data);
                $message = 'Office contact updated successfully';
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => $message,
                'data' => $officeContact
            ]);

        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Office contact update error', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Update failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the office contact data from storage
     */
    public function destroy()
    {
        try {
            $officeContact = OfficeContact::first();

            if (!$officeContact) {
                return response()->json([
                    'success' => false,
                    'message' => 'No office contact data found'
                ], 404);
            }

            DB::beginTransaction();

            $officeContact->update([
                'office_location' => null,
                'phone_number' => null,
                'email' => null,
                'office_hours' => null,
                'map_link' => null,
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Office contact data cleared successfully'
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error clearing office contact data: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to clear office contact data',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}