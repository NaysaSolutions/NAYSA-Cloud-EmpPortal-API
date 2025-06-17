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

public function index(Request $request)
{
    $request->validate([
        'EMP_NO' => 'required|string'
    ]);

    $employeeNo = $request->input('EMP_NO');

    try {
        // Call stored procedure with only EMP_NO
        $results = DB::select('EXEC sproc_PHP_EmpInq_SummInfo @emp = ?', [$employeeNo]);

        if (empty($results) || empty($results[0]->result)) {
            return response()->json([
                'success' => false,
                'message' => 'No user data found.',
            ], 404);
        }

        $userData = json_decode($results[0]->result, true);

        if (empty($userData)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid or missing user data.',
            ], 404);
        }

        // No password decryption or check

        return response()->json([
            'success' => true,
            'data' => $userData,
        ], 200);

    } catch (\Exception $e) {
        Log::error('Employee lookup error', [
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);

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