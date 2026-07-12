<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Mail\PasswordResetMail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class PasswordResetController extends Controller
{
    public function requestReset(Request $request)
    {
        $request->validate([
            'email' => 'required|string|email',
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user) {
            return response()->json(['message' => 'User not found.'], 404);
        }

        // Generate secure token
        $resetToken = Str::random(20);
        $user->resetToken = $resetToken;
        $user->resetTokenExpiry = now()->addHour(); // Valid for 1 hour
        $user->save();

        $resetLink = "https://www.diksxcars.co.ke/reset-password?token=" . $resetToken;

        try {
            Mail::to($user->email)->send(new PasswordResetMail($resetLink));
        } catch (\Exception $e) {
            // Log the error but maybe return success or specific error, depending on configuration
        }

        return response()->json(['message' => 'Password reset email sent.']);
    }

    public function resetPassword(Request $request, $token)
    {
        $request->validate([
            'password' => 'required|string|min:6',
        ]);

        $user = User::where('resetToken', $token)->first();

        if (!$user) {
            return response()->json(['message' => 'Invalid token.'], 400);
        }

        if (!$user->resetTokenExpiry || now()->greaterThan($user->resetTokenExpiry)) {
            return response()->json(['message' => 'Token has expired.'], 400);
        }

        $user->password = Hash::make($request->password);
        $user->resetToken = null;
        $user->resetTokenExpiry = null;
        $user->save();

        return response()->json(['message' => 'Password reset successful. You can now log in with your new password.']);
    }
}
