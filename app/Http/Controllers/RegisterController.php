<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Hash;
use App\Models\User; // RIGHT: This points to your database model

class RegisterController extends Controller
{
    // public function regEmp(Request $request)
    // {
    //     $request->validate([
    //         'empno' => 'required|string',
    //         'password' => 'required|string|min:6',
    //         'confirm_password' => 'required|string|same:password',
    //     ]);

    //     $empno = $request->input('empno');
    //     $hashedPassword = Hash::make($request->input('password')); // Encrypt password

    //     // Prepare JSON payload matching the expected format
    //     $jsonData = json_encode([
    //         'json_data' => [
    //             'empNo' => $empno,
    //             'password' => $hashedPassword,
    //         ]
    //     ]);

    //     try {
    //         // Call the stored procedure with a single JSON parameter
    //         $result = DB::select('EXEC sproc_PHP_EmpRegister ?', [$jsonData]);

    //         return response()->json([
    //             'status' => $result[0]->status ?? 'Failed'
    //         ]);
    //     } catch (\Exception $e) {
    //         return response()->json([
    //             'error' => 'Registration failed',
    //             'message' => $e->getMessage()
    //         ], 500);
    //     }
    // }

// public function loginEmp(Request $request)
// {
//     $request->validate([
//         'empno' => 'required|string',
//         'password' => 'required|string',
//     ]);

//     $empno = $request->input('empno');
//     $password = $request->input('password');

//     $user = DB::table('paymast')
//         ->select('empno', 'askapp_pw')
//         ->where('empno', $empno)
//         ->first();

//     if (!$user) {
//         return response()->json([
//             'status' => 'failed',
//             'message' => 'Employee not found',
//         ], 404);
//     }

//     if (!Hash::check($password, $user->askapp_pw)) {
//         return response()->json([
//             'status' => 'failed',
//             'message' => 'Invalid credentials',
//         ], 401);
//     }

//     return response()->json([
//         'status' => 'success',
//         'data' => [
//             'empno' => $user->empno
//         ],
//     ]);
// }

// app/Http/Controllers/RegisterController.php

// public function loginEmp(Request $request)
// {
//     $request->validate([
//         'empno' => 'required|string',
//         'password' => 'required|string',
//     ]);

//     // 1. Find the user in your 'paymast' table
//     // Note: To use createToken(), your User model must use the HasApiTokens trait
//     $user = \App\Models\User::where('empno', $request->empno)->first();

//     if (!$user) {
//         return response()->json(['status' => 'failed', 'message' => 'Employee not found'], 404);
//     }

//     // 2. Verify password
//     if (!Hash::check($request->password, $user->askapp_pw)) {
//         return response()->json(['status' => 'failed', 'message' => 'Invalid credentials'], 401);
//     }

//     // 3. GENERATE THE TOKEN (This is what was missing)
//     $token = $user->createToken('auth_token')->plainTextToken;

//     return response()->json([
//         'status' => 'success',
//         'token' => $token, // Send this back to React
//         'data' => [
//             'empno' => $user->empno,
//             'empname' => $user->emp_name // Assuming this column exists in paymast
//         ],
//     ]);
// }

// public function loginEmp(Request $request)
// {
//     $request->validate([
//         'empno' => 'required|string',
//         'password' => 'required|string',
//     ]);

//     // Use the User Model to find the employee in 'paymast'
//     $user = \App\Models\User::where('empno', $request->empno)->first();

//     if (!$user) {
//         return response()->json([
//             'status' => 'failed',
//             'message' => 'Employee not found',
//         ], 404);
//     }

//     // Check password against the 'askapp_pw' column defined in your User model
//     if (!\Illuminate\Support\Facades\Hash::check($request->password, $user->askapp_pw)) {
//         return response()->json([
//             'status' => 'failed',
//             'message' => 'Invalid credentials',
//         ], 401);
//     }

//     // Generate the Sanctum token
//     $token = $user->createToken('auth_token')->plainTextToken;

//     return response()->json([
//         'status' => 'success',
//         'token' => $token, // Send this to React
//         'data' => [
//             'empno' => $user->empno,
//             'empname' => $user->emp_name
//         ],
//     ]);
// }

// app/Http/Controllers/RegisterController.php

// public function loginEmp(Request $request)
// {
//     $request->validate([
//         'empno' => 'required|string',
//         'password' => 'required|string',
//     ]);

//     // Fetch using the Model
//     $user = \App\Models\User::where('empno', $request->empno)->first();

//     if (!$user) {
//         return response()->json(['status' => 'failed', 'message' => 'Employee not found'], 404);
//     }

//     // CRITICAL: Use trim() to remove SQL Server padding spaces
//     $storedHash = trim($user->askapp_pw);

//     // Double check it's actually a hash now
//     if (!str_starts_with($storedHash, '$2y$')) {
//         return response()->json([
//             'status' => 'error',
//             'msg' => 'Invalid hash format in DB',
//             'debug_value' => "|" . $storedHash . "|" // The pipes show if spaces still exist
//         ], 500);
//     }

//     if (!Hash::check($request->password, $storedHash)) { 
//         return response()->json(['status' => 'failed', 'message' => 'Invalid credentials'], 401);
//     }

//     // Generate token for React
//     $token = $user->createToken('auth_token')->plainTextToken;

//     return response()->json([
//         'status' => 'success',
//         'token' => $token,
//         'data' => [
//             'empno' => $user->empno,
//             'empname' => $user->emp_name
//         ],
//     ]);
// }

// <?php

// namespace App\Http\Controllers;

// use Illuminate\Http\Request;
// use Illuminate\Support\Facades\DB;
// use Illuminate\Support\Facades\Hash;
// use App\Models\User; // Ensure this is imported

// class RegisterController extends Controller
// {
    /**
     * Handle Employee Registration
     */
    public function regEmp(Request $request)
    {
        $request->validate([
            'empno' => 'required|string',
            'password' => 'required|string|min:6',
            'confirm_password' => 'required|string|same:password',
        ]);

        $empno = $request->input('empno');
        $hashedPassword = Hash::make($request->input('password'));

        $jsonData = json_encode([
            'json_data' => [
                'empNo' => $empno,
                'password' => $hashedPassword,
            ]
        ]);

        try {
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

    /**
     * Handle Employee Login & Token Generation
     */
    

public function loginEmp(Request $request)
{
    $validated = $request->validate([
        'empno' => ['required', 'string'],
        'password' => ['required', 'string'],
    ]);

    $empno = trim($validated['empno']);

    /*
     * The User model is explicitly mapped to paymast.
     * Do not alias EMPNO because Sanctum needs the real primary-key attribute.
     */
    $user = User::query()
        ->select([
            'EMPNO',
            'ASKAPP_PW',
            'EMP_NAME',
            'COMP_NAME',
            'HR_FLAG',
        ])
        ->where('EMPNO', $empno)
        ->first();

    if (!$user) {
        return response()->json([
            'status' => 'failed',
            'success' => false,
            'message' => 'Employee not found.',
        ], 404);
    }

    $dbHash = trim((string) $user->ASKAPP_PW);

    if ($dbHash === '') {
        return response()->json([
            'status' => 'failed',
            'success' => false,
            'message' => 'Employee password has not been registered.',
        ], 401);
    }

    if (!Hash::check($validated['password'], $dbHash)) {
        return response()->json([
            'status' => 'failed',
            'success' => false,
            'message' => 'Invalid credentials.',
        ], 401);
    }

    $token = $user->createToken('auth_token')->plainTextToken;

    $userData = [
        'empno' => trim((string) $user->EMPNO),
        'EMP_NO' => trim((string) $user->EMPNO),
        'userId' => trim((string) $user->EMPNO),
        'userid' => trim((string) $user->EMPNO),

        'emp_name' => trim((string) $user->EMP_NAME),
        'empName' => trim((string) $user->EMP_NAME),

        'comp_name' => trim((string) $user->COMP_NAME),
        'compName' => trim((string) $user->COMP_NAME),

        'hr_flag' => trim((string) ($user->HR_FLAG ?? 'N')),
        'hrFlag' => trim((string) ($user->HR_FLAG ?? 'N')),
    ];

    return response()->json([
        'status' => 'success',
        'success' => true,
        'message' => 'Login successful.',
        'token' => $token,
        'data' => $userData,
        'user' => $userData,
    ]);
}



}

