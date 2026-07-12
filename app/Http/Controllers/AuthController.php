<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Hash;
use App\Models\UsersDB; // Ensure you're using UsersDB model
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Mail;
use App\Mail\ResetPasswordMail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        // Validate the 'apiSecret' field in the request
        $validator = Validator::make($request->all(), [
            'apiSecret' => 'required',
        ]);

        // Return validation errors if any
        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 422);
        }

        // Get the provided secret key and compare with the stored secret key
        $providedKey = $request->input('apiSecret');
        $storedKey = env('SECRET_KEY'); // Make sure this matches your .env setup

        if ($providedKey === $storedKey) {
            return response()->json([
                'message' => 'Authentication successful',
            ], 200);
        } else {
            return response()->json(['error' => 'Invalid secret key'], 401);
        }
    }

    public function register(Request $request)
{
    // Validate request
    $request->validate([
        'userId' => 'required|string|max:10|unique:users,userId',
        'username' => 'required|string|unique:users,username|max:255',
        'email' => 'required|string|email|unique:users,email|max:255',
        'password' => 'required|string|min:8',
    ]);

    // Set the connection dynamically for the UsersDB model
    $user = UsersDB::on('API')->create([
        'userId' => $request->userId,
        'username' => $request->username,
        'email' => $request->email,
        'password' => Hash::make($request->password),
    ]);

    return response()->json([
        'message' => 'User registered successfully!',
        'user' => [
            'id' => $user->id,
            'userId' => $user->userId,
            'username' => $user->username,
            'email' => $user->email,
        ],
    ], 201);
}

    public function loginDB(Request $request) 
{
    $request->validate([
        'userId' => 'required|string',
        'password' => 'required',
    ]);

    $user = UsersDB::on(env('DB_CONNECTION'))
        ->where('userId', $request->userId)
        ->first();

    if (!$user) {
        return response()->json([
            'status' => 'error',
            'message' => 'User does not exist.',
        ], 404);
    }

    if (!Hash::check($request->password, $user->password)) {
        return response()->json([
            'status' => 'error',
            'message' => 'Invalid credentials.',
        ], 401);
    }

    $employee = DB::table('paymast')
        ->select(
            'empno',
            'emp_name',
            DB::raw("ISNULL(hr_flag, 'N') as hr_flag")
        )
        ->where('empno', $user->userId)
        ->first();

    return response()->json([
        'status' => 'success',
        'success' => true,
        'message' => 'Login successful.',
        'user' => [
            'id' => $user->id,
            'userId' => $user->userId,
            'userid' => $user->userId,
            'empno' => $employee->empno ?? $user->userId,
            'EMP_NO' => $employee->empno ?? $user->userId,
            'username' => $user->username,
            'email' => $user->email,
            'empName' => $employee->emp_name ?? $user->username,
            'hr_flag' => $employee->hr_flag ?? 'N',
            'hrFlag' => $employee->hr_flag ?? 'N',
        ],
    ]);
}

    public function forgotPassword(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
        ]);

        // Use UsersDB model instead of User
         $user = UsersDB::on('API')->where('email', $request->email)->first();

        if (!$user) {
            return response()->json([
                'status' => 'error',
                'message' => 'User with this email does not exist.',
            ], 404);
        }

        $resetCode = Str::random(40); 

        $user->reset_code = $resetCode;
        $user->save();

        $resetLink = url("/reset-password?code={$resetCode}");

        Mail::to($user->email)->send(new ResetPasswordMail($user, $resetLink));

        return response()->json([
            'status' => 'success',
            'message' => 'Password reset link has been sent to your email.',
        ]);
    }

    public function resetPassword(Request $request)
    {
        $validated = $request->validate([
            'email' => 'required|email|exists:users,email',
            'password' => 'required|confirmed|min:8',
        ]);

        // Use UsersDB model instead of User
        $user = UsersDB::where('email', $request->email)->first();
        if ($user) {
            $user->password = bcrypt($request->password);
            $user->save();

            return response()->json([
                'status' => 'success',
                'message' => 'Password reset successfully!',
            ]);
        }

        return response()->json([
            'status' => 'error',
            'message' => 'User not found.',
        ]);
    }


public function me(Request $request)
{
    $userId = $request->input('userId')
        ?? $request->input('userid')
        ?? $request->input('empno')
        ?? $request->query('userId')
        ?? $request->query('userid')
        ?? $request->query('empno');

    if (!$userId) {
        return response()->json([
            'success' => false,
            'status' => 'error',
            'message' => 'User ID is required.',
        ], 422);
    }

    $user = UsersDB::on(env('DB_CONNECTION'))
        ->where('userId', $userId)
        ->first();

    if (!$user) {
        return response()->json([
            'success' => false,
            'status' => 'error',
            'message' => 'User not found.',
        ], 404);
    }

    $employee = DB::table('paymast')
        ->select(
            'empno',
            'emp_name',
            DB::raw("ISNULL(hr_flag, 'N') as hr_flag")
        )
        ->where('empno', $user->userId)
        ->first();

    return response()->json([
        'success' => true,
        'status' => 'success',
        'user' => [
            'id' => $user->id,
            'userId' => $user->userId,
            'userid' => $user->userId,
            'empno' => $employee->empno ?? $user->userId,
            'EMP_NO' => $employee->empno ?? $user->userId,
            'username' => $user->username,
            'email' => $user->email,
            'empName' => $employee->emp_name ?? $user->username,
            'hr_flag' => $employee->hr_flag ?? 'N',
            'hrFlag' => $employee->hr_flag ?? 'N',
        ],
    ]);
}

}
