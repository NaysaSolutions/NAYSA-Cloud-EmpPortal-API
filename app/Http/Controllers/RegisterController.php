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
        try {

             Log::info('Register Request', $request->all());  // <-- This line logs the request data


            // Use the correct field name for empNo
            $empNo = $request->input('empNo');  // <-- this should match the 'empno' column in paymast
            $email = $request->input('email');
            $password = $request->input('password');

            // Check if any required field is missing
            if (!$empNo || !$email || !$password) {
                return response()->json(['status' => 'error', 'message' => 'Missing required fields'], 400);
            }

            // Hash the password
            $hashedPassword = Hash::make($password);

            // Call the stored procedure with correct parameters
            DB::statement("EXEC sproc_PHP_EmpInq_SummInfo @emp = ?, @email = ?, @pass = ?, @mode = ?", [
                $empNo,
                $email,
                $hashedPassword,
                'Register'
            ]);

            return response()->json(['status' => 'success', 'message' => 'User registered successfully'], 201);

        } catch (\Exception $e) {
            Log::error('Register Error: ' . $e->getMessage());
            return response()->json(['status' => 'error', 'message' => 'Registration failed'], 500);
        }
    }
}
