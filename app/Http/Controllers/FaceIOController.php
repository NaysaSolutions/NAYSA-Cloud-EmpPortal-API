<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FaceIOController extends Controller
{
    private function normalizeTenantCode($source): string
    {
        return trim((string) (
            $source['tenantCode'] ??
            $source['tenant_code'] ??
            $source['companyCode'] ??
            $source['company_code'] ??
            $source['code'] ??
            ''
        ));
    }

    private function getAuthenticatedEmpNo($user): string
    {
        return trim((string) (
            $user->empno ??
            $user->emp_no ??
            $user->EMPNO ??
            $user->EMP_NO ??
            ''
        ));
    }

    private function getAuthenticatedTenantCode($user): string
    {
        return trim((string) (
            $user->tenantCode ??
            $user->tenant_code ??
            $user->companyCode ??
            $user->company_code ??
            $user->code ??
            ''
        ));
    }

    private function isAdminUser($user): bool
    {
        return (bool) (
            $user->is_admin ??
            $user->isAdmin ??
            $user->IS_ADMIN ??
            false
        );
    }

    private function ensureAuthorized(Request $request, string $targetEmpNo, ?string $targetTenantCode = null)
    {
        $authUser = $request->user();

        if (!$authUser) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated user.',
            ], 401);
        }

        $authEmpNo = $this->getAuthenticatedEmpNo($authUser);
        $authTenantCode = $this->getAuthenticatedTenantCode($authUser);
        $isAdmin = $this->isAdminUser($authUser);

        if (!$isAdmin && $authEmpNo !== trim($targetEmpNo)) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized action.',
                'debug' => [
                    'authEmpNo' => $authEmpNo,
                    'targetEmpNo' => trim($targetEmpNo),
                ],
            ], 403);
        }

        if (!$isAdmin && $targetTenantCode !== null && $targetTenantCode !== '' && $authTenantCode !== '' && $authTenantCode !== trim($targetTenantCode)) {
            return response()->json([
                'success' => false,
                'message' => 'Tenant mismatch.',
                'debug' => [
                    'authTenantCode' => $authTenantCode,
                    'targetTenantCode' => trim($targetTenantCode),
                ],
            ], 403);
        }

        return null;
    }

    public function check(Request $request, $empNo)
    {
        try {
            $empNo = trim((string) $empNo);
            $tenantCode = $this->normalizeTenantCode($request->all());

            if ($empNo === '') {
                return response()->json([
                    'success' => false,
                    'message' => 'Employee number is required.',
                    'hasFace' => false,
                    'facialId' => null,
                ], 422);
            }

            if ($authError = $this->ensureAuthorized($request, $empNo, $tenantCode ?: null)) {
                return $authError;
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
                'tenantCode' => $tenantCode,
            ]);
        } catch (\Throwable $e) {
            Log::error('FACEIO check failed', [
                'empNo' => $empNo ?? null,
                'payload' => $request->all(),
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
                'tenantCode' => 'nullable|string',
                'payloadEmpNo' => 'nullable|string',
                'payloadTenantCode' => 'nullable|string',
            ]);

            $empNo = trim((string) $validated['empNo']);
            $facialId = trim((string) $validated['facialId']);
            $tenantCode = trim((string) ($validated['tenantCode'] ?? ''));
            $payloadEmpNo = trim((string) ($validated['payloadEmpNo'] ?? ''));
            $payloadTenantCode = trim((string) ($validated['payloadTenantCode'] ?? ''));

            if ($authError = $this->ensureAuthorized($request, $empNo, $tenantCode ?: null)) {
                return $authError;
            }

            if ($payloadEmpNo !== '' && $payloadEmpNo !== $empNo) {
                return response()->json([
                    'success' => false,
                    'message' => 'FACEIO payload employee number mismatch.',
                    'debug' => [
                        'requestEmpNo' => $empNo,
                        'payloadEmpNo' => $payloadEmpNo,
                    ],
                ], 422);
            }

            if ($tenantCode !== '' && $payloadTenantCode !== '' && $tenantCode !== $payloadTenantCode) {
                return response()->json([
                    'success' => false,
                    'message' => 'FACEIO payload tenant code mismatch.',
                    'debug' => [
                        'requestTenantCode' => $tenantCode,
                        'payloadTenantCode' => $payloadTenantCode,
                    ],
                ], 422);
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

            $existingOwner = DB::table('paymast')
                ->select('empno', 'emp_name', 'faceio_facial_id')
                ->where('faceio_facial_id', $facialId)
                ->where('empno', '<>', $empNo)
                ->first();

            if ($existingOwner) {
                return response()->json([
                    'success' => false,
                    'message' => 'This FACEIO facial ID is already assigned to another employee.',
                    'debug' => [
                        'existingEmpNo' => $existingOwner->empno,
                        'existingEmpName' => $existingOwner->emp_name,
                        'facialId' => $existingOwner->faceio_facial_id,
                    ],
                ], 409);
            }

            DB::table('paymast')
                ->where('empno', $empNo)
                ->update([
                    'faceio_facial_id' => $facialId,
                    'tenant_code' => $tenantCode,
                ]);

            return response()->json([
                'success' => true,
                'message' => 'FACEIO enrollment saved successfully.',
                'empNo' => $empNo,
                'tenantCode' => $tenantCode,
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

    public function delete(Request $request)
    {
        try {
            $validated = $request->validate([
                'empNo' => 'required|string',
                'facialId' => 'nullable|string',
                'tenantCode' => 'nullable|string',
            ]);

            $empNo = trim((string) $validated['empNo']);
            $facialId = trim((string) ($validated['facialId'] ?? ''));
            $tenantCode = trim((string) ($validated['tenantCode'] ?? ''));

            if ($authError = $this->ensureAuthorized($request, $empNo, $tenantCode ?: null)) {
                return $authError;
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
                'tenantCode' => $tenantCode,
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
                'tenantCode' => $tenantCode,
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
