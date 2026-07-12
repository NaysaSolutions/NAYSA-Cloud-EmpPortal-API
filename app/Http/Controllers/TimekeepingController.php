<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class TimekeepingController extends Controller
{
    private string $sprocName = 'dbo.sproc_PHP_EmpInq_DTR';

    public function upsertTimeIn(Request $request)
    {
        $data = $request->all();

        if (!is_array($data) || array_values($data) !== $data || count($data) === 0) {
            return response()->json([
                'success' => false,
                'status'  => 'error',
                'message' => 'Invalid data format. Expected a non-empty array of timekeeping records.',
            ], 422);
        }

        try {
            foreach ($data as $index => &$item) {
                if (!isset($item['detail']) || !is_array($item['detail'])) {
                    return response()->json([
                        'success' => false,
                        'status'  => 'error',
                        'message' => '"detail" is missing or invalid for item at index '.$index,
                    ], 422);
                }

                $validator = Validator::make($item, [
                    'empNo' => 'required|string',
                    'detail' => 'required|array',
                    'detail.empNo' => 'required|string',
                    'detail.date' => 'required|date_format:Y-m-d',

                    'detail.timeIn' => 'nullable|string',
                    'detail.breakIn' => 'nullable|string',
                    'detail.breakOut' => 'nullable|string',
                    'detail.timeOut' => 'nullable|string',

                    'detail.timeInImageId' => 'nullable|string',
                    'detail.timeOutImageId' => 'nullable|string',
                    'detail.breakInImageId' => 'nullable|string',
                    'detail.breakOutImageId' => 'nullable|string',

                    'detail.timeInImagePath' => 'nullable|string',
                    'detail.timeOutImagePath' => 'nullable|string',
                    'detail.breakInImagePath' => 'nullable|string',
                    'detail.breakOutImagePath' => 'nullable|string',

                    'detail.timeInImageBase64' => 'nullable|string',
                    'detail.timeOutImageBase64' => 'nullable|string',
                    'detail.breakInImageBase64' => 'nullable|string',
                    'detail.breakOutImageBase64' => 'nullable|string',

                    'detail.latitude' => 'nullable',
                    'detail.longitude' => 'nullable',
                    'detail.accuracy' => 'nullable',
                    'detail.locationAccuracy' => 'nullable',
                    'detail.locationAddress' => 'nullable|string',

                    'detail.branchcode' => 'nullable|string',
                    'detail.branchname' => 'nullable|string',
                    'detail.allowedRadius' => 'nullable',
                    'detail.geotagging' => 'nullable',
                    'detail.geofence' => 'nullable',

                    'detail.faceioFacialId' => 'nullable|string',
                    'detail.faceioPayloadEmpNo' => 'nullable|string',
                    'detail.faceioTenantCode' => 'nullable|string',
                    'detail.faceioPayloadTenantCode' => 'nullable|string',
                    'detail.faceioVerifiedAt' => 'nullable|string',
                ]);

                if ($validator->fails()) {
                    Log::warning('Validation failed for upsertTimeIn record.', [
                        'index'  => $index,
                        'errors' => $validator->errors(),
                        'item'   => $item,
                    ]);

                    return response()->json([
                        'success' => false,
                        'status'  => 'error',
                        'message' => 'Validation failed for item at index '.$index,
                        'errors'  => $validator->errors(),
                    ], 422);
                }

                $detail = &$item['detail'];

                $this->validateFaceIoVerification($request, $item, $detail);

                $branchCode = $detail['branchcode'] ?? $detail['branchCode'] ?? 'NO_BRANCH';
                $empNo      = $detail['empNo'] ?? $item['empNo'] ?? 'NO_EMPLOYEE';

                $this->saveInlineBase64ImageIfPresent($detail, 'timeIn', $branchCode, $empNo);
                $this->saveInlineBase64ImageIfPresent($detail, 'timeOut', $branchCode, $empNo);
                $this->saveInlineBase64ImageIfPresent($detail, 'breakIn', $branchCode, $empNo);
                $this->saveInlineBase64ImageIfPresent($detail, 'breakOut', $branchCode, $empNo);

            }

            unset($item, $detail);

            $params = collect($data)->values()->toJson(JSON_UNESCAPED_SLASHES);
            $first  = $data[0];

            Log::info('Calling Timekeeping upsert.', [
                'mode' => 'upsert_TimeIn',
                'emp'  => $first['empNo'] ?? null,
                'date' => $first['detail']['date'] ?? null,
            ]);

            $this->executeDtrStatement(
                'upsert_TimeIn',
                $first['empNo'] ?? null,
                null,
                null,
                $first['detail']['date'] ?? null,
                $this->getUserId($request, $first['empNo'] ?? null),
                null,
                $params
            );

            return response()->json([
                'success' => true,
                'status'  => 'success',
                'message' => 'Timekeeping record saved successfully.',
                'data'    => $data,
            ]);
        } catch (\Throwable $e) {
            Log::error('Failed to upsert Time In/Out record.', [
                'message' => $e->getMessage(),
                'trace'   => $e->getTraceAsString(),
                'data'    => $data,
            ]);

            return response()->json([
                'success'       => false,
                'status'        => 'error',
                'message'       => $e->getMessage(),
                'error_details' => $e->getMessage(),
            ], 500);
        }
    }

    public function getNewImageId()
    {
        try {
            $newId = $this->generateImageId();

            return response()->json([
                'success' => true,
                'id'      => $newId,
            ]);
        } catch (\Throwable $e) {
            Log::error('Error in getNewImageId.', [
                'message' => $e->getMessage(),
                'trace'   => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success'       => false,
                'message'       => 'Failed to generate a new image ID.',
                'error_details' => $e->getMessage(),
            ], 500);
        }
    }

    public function saveImage(Request $request)
    {
        $request->validate([
            'imageId'    => 'required|string',
            'imageData'  => 'required|string',
            'branchCode' => 'nullable|string',
            'empNo'      => 'nullable|string',
        ]);

        try {
            $imageId    = $request->input('imageId');
            $imageData  = $request->input('imageData');
            $branchCode = $request->input('branchCode', 'NO_BRANCH');
            $empNo      = $request->input('empNo', 'NO_EMPLOYEE');

            $path = $this->saveImageToPublicImages($imageId, $imageData, $branchCode, $empNo);

            return response()->json([
                'success' => true,
                'path'    => $path,
                'url'     => asset('images/'.$path),
            ]);
        } catch (\Throwable $e) {
            Log::error('Error saving timekeeping image.', [
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'success'       => false,
                'message'       => 'Failed to save image.',
                'error_details' => $e->getMessage(),
            ], 500);
        }
    }

    public function getDTRRecords(Request $request, $empNo, $startDate, $endDate)
    {
        return $this->getDTRRecordsCore($request, $empNo, $startDate, $endDate);
    }

    public function getDTRRecordsPost(Request $request)
    {
        $request->validate([
            'empNo'     => 'required|string',
            'startDate' => 'required|date_format:Y-m-d',
            'endDate'   => 'required|date_format:Y-m-d',
        ]);

        return $this->getDTRRecordsCore(
            $request,
            $request->input('empNo'),
            $request->input('startDate'),
            $request->input('endDate')
        );
    }

    private function getDTRRecordsCore(Request $request, string $empNo, string $startDate, string $endDate)
    {
        try {
            $records = $this->executeDtrSelect(
                'get_DTR',
                $empNo,
                $startDate,
                $endDate,
                null,
                $this->getUserId($request),
                null,
                null
            );

            return response()->json([
                'success' => true,
                'message' => 'DTR records fetched successfully.',
                'records' => $records,
                'data'    => $records,
            ]);
        } catch (\Throwable $e) {
            Log::error('Error fetching DTR records.', [
                'message'   => $e->getMessage(),
                'trace'     => $e->getTraceAsString(),
                'empNo'     => $empNo,
                'startDate' => $startDate,
                'endDate'   => $endDate,
            ]);

            return response()->json([
                'success'       => false,
                'message'       => 'Failed to fetch DTR records.',
                'error_details' => $e->getMessage(),
            ], 500);
        }
    }

    public function getDTRHistory(Request $request)
    {
        $request->merge([
            'empNo'     => $request->input('empNo', $request->input('EMP_NO')),
            'startDate' => $request->input('startDate', $request->input('START_DATE', now()->startOfMonth()->format('Y-m-d'))),
            'endDate'   => $request->input('endDate', $request->input('END_DATE', now()->format('Y-m-d'))),
        ]);

        $request->validate([
            'empNo'     => 'required|string',
            'startDate' => 'required|date_format:Y-m-d',
            'endDate'   => 'required|date_format:Y-m-d',
        ]);

        return $this->getDTRRecordsCore(
            $request,
            $request->input('empNo'),
            $request->input('startDate'),
            $request->input('endDate')
        );
    }

    public function getBranchLocation(Request $request, $empNo)
    {
        try {
            $records = $this->executeDtrSelect(
                'get_BranchLocation',
                $empNo,
                null,
                null,
                null,
                $this->getUserId($request),
                null,
                null
            );

            return response()->json([
                'success' => true,
                'message' => 'Employee branch location fetched successfully.',
                'records' => $records,
                'data'    => $records,
            ]);
        } catch (\Throwable $e) {
            Log::error('Error fetching branch location.', [
                'message' => $e->getMessage(),
                'trace'   => $e->getTraceAsString(),
                'empNo'   => $empNo,
            ]);

            return response()->json([
                'success'       => false,
                'message'       => 'Failed to fetch branch location.',
                'error_details' => $e->getMessage(),
            ], 500);
        }
    }

    public function getApprInq(Request $request)
    {
        $request->validate(['EMP_NO' => 'required|string']);

        $rows = $this->executeDtrSelect(
            'ApprInq',
            $request->input('EMP_NO'),
            null,
            null,
            null,
            $this->getUserId($request),
            null,
            null,
            $request->input('STAT')
        );

        $data = $this->unwrapJsonResult($rows);

        return response()->json([
            'success' => true,
            'data'    => $data,
            'records' => $data,
        ]);
    }

    public function getApprHistory(Request $request)
    {
        $request->validate([
            'EMP_NO'     => 'required|string',
            'START_DATE' => 'required|date_format:Y-m-d',
            'END_DATE'   => 'required|date_format:Y-m-d',
        ]);

        $rows = $this->executeDtrSelect(
            'ApprHistory',
            $request->input('EMP_NO'),
            $request->input('START_DATE'),
            $request->input('END_DATE'),
            null,
            $this->getUserId($request),
            null,
            null
        );

        $data = $this->unwrapJsonResult($rows);

        return response()->json([
            'success' => true,
            'data'    => $data,
            'records' => $data,
        ]);
    }

    public function getAppInq(Request $request)
    {
        $request->validate(['EMP_NO' => 'required|string']);

        $rows = $this->executeDtrSelect(
            'AppInq',
            $request->input('EMP_NO'),
            null,
            null,
            null,
            $this->getUserId($request),
            null,
            null
        );

        $data = $this->unwrapJsonResult($rows);

        return response()->json([
            'success' => true,
            'data'    => $data,
            'records' => $data,
        ]);
    }

    public function getAppHistory(Request $request)
    {
        $request->validate([
            'EMP_NO'     => 'required|string',
            'START_DATE' => 'required|date_format:Y-m-d',
            'END_DATE'   => 'required|date_format:Y-m-d',
        ]);

        $rows = $this->executeDtrSelect(
            'AppHistory',
            $request->input('EMP_NO'),
            $request->input('START_DATE'),
            $request->input('END_DATE'),
            null,
            $this->getUserId($request),
            null,
            null
        );

        $data = $this->unwrapJsonResult($rows);

        return response()->json([
            'success' => true,
            'data'    => $data,
            'records' => $data,
        ]);
    }

    public function upsert(Request $request)
    {
        try {
            $data = $this->extractJsonDataPayload($request);

            if (empty($data['empNo']) || empty($data['detail'])) {
                return response()->json([
                    'success' => false,
                    'status'  => 'error',
                    'message' => 'Invalid data provided. empNo and detail are required.',
                ], 400);
            }

            $jsonParams = json_encode(['json_data' => $data], JSON_UNESCAPED_SLASHES);

            $this->executeDtrStatement(
                'upsert',
                $data['empNo'],
                null,
                null,
                null,
                $this->getUserId($request, $data['empNo']),
                null,
                $jsonParams
            );

            return response()->json([
                'success' => true,
                'status'  => 'success',
                'message' => 'DTR application submitted successfully.',
            ]);
        } catch (\Throwable $e) {
            Log::error('Error in upsertDTR.', ['message' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'status'  => 'error',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function approval(Request $request)
    {
        try {
            $data = $this->extractJsonDataPayload($request);

            if (empty($data['dtrStamp'])) {
                return response()->json([
                    'success' => false,
                    'status'  => 'error',
                    'message' => 'dtrStamp is required.',
                ], 400);
            }

            $jsonParams = json_encode($data, JSON_UNESCAPED_SLASHES);

            $this->executeDtrStatement(
                'Approval',
                $data['empNo'] ?? null,
                null,
                null,
                null,
                $this->getUserId($request, $data['appUser'] ?? null),
                null,
                $jsonParams
            );

            return response()->json([
                'success' => true,
                'status'  => 'success',
                'message' => 'DTR approval processed successfully.',
            ]);
        } catch (\Throwable $e) {
            Log::error('Error in approvalDTR.', ['message' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'status'  => 'error',
                'message' => 'Failed to process approval: '.$e->getMessage(),
            ], 500);
        }
    }

    public function cancel(Request $request)
    {
        try {
            $data = $this->extractJsonDataPayload($request);

            $empNo = $data['empNo'] ?? null;
            $stamp = $data['dtrStamp'] ?? null;

            if (!$empNo || !$stamp) {
                return response()->json([
                    'success' => false,
                    'status'  => 'error',
                    'message' => 'empNo and dtrStamp are required.',
                ], 400);
            }

            $jsonParams = json_encode([
                'empNo'    => $empNo,
                'dtrStamp' => $stamp,
            ], JSON_UNESCAPED_SLASHES);

            $this->executeDtrStatement(
                'Cancel',
                $empNo,
                null,
                null,
                null,
                $this->getUserId($request, $empNo),
                null,
                $jsonParams
            );

            return response()->json([
                'success' => true,
                'status'  => 'success',
                'message' => 'DTR cancelled successfully.',
            ]);
        } catch (\Throwable $e) {
            Log::error('Error in cancelDTR.', ['message' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'status'  => 'error',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function confirmDTR(Request $request)
    {
        $request->validate([
            'empNo'     => 'required|string',
            'startDate' => 'required|date_format:Y-m-d',
            'endDate'   => 'required|date_format:Y-m-d',
        ]);

        try {
            $rows = $this->executeDtrSelect(
                'ConfirmDTR',
                $request->input('empNo'),
                $request->input('startDate'),
                $request->input('endDate'),
                null,
                $this->getUserId($request),
                null,
                null
            );

            $result = (object) ($rows[0] ?? []);

            $status  = $result->status ?? ($result->STATUS ?? 'success');
            $message = $result->message ?? ($result->MESSAGE ?? 'DTR successfully confirmed.');

            return response()->json([
                'success' => strtolower((string) $status) === 'success',
                'status'  => $status,
                'message' => $message,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'status'  => 'error',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function getAllDTR(Request $request)
    {
        $request->merge([
            'empNo'     => $request->input('empNo', $request->input('EMP_NO')),
            'startDate' => $request->input('startDate', $request->input('START_DATE')),
            'endDate'   => $request->input('endDate', $request->input('END_DATE')),
        ]);

        $request->validate([
            'startDate' => 'required|date_format:Y-m-d',
            'endDate'   => 'required|date_format:Y-m-d',
            'empNo'     => 'nullable|string',
        ]);

        return $this->getAllDTRCore($request, 'getAll_DTR', 'All DTR records fetched successfully.');
    }

    public function getAllDTRHR(Request $request)
    {
        $request->merge([
            'empNo'     => $request->input('empNo', $request->input('EMP_NO')),
            'startDate' => $request->input('startDate', $request->input('START_DATE')),
            'endDate'   => $request->input('endDate', $request->input('END_DATE')),
        ]);

        $request->validate([
            'empNo'     => 'required|string',
            'startDate' => 'required|date_format:Y-m-d',
            'endDate'   => 'required|date_format:Y-m-d',
        ]);

        return $this->getAllDTRCore($request, 'getAll_DTR_HR', 'HR DTR records fetched successfully.');
    }

    private function getAllDTRCore(Request $request, string $mode, string $successMessage)
    {
        try {
            $records = $this->executeDtrSelect(
                $mode,
                $request->input('empNo'),
                $request->input('startDate'),
                $request->input('endDate'),
                null,
                $this->getUserId($request),
                null,
                null
            );

            return response()->json([
                'success' => true,
                'message' => $successMessage,
                'records' => $records,
                'data'    => $records,
            ]);
        } catch (\Throwable $e) {
            Log::error('Error fetching '.$mode.' records.', [
                'message'   => $e->getMessage(),
                'trace'     => $e->getTraceAsString(),
                'startDate' => $request->input('startDate'),
                'endDate'   => $request->input('endDate'),
                'empNo'     => $request->input('empNo'),
            ]);

            return response()->json([
                'success'       => false,
                'message'       => 'Failed to fetch DTR records.',
                'error_details' => $e->getMessage(),
            ], 500);
        }
    }

    private function executeDtrSelect(
        string $mode,
        ?string $empNo = null,
        ?string $startDate = null,
        ?string $endDate = null,
        ?string $date = null,
        ?string $userId = null,
        ?string $cutoff = null,
        ?string $params = null,
        ?string $stat = null
    ): array {
        $pdo = DB::connection()->getPdo();

        $stmt = $pdo->prepare("
            SET NOCOUNT ON;

            EXEC {$this->sprocName}
                @mode      = ?,
                @stat      = ?,
                @emp       = ?,
                @startdate = ?,
                @enddate   = ?,
                @date      = ?,
                @userid    = ?,
                @cutoff    = ?,
                @params    = ?
        ");

        $stmt->execute([
            $mode,
            $stat,
            $empNo,
            $startDate,
            $endDate,
            $date,
            $userId,
            $cutoff,
            $params,
        ]);

        $rows = [];

        do {
            if ($stmt->columnCount() > 0) {
                $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
                break;
            }
        } while ($stmt->nextRowset());

        $stmt->closeCursor();

        return $rows;
    }

    private function executeDtrStatement(
        string $mode,
        ?string $empNo = null,
        ?string $startDate = null,
        ?string $endDate = null,
        ?string $date = null,
        ?string $userId = null,
        ?string $cutoff = null,
        ?string $params = null,
        ?string $stat = null
    ): void {
        DB::statement("
            EXEC {$this->sprocName}
                @mode      = :mode,
                @stat      = :stat,
                @emp       = :emp,
                @startdate = :startdate,
                @enddate   = :enddate,
                @date      = :date,
                @userid    = :userid,
                @cutoff    = :cutoff,
                @params    = :params
        ", [
            'mode'      => $mode,
            'stat'      => $stat,
            'emp'       => $empNo,
            'startdate' => $startDate,
            'enddate'   => $endDate,
            'date'      => $date,
            'userid'    => $userId,
            'cutoff'    => $cutoff,
            'params'    => $params,
        ]);
    }

    private function extractJsonDataPayload(Request $request): array
    {
        $payload = $request->input('json_data', $request->json('json_data'));

        if (is_string($payload)) {
            $decoded = json_decode($payload, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \Exception('Invalid JSON data format.');
            }

            $payload = $decoded;
        }

        if (is_array($payload) && isset($payload['json_data']) && is_array($payload['json_data'])) {
            return $payload['json_data'];
        }

        if (is_array($payload)) {
            return $payload;
        }

        $raw = $request->json()->all();

        if (isset($raw['json_data']) && is_array($raw['json_data'])) {
            return $raw['json_data'];
        }

        if (is_array($raw) && !empty($raw)) {
            return $raw;
        }

        throw new \Exception('json_data is required.');
    }

    private function unwrapJsonResult(array $rows): array
    {
        if (count($rows) === 1) {
            $row = $rows[0];

            if (isset($row['result'])) {
                $decoded = json_decode((string) $row['result'], true);
                return is_array($decoded) ? $decoded : [];
            }

            if (isset($row['RESULT'])) {
                $decoded = json_decode((string) $row['RESULT'], true);
                return is_array($decoded) ? $decoded : [];
            }
        }

        return $rows;
    }

    private function getUserId(Request $request, ?string $fallback = null): string
    {
        $user = $request->user();

        return (string) (
            $request->input('userId')
            ?? $user->empno
            ?? $user->userid
            ?? $user->id
            ?? $fallback
            ?? 'SYSTEM'
        );
    }

    private function validateFaceIoVerification(Request $request, array $item, array $detail): void
    {
        $facialId = trim((string) ($detail['faceioFacialId'] ?? ''));

        if ($facialId === '') {
            return;
        }

        $empNo = trim((string) ($detail['empNo'] ?? $item['empNo'] ?? ''));
        $payloadEmpNo = trim((string) ($detail['faceioPayloadEmpNo'] ?? ''));

        $requestTenantCode = $this->normalizeTenantCode(
            $detail['faceioTenantCode'] ?? $request->header('X-Tenant-Code') ?? $request->input('tenantCode')
        );
        $payloadTenantCode = $this->normalizeTenantCode($detail['faceioPayloadTenantCode'] ?? null);

        if ($empNo === '') {
            throw new \Exception('Employee number is required for FACEIO validation.');
        }

        if ($payloadEmpNo !== '' && strcasecmp($payloadEmpNo, $empNo) !== 0) {
            throw new \Exception('FACEIO verification employee mismatch.');
        }

        $employee = DB::table('paymast')
            ->select('empno', 'faceio_facial_id')
            ->where('empno', $empNo)
            ->first();

        if (!$employee) {
            throw new \Exception('Employee not found for FACEIO validation.');
        }

        $savedFacialId = trim((string) ($employee->faceio_facial_id ?? ''));

        if ($savedFacialId === '') {
            throw new \Exception('Employee has no saved FACEIO enrollment.');
        }

        if (strcasecmp($savedFacialId, $facialId) !== 0) {
            Log::warning('FACEIO facial ID mismatch on timekeeping.', [
                'empNo' => $empNo,
                'providedFacialId' => $facialId,
                'savedFacialId' => $savedFacialId,
                'requestTenantCode' => $requestTenantCode,
                'payloadTenantCode' => $payloadTenantCode,
            ]);

            throw new \Exception('FACEIO verification does not match the logged-in employee enrollment.');
        }

        if (
            $requestTenantCode !== null
            && $payloadTenantCode !== null
            && strcasecmp($requestTenantCode, $payloadTenantCode) !== 0
        ) {
            throw new \Exception('FACEIO verification tenant mismatch.');
        }
    }

    private function normalizeTenantCode($value): ?string
    {
        $value = trim((string) ($value ?? ''));
        return $value === '' ? null : $value;
    }

    private function generateImageId(): string
    {
        $result = DB::select('EXEC sproc_php_auto_newid');

        if (empty($result) || !isset($result[0]->new_id)) {
            throw new \Exception('Failed to generate a new image ID from the database.');
        }

        return (string) $result[0]->new_id;
    }

    private function saveInlineBase64ImageIfPresent(array &$detail, string $prefix, string $branchCode, string $empNo): void
    {
        $base64Key = $prefix.'ImageBase64';
        $idKey     = $prefix.'ImageId';
        $pathKey   = $prefix.'ImagePath';

        if (empty($detail[$base64Key])) {
            return;
        }

        $imageId = $this->generateImageId();
        $path    = $this->saveImageToPublicImages($imageId, $detail[$base64Key], $branchCode, $empNo);

        $detail[$idKey]   = $imageId;
        $detail[$pathKey] = $path;

        unset($detail[$base64Key]);
    }

    private function saveImageToPublicImages(string $imageId, string $imageData, string $branchCode = 'NO_BRANCH', string $empNo = 'NO_EMPLOYEE'): string
    {
        if (!preg_match('/^data:image\/(\w+);base64,/', $imageData, $matches)) {
            throw new \Exception('Invalid image data format.');
        }

        $extension = strtolower($matches[1]);
        $extension = $extension === 'jpeg' ? 'jpg' : $extension;

        $allowedExtensions = ['jpg', 'png', 'webp'];

        if (!in_array($extension, $allowedExtensions, true)) {
            throw new \Exception('Invalid image type.');
        }

        $imageBinary = base64_decode(substr($imageData, strpos($imageData, ',') + 1));

        if ($imageBinary === false) {
            throw new \Exception('Failed to decode image data.');
        }

        $branchCode = $this->safeFolderName($branchCode, 'NO_BRANCH');
        $empNo      = $this->safeFolderName($empNo, 'NO_EMPLOYEE');

        $path     = "timekeeping_images/{$branchCode}/{$empNo}/{$imageId}.{$extension}";
        $fullPath = public_path('images/'.$path);

        if (!is_dir(dirname($fullPath))) {
            mkdir(dirname($fullPath), 0755, true);
        }

        if (file_put_contents($fullPath, $imageBinary) === false) {
            throw new \Exception('Failed to write image file.');
        }

        return $path;
    }

    private function safeFolderName(string $value, string $default): string
    {
        $value = strtoupper(trim($value));
        $value = preg_replace('/[^A-Z0-9_-]/', '_', $value);

        return $value === '' ? $default : $value;
    }
}
