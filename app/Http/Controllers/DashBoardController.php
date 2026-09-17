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


    /**
     * GET /api/announcements
     * Load only the announcements visible to the logged-in employee.
     */
    public function announcements(Request $request)
    {
        $validated = $request->validate([
            'EMP_NO' => 'required|string|max:50',
        ]);

        try {
            $params = json_encode([
                'viewerEmpNo' => trim($validated['EMP_NO']),
            ], JSON_UNESCAPED_UNICODE);

            $results = DB::select(
                'EXEC dbo.sproc_PHP_EmpInq_Announcement @mode = ?, @params = ?',
                ['Load', $params]
            );

            return response()->json([
                'success' => true,
                'data' => $results,
            ], 200);

        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;

        } catch (\Throwable $e) {
            Log::error('Announcement load error', [
                'viewerEmpNo' => $validated['EMP_NO'] ?? null,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Unable to load announcements.',
            ], 500);
        }
    }

    /**
     * POST /api/announcements
     * HR, Manager, or Supervisor may create an announcement.
     * The stored procedure validates the employee's actual PAYMAST flags.
     */
    public function createAnnouncement(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:150',
            'message' => 'required|string|max:2000',
            'expirationDate' => 'required|date_format:Y-m-d',
            'createdBy' => 'required|string|max:50',
            'visibilityScope' => 'nullable|string|in:ALL,TEAM',
        ]);

        try {
            $params = json_encode([
                'title' => trim($validated['title']),
                'message' => trim($validated['message']),
                'expirationDate' => $validated['expirationDate'],
                'createdBy' => trim($validated['createdBy']),
                'visibilityScope' => strtoupper(trim($validated['visibilityScope'] ?? '')),
            ], JSON_UNESCAPED_UNICODE);

            $results = DB::select(
                'EXEC dbo.sproc_PHP_EmpInq_Announcement @mode = ?, @params = ?',
                ['Save', $params]
            );

            return response()->json([
                'success' => true,
                'message' => 'Announcement posted successfully.',
                'data' => $results[0] ?? null,
            ], 201);

        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;

        } catch (\Throwable $e) {
            Log::error('Announcement create error', [
                'createdBy' => $validated['createdBy'] ?? null,
                'visibilityScope' => $validated['visibilityScope'] ?? null,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $message = strtolower($e->getMessage());
            $status = (
                str_contains($message, 'authorized') ||
                str_contains($message, 'only hr') ||
                str_contains($message, 'require manager') ||
                str_contains($message, 'require supervisor')
            ) ? 403 : 500;

            return response()->json([
                'success' => false,
                'message' => $status === 403
                    ? 'Only HR, Managers, or Supervisors may post announcements for their permitted audience.'
                    : 'Unable to post announcement.',
            ], $status);
        }
    }

    /**
     * PUT /api/announcements/{id}
     * Only the employee who created the announcement may edit it.
     */
    public function updateAnnouncement(Request $request, $id)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:150',
            'message' => 'required|string|max:2000',
            'expirationDate' => 'required|date_format:Y-m-d',
            'updatedBy' => 'required|string|max:50',
        ]);

        try {
            $params = json_encode([
                'announcementId' => (int) $id,
                'title' => trim($validated['title']),
                'message' => trim($validated['message']),
                'expirationDate' => $validated['expirationDate'],
                'updatedBy' => trim($validated['updatedBy']),
            ], JSON_UNESCAPED_UNICODE);

            $results = DB::select(
                'EXEC dbo.sproc_PHP_EmpInq_Announcement @mode = ?, @params = ?',
                ['Edit', $params]
            );

            return response()->json([
                'success' => true,
                'message' => 'Announcement updated successfully.',
                'data' => $results[0] ?? null,
            ], 200);

        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;

        } catch (\Throwable $e) {
            Log::error('Announcement update error', [
                'announcementId' => $id,
                'updatedBy' => $validated['updatedBy'] ?? null,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $message = strtolower($e->getMessage());
            $status = str_contains($message, 'created this announcement')
                ? 403
                : (str_contains($message, 'not found') ? 404 : 500);

            return response()->json([
                'success' => false,
                'message' => $status === 403
                    ? 'Only the employee who created this announcement can edit it.'
                    : ($status === 404
                        ? 'Announcement not found.'
                        : 'Unable to update announcement.'),
            ], $status);
        }
    }

    /**
     * DELETE /api/announcements/{id}
     * Only the employee who created the announcement may soft-delete it.
     */
    public function deleteAnnouncement(Request $request, $id)
    {
        $validated = $request->validate([
            'deletedBy' => 'required|string|max:50',
        ]);

        try {
            $params = json_encode([
                'announcementId' => (int) $id,
                'deletedBy' => trim($validated['deletedBy']),
            ], JSON_UNESCAPED_UNICODE);

            DB::select(
                'EXEC dbo.sproc_PHP_EmpInq_Announcement @mode = ?, @params = ?',
                ['Delete', $params]
            );

            return response()->json([
                'success' => true,
                'message' => 'Announcement deleted successfully.',
            ], 200);

        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;

        } catch (\Throwable $e) {
            Log::error('Announcement delete error', [
                'announcementId' => $id,
                'deletedBy' => $validated['deletedBy'] ?? null,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $message = strtolower($e->getMessage());
            $status = str_contains($message, 'created this announcement')
                ? 403
                : (str_contains($message, 'not found') ? 404 : 500);

            return response()->json([
                'success' => false,
                'message' => $status === 403
                    ? 'Only the employee who created this announcement can delete it.'
                    : ($status === 404
                        ? 'Announcement not found.'
                        : 'Unable to delete announcement.'),
            ], $status);
        }
    }


}