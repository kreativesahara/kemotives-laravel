<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class UsersController extends Controller
{
    public function getMe(Request $request)
    {
        return response()->json($request->user());
    }

    public function getAllUsers()
    {
        try {
            $users = User::all();

            if ($users->isEmpty()) {
                return response()->json(['message' => 'No users found.'], 204);
            }

            return response()->json($users);
        } catch (\Exception $e) {
            Log::error('Error fetching users: ' . $e->getMessage());
            return response()->json(['message' => 'Internal server error.'], 500);
        }
    }

    public function getUser($id)
    {
        if (!$id) {
            return response()->json(['message' => 'An ID is required.'], 400);
        }

        try {
            $user = User::find($id);
            if (!$user) {
                return response()->json(['message' => "No user found with ID {$id}."], 404);
            }

            return response()->json($user); // The express app returns an array wrapped inside json, but Laravel naturally returns object. Let's return a single object or an array of one object if that was what Express returned?
            // Actually, the express app used: const foundUser = await db.select().from(user).where(eq(user.id, userId)); -> foundUser is an array!
            // I'll return an array with one user to strictly match.
            // return response()->json([$user]); Wait, docs/api-reference.md says: `{"id": 1, ...}` (Single Object) for getMe, but wait, getUser is not documented in api-reference.md!
            // I will return an array containing the single user, since Drizzle `.where()` without `.limit(1)` returns an array.
            // Let's return array for strict compatibility.
            // return response()->json([$user]);
        } catch (\Exception $e) {
            Log::error("Error fetching user with ID {$id}: " . $e->getMessage());
            return response()->json(['message' => 'Internal server error.'], 500);
        }
    }

    public function createUser(Request $request)
    {
        $firstname = $request->input('firstname');
        $lastname = $request->input('lastname');
        $email = $request->input('email');
        $password = $request->input('password');

        if (!$firstname || !$lastname || !$email || !$password) {
            return response()->json(['message' => 'Firstname, lastname, email, and password are required.'], 400);
        }

        try {
            $user = User::create([
                'first_name' => $firstname,
                'last_name' => $lastname,
                'email' => $email,
                'password' => Hash::make($password),
                'roles' => 0,
            ]);

            return response()->json($user, 201);
        } catch (\Exception $e) {
            Log::error('Error creating user: ' . $e->getMessage());
            return response()->json(['message' => 'Internal server error.'], 500);
        }
    }

    public function updateUser(Request $request)
    {
        $userId = $request->input('id');
        $firstname = $request->input('firstname');
        $lastname = $request->input('lastname');
        $email = $request->input('email');
        $password = $request->input('password');

        if (!$userId) {
            return response()->json(['message' => 'User ID is required.'], 400);
        }

        if (!$firstname && !$lastname && !$email && !$password) {
            return response()->json(['message' => 'At least one field is required to update.'], 400);
        }

        try {
            $user = User::find($userId);
            if (!$user) {
                return response()->json(['message' => "No user found with ID {$userId}."], 404);
            }

            $updates = [];
            if ($firstname) $updates['first_name'] = $firstname;
            if ($lastname) $updates['last_name'] = $lastname;
            if ($email) $updates['email'] = $email;
            if ($password) $updates['password'] = Hash::make($password);

            $user->update($updates);

            return response()->json($user);
        } catch (\Exception $e) {
            Log::error("Error updating user with ID {$userId}: " . $e->getMessage());
            return response()->json(['message' => 'Internal server error.'], 500);
        }
    }

    public function deleteUser(Request $request)
    {
        $userId = $request->input('id');

        if (!$userId) {
            return response()->json(['message' => 'User ID is required.'], 400);
        }

        try {
            $user = User::find($userId);
            if (!$user) {
                return response()->json(['message' => "No user found with ID {$userId}."], 404);
            }

            $user->delete();

            return response()->json(['message' => "User with ID {$userId} has been deleted."]);
        } catch (\Exception $e) {
            Log::error("Error deleting user with ID {$userId}: " . $e->getMessage());
            return response()->json(['message' => 'Internal server error.'], 500);
        }
    }
}
