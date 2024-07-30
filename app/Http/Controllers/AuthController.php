<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Laravel\Passport\HasApiTokens;
use Kreait\Firebase\Factory;
use Illuminate\Support\Str;
use Laravel\Passport\PersonalAccessToken;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed',
            'phone_number' => 'required|string|max:15|unique:users',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 400);
        }

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'phone_number' => $request->phone_number,
        ]);

        $token = $user->createToken('LaravelAuthApp')->accessToken;

        return response()->json(['token' => $token], 200);
    }

    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|string|email',
            'password' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 400);
        }
        $credentials = $request->only('email', 'password');
        if (!Auth::attempt($credentials)) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }
        if (Auth::attempt(['email' => $request->email, 'password' => $request->password])) {
            $user = Auth::user();
            // dd($user->createToken('LaravelAuthApp')->accessToken);
            $token = $user->createToken('LaravelAuthApp')->plainTextToken;

            // return response()->json(['token' => $token], 200);
            
        return response()->json([
            'code' => 200,
            'status' => "Successful",
            'message' => "Login successful.",
            'total_results' => 1,
            'token' => $token,
            'data' => Auth::user()
        ]);
        } else {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
    }

    public function validate2FA(Request $request)
    {
        $firebase = (new Factory)->createAuth();
        $idTokenString = $request->idToken;

        try {
            $verifiedIdToken = $firebase->verifyIdToken($idTokenString);
            return response()->json(['status' => '2FA validated'], 200);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['error' => 'Invalid token'], 400);
        } catch (InvalidToken $e) {
            return response()->json(['error' => 'Invalid token'], 400);
        }
    }

    public function logout(Request $request)
    {
        $user = Auth::user();
        $user->tokens()->delete(); // Delete all tokens

        return response()->json(['message' => 'Successfully logged out']);
    }

    protected function respondWithToken($token)
    {
        return response()->json([
            'access_token' => $token,
            'token_type' => 'bearer',
            'user' => auth()->user(),
        ]);
    }
}
