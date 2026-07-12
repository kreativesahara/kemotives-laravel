<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        $request->validate([
            'firstname' => 'required|string|max:255',
            'lastname' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:6',
        ]);

        $user = clone new User();
        $user->firstname = $request->firstname;
        $user->lastname = $request->lastname;
        $user->email = $request->email;
        $user->password = Hash::make($request->password);
        $user->roles = 1; // ROLES_LIST.Visitor
        $user->save();

        return response()->json([
            'message' => 'User registered successfully.',
            'user' => [
                'firstname' => $user->firstname,
                'lastname' => $user->lastname,
                'email' => $user->email,
                'roles' => $user->roles,
            ]
        ], 201);
    }

    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|string|email',
            'password' => 'required|string',
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json(['message' => 'Invalid email or password.'], 406);
        }

        // Generate Access Token (Sanctum PAT expiring in 15 minutes)
        $user->tokens()->delete(); // Clear old tokens
        $accessToken = $user->createToken('access_token', ['*'], now()->addMinutes(15))->plainTextToken;

        // Generate Refresh Token string (custom random string saved to DB)
        $refreshToken = Str::random(60);
        $user->refreshToken = $refreshToken;
        $user->save();

        return response()->json([
            'accessToken' => $accessToken,
            'roles' => $user->roles,
            'userId' => $user->id,
        ])
        ->header('authorization', $accessToken)
        ->cookie('refreshToken', $refreshToken, 60 * 24, null, null, true, true, false, 'none'); 
        // 60*24 mins = 1 day maxAge, secure=true, httpOnly=true, sameSite=none
    }

    public function refresh(Request $request)
    {
        $refreshToken = $request->cookie('refreshToken');

        if (!$refreshToken) {
            return response()->json(['error' => 'Refresh token required'], 401);
        }

        $user = User::where('refreshToken', $refreshToken)->first();

        if (!$user) {
            return response()->json(['message' => 'User not found or token invalid.'], 403);
        }

        // Generate new Access Token
        $user->tokens()->delete();
        $accessToken = $user->createToken('access_token', ['*'], now()->addMinutes(15))->plainTextToken;

        return response()->json([
            'id' => $user->id,
            'firstname' => $user->firstname,
            'lastname' => $user->lastname,
            'email' => $user->email,
            'roles' => $user->roles,
            'accessToken' => $accessToken,
        ])->header('authorization', $accessToken);
    }

    public function logout(Request $request)
    {
        $refreshToken = $request->cookie('refreshToken');

        if (!$refreshToken) {
            return response()->noContent();
        }

        $user = User::where('refreshToken', $refreshToken)->first();

        if ($user) {
            $user->tokens()->delete();
            $user->refreshToken = null;
            $user->save();
        }

        return response()->noContent()
            ->withoutCookie('refreshToken')
            ->withoutCookie('authorization'); // Although authorization is typically a header, express cleared it as cookie
    }
}
