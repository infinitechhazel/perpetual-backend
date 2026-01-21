<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class UserController extends Controller
{
    /**
     * Get authenticated user with JuanTap profile
     */
    public function me(Request $request)
    {
        $user = $request->user()->load('juantapProfile');

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'juantap_profile' => $user->juantapProfile
            ]
        ]);
    }
}