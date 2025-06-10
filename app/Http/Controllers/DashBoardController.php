<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Hash;


class DashBoardController extends Controller
{
    

// public function index(Request $request) {

//     $request->validate([
//         'EMP_NO' => 'required|string',
//         'EMP_PASS' => 'required|string',
//     ]);

//     $employee_no = $request->input('EMP_NO');
//     $employee_pass = $request->input('EMP_PASS');


//     try {
//         $results = DB::select(
//             'EXEC sproc_PHP_EmpInq_SummInfo  @emp =?',
//             [$employee_no] 
//         );

//         return response()->json([
//             'success' => true,
//             'data' => $results,
//         ], 200);
//     } catch (\Exception $e) {
//         return response()->json([
//             'success' => false,
//             'message' => $e->getMessage(),
//         ], 500);
//     }
// }

public function index(Request $request) {

    $request->validate([
        'EMP_NO' => 'required|string',
        'EMP_PASS' => 'required|string',
    ]);

    $employee_no = $request->input('EMP_NO');
    $employee_pass = $request->input('EMP_PASS');

    try {
        $results = DB::select(
            'EXEC sproc_PHP_EmpInq_SummInfo @emp = ?',
            [$employee_no] 
        );

        if (empty($results)) {
            return response()->json([
                'success' => false,
                'message' => 'No user data found.',
            ], 404);
        }

        $userData = json_decode($results[0]->result ?? '[]', true);

        if (!empty($userData)) {
            $storedEncryptedPassword = $userData[0]['askapp_pw'];

            // 🔐 Decrypt the stored password (AES-256-CBC example)
            $key = env('LOGIN_ENCRYPTION_KEY'); // 32 bytes (256-bit)
            $iv = env('LOGIN_ENCRYPTION_IV');   // 16 bytes (128-bit)

            $decryptedPassword = openssl_decrypt(
                base64_decode($storedEncryptedPassword),
                'AES-256-CBC',
                $key,
                OPENSSL_RAW_DATA,
                $iv
            );

            if ($employee_pass === $decryptedPassword) {
                return response()->json([
                    'success' => true,
                    'data' => $results,
                ], 200);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Incorrect password.',
                ], 401);
            }
        } else {
            return response()->json([
                'success' => false,
                'message' => 'Employee not found.',
            ], 404);
        }
    } catch (\Exception $e) {
        Log::error('Login error: ' . $e->getMessage());

        return response()->json([
            'success' => false,
            'message' => 'Server error.',
        ], 500);
    }
}


public function getDTR(Request $request) {
    $request->validate([
        'EMP_NO' => 'required|string',
    ]);

    $employee_no = $request->input('EMP_NO');


    try {
        $results = DB::select(
            'EXEC sproc_PHP_getDTR  @emp =?',
            [$employee_no] 
        );

        return response()->json([
            'success' => true,
            'data' => $results,
        ], 200);
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => $e->getMessage(),
        ], 500);
    }
}


// public function register(Request $request)
// {
//     $request->validate([
//         'userId' => 'required|string|unique:users,user_id',
//         'email' => 'required|email|unique:users,email',
//         'password' => 'required|string|min:8',
//     ]);

//     $employee_no = $request->input('userId');
//     $employee_email = $request->input('email');
//     $employee_pass = $request->input('password');

//     try {
//         $user = DB::table('users')->insert([
//             'user_id' => $request->userId,
//             'email' => $request->email,
//             'password' => Hash::make($request->password), // ← This is important
//         ]);

//            try {
//         $results = DB::select(
//             'EXEC sproc_PHP_getDTR  @emp = ?, @email = ?, @pass =?, @mode =?',
//             [$employee_no, $employee_email,$employee_pass,'Register' ] 
//         );

//         return response()->json(['success' => true], 201);
//     } catch (\Exception $e) {
//         return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
//     }
// }

// }




}