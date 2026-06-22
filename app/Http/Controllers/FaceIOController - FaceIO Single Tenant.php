<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;

class FaceIOController extends Controller
{
    /**
     * Check if employee already has FACEIO enrollment
     */
    public function check($empNo)
    {
        try {
            $empNo = trim((string) $empNo);

            if ($empNo === '') {
                return response()->json([
                    'success' => false,
                    'message' => 'Employee number is required.',
                    'hasFace' => false,
                    'facialId' => null,
                ], 422);
            }

            $employee = DB::table('paymast')
                ->select('empno', 'emp_name', 'faceio_facial_id')
                ->where('empno', $empNo)
                ->first();

            if (!$employee) {
                return response()->json([
                    'success' => false,
                    'message' => 'Employee not found.',
                    'hasFace' => false,
                    'facialId' => null,
                ], 404);
            }

            return response()->json([
                'success' => true,
                'message' => 'FACEIO enrollment status fetched successfully.',
                'hasFace' => !empty($employee->faceio_facial_id),
                'facialId' => $employee->faceio_facial_id,
                'empNo' => $employee->empno,
                'empName' => $employee->emp_name ?? null,
            ]);
        } catch (\Throwable $e) {
            Log::error('FACEIO check failed', [
                'empNo' => $empNo ?? null,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Unable to check FACEIO enrollment status.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function enroll(Request $request)
{
    try {
        $validated = $request->validate([
            'empNo' => 'required|string',
            'facialId' => 'required|string',
        ]);

        $authUser = $request->user();
        $empNo = trim((string) $validated['empNo']);
        $facialId = trim((string) $validated['facialId']);

        if (!$authUser) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated user.',
            ], 401);
        }

        $authEmpNo = trim((string) (
            $authUser->empno ??
            $authUser->emp_no ??
            $authUser->EMPNO ??
            $authUser->EMP_NO ??
            ''
        ));

        $isAdmin = (bool) (
            $authUser->is_admin ??
            $authUser->isAdmin ??
            $authUser->IS_ADMIN ??
            false
        );

        if ($authEmpNo !== $empNo && !$isAdmin) {
            Log::warning('FACEIO enroll unauthorized', [
                'auth_user_id' => $authUser->id ?? null,
                'auth_empno' => $authEmpNo,
                'request_empno' => $empNo,
                'is_admin' => $isAdmin,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Unauthorized action.',
                'debug' => [
                    'authEmpNo' => $authEmpNo,
                    'requestEmpNo' => $empNo,
                    'isAdmin' => $isAdmin,
                ],
            ], 403);
        }

        $employee = DB::table('paymast')
            ->where('empno', $empNo)
            ->first();

        if (!$employee) {
            return response()->json([
                'success' => false,
                'message' => 'Employee not found.',
            ], 404);
        }

        DB::table('paymast')
            ->where('empno', $empNo)
            ->update([
                'faceio_facial_id' => $facialId,
            ]);

        return response()->json([
            'success' => true,
            'message' => 'FACEIO enrollment saved successfully.',
            'empNo' => $empNo,
            'facialId' => $facialId,
        ]);
    } catch (\Illuminate\Validation\ValidationException $e) {
        return response()->json([
            'success' => false,
            'message' => 'Validation failed.',
            'errors' => $e->errors(),
        ], 422);
    } catch (\Throwable $e) {
        Log::error('FACEIO enroll failed', [
            'payload' => $request->all(),
            'auth_user' => $request->user(),
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
        ]);

        return response()->json([
            'success' => false,
            'message' => 'Unable to save FACEIO enrollment.',
            'error' => $e->getMessage(),
        ], 500);
    }
}

    /**
     * Delete FACEIO enrollment
     */
    
   public function delete(Request $request)
{
    try {
        $validated = $request->validate([
            'empNo' => 'required|string',
            'facialId' => 'nullable|string',
        ]);

        $user = $request->user();
        $empNo = trim((string) $validated['empNo']);
        $facialId = trim((string) ($validated['facialId'] ?? ''));

        if ($user && $user->empno !== $empNo && !$user->is_admin) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized action.',
            ], 403);
        }

        $employee = DB::table('paymast')
            ->select('empno', 'emp_name', 'faceio_facial_id')
            ->where('empno', $empNo)
            ->first();

        if (!$employee) {
            return response()->json([
                'success' => false,
                'message' => 'Employee not found.',
            ], 404);
        }

        if ($facialId === '') {
            $facialId = trim((string) ($employee->faceio_facial_id ?? ''));
        }

        if ($facialId === '') {
            return response()->json([
                'success' => false,
                'message' => 'No facialId found in request or database.',
            ], 400);
        }

        $faceioResponse = Http::timeout(30)->get('https://api.faceio.net/deletefacialid', [
            'fid' => $facialId,
            'key' => env('FACEIO_API_KEY'),
        ]);

        Log::info('FACEIO delete response', [
            'empNo' => $empNo,
            'facialId' => $facialId,
            'status' => $faceioResponse->status(),
            'body' => $faceioResponse->body(),
        ]);

        if (!$faceioResponse->successful()) {
            return response()->json([
                'success' => false,
                'message' => 'FACEIO cloud deletion failed.',
                'faceio_response' => $faceioResponse->body(),
            ], 500);
        }

        $decoded = $faceioResponse->json();

        if (is_array($decoded)) {
            $possibleFailure =
                (isset($decoded['status']) && $decoded['status'] !== 1 && $decoded['status'] !== 'success') ||
                (isset($decoded['error']) && $decoded['error']) ||
                (isset($decoded['success']) && $decoded['success'] === false);

            if ($possibleFailure) {
                return response()->json([
                    'success' => false,
                    'message' => 'FACEIO cloud deletion was rejected.',
                    'faceio_response' => $decoded,
                ], 500);
            }
        }

        DB::table('paymast')
            ->where('empno', $empNo)
            ->update([
                'faceio_facial_id' => null,
            ]);

        return response()->json([
            'success' => true,
            'message' => 'FACEIO enrollment deleted from cloud and local database.',
            'empNo' => $empNo,
            'facialId' => $facialId,
        ]);
    } catch (\Illuminate\Validation\ValidationException $e) {
        return response()->json([
            'success' => false,
            'message' => 'Validation failed.',
            'errors' => $e->errors(),
        ], 422);
    } catch (\Throwable $e) {
        Log::error('FACEIO delete failed', [
            'payload' => $request->all(),
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
        ]);

        return response()->json([
            'success' => false,
            'message' => 'Unable to complete deletion.',
            'error' => $e->getMessage(),
        ], 500);
    }
}

}