<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Hash;

class RegisterController extends Controller
{
    public function regEmp(Request $request)
    {
        $request->validate([
            'empno' => 'required|string',
            'password' => 'required|string|min:6',
            'confirm_password' => 'required|string|same:password',
        ]);

        $empno = $request->input('empno');
        $hashedPassword = Hash::make($request->input('password')); // Encrypt password

        // Prepare JSON payload matching the expected format
        $jsonData = json_encode([
            'json_data' => [
                'empNo' => $empno,
                'password' => $hashedPassword,
            ]
        ]);

        try {
            // Call the stored procedure with a single JSON parameter
            $result = DB::select('EXEC sproc_PHP_EmpRegister ?', [$jsonData]);

            return response()->json([
                'status' => $result[0]->status ?? 'Failed'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Registration failed',
                'message' => $e->getMessage()
            ], 500);
        }
    }

public function loginEmp(Request $request)
{
    $request->validate([
        'empno' => 'required|string',
        'password' => 'required|string',
    ]);

    $empno = $request->input('empno');
    $password = $request->input('password');

    $user = DB::table('paymast')
        ->select('empno', 'askapp_pw')
        ->where('empno', $empno)
        ->first();

    if (!$user) {
        return response()->json([
            'status' => 'failed',
            'message' => 'Employee not found',
        ], 404);
    }

    if (!Hash::check($password, $user->askapp_pw)) {
        return response()->json([
            'status' => 'failed',
            'message' => 'Invalid credentials',
        ], 401);
    }

    return response()->json([
        'status' => 'success',
        'data' => [
            'empno' => $user->empno
        ],
    ]);
}
}
