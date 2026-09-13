<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class EmployeeShiftController extends Controller
{
    private const SPROC = 'dbo.sproc_PHP_EmpInq_EmployeeShift';

public function employeeShifts(Request $request)
{
    /*
    |--------------------------------------------------------------------------
    | TEMP DEBUG - MUST BE BEFORE VALIDATION
    |--------------------------------------------------------------------------
    */
    Log::info('Employee Shift RAW Request', [
        'method' => $request->method(),
        'content_type' => $request->header('Content-Type'),
        'all' => $request->all(),
        'raw' => $request->getContent(),
    ]);

    $validated = $request->validate([
        'EMP_NO' => 'required|string',
        'START_DATE' => 'required|date_format:Y-m-d',
        'END_DATE' => 'required|date_format:Y-m-d|after_or_equal:START_DATE',
        'VIEW' => 'nullable|string|in:MY,EMPLOYEE',
        'HR_FLAG' => 'nullable|string|in:Y,N,y,n,1,0',
        'APPROVER' => 'nullable|string|in:Y,N,y,n,1,0',
    ]);

    $bindings = [
        trim($validated['EMP_NO']),
        $validated['START_DATE'],
        $validated['END_DATE'],
        strtoupper($validated['VIEW'] ?? 'MY'),
        strtoupper($validated['HR_FLAG'] ?? 'N'),
        strtoupper($validated['APPROVER'] ?? 'N'),
    ];

    return $this->query('Inquiry', $bindings);
}

    public function shiftCodes()
    {
        return $this->query('ShiftCodes');
    }

    public function employeeShiftTemplateData(Request $request)
    {
        $request->validate([
            'EMP_NO' => 'required|string',
        ]);

        return $this->query('TemplateData');
    }

    public function uploadEmployeeShifts(Request $request)
    {
        $validated = $request->validate([
            'empNo' => 'required|string',
            'detail' => 'required|array|min:1',

            // Only these six fields are accepted from Sheet1.
            'detail.*.empNo' => 'required|string',
            'detail.*.cutOff' => 'required|string',
            'detail.*.date' => 'required|date_format:Y-m-d',
            'detail.*.rd' => 'nullable|string|max:1',
            'detail.*.shiftCode' => 'nullable|string',
            'detail.*.workHrs' => 'required|numeric|min:0',
        ]);

        try {
            DB::statement('EXEC '.self::SPROC.' @mode = ?, @params = ?', [
                'Upload',
                json_encode(['json_data' => $validated], JSON_UNESCAPED_SLASHES),
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'Employee shifts uploaded successfully.',
                'count' => count($validated['detail']),
            ]);
        } catch (\Throwable $e) {
            return $this->failure('Employee shift upload failed.', $e);
        }
    }

    public function upsertShiftChange(Request $request)
    {
        $data = $request->input('json_data');
        if (!is_array($data)) {
            return response()->json(['status' => 'error', 'message' => 'json_data must be an object.'], 422);
        }

        $validated = validator($data, [
            'empNo' => 'required|string',
            'detail' => 'required|array|min:1',
            'detail.*.shiftDate' => 'required|date_format:Y-m-d',
            'detail.*.changeShiftType' => 'required|string|in:Duty,Rest Day',
            'detail.*.currentShiftCode' => 'nullable|string',
            'detail.*.requestedShiftCode' => 'nullable|string|required_if:detail.*.changeShiftType,Duty',
            'detail.*.remarks' => 'required|string',
        ])->validate();

        try {
            DB::statement('EXEC '.self::SPROC.' @mode = ?, @params = ?', [
                'upsert',
                json_encode(['json_data' => $validated], JSON_UNESCAPED_SLASHES),
            ]);

            return response()->json(['status' => 'success', 'message' => 'Shift change submitted successfully.']);
        } catch (\Throwable $e) {
            return $this->failure('Shift change submission failed.', $e);
        }
    }

    public function shiftChangeApprovalInquiry(Request $request)
    {
        $validated = $request->validate(['EMP_NO' => 'required|string']);
        return $this->query('ApprInq', [$validated['EMP_NO']]);
    }

    public function shiftChangeApprovalHistory(Request $request)
    {
        $validated = $request->validate([
            'EMP_NO' => 'required|string',
            'START_DATE' => 'required|date_format:Y-m-d',
            'END_DATE' => 'required|date_format:Y-m-d|after_or_equal:START_DATE',
        ]);

        return $this->query('ApprHistory', [
            $validated['EMP_NO'],
            $validated['START_DATE'],
            $validated['END_DATE'],
        ]);
    }

    public function approvalShiftChange(Request $request)
    {
        $json = $request->input('json_data');
        if (!is_string($json)) {
            return response()->json(['status' => 'error', 'message' => 'json_data must be a JSON string.'], 422);
        }

        $data = json_decode($json, true);
        if (json_last_error() !== JSON_ERROR_NONE || !isset($data['json_data']) || !is_array($data['json_data'])) {
            return response()->json(['status' => 'error', 'message' => 'Invalid JSON data format.'], 422);
        }

        validator($data['json_data'], [
            'shiftChangeStamp' => 'required',
            'appStat' => 'required|in:0,1',
            'appUser' => 'required|string',
            'appRemarks' => 'nullable|string',
        ])->validate();

        try {
            DB::statement('EXEC '.self::SPROC.' @mode = ?, @params = ?', [
                'Approval',
                json_encode($data['json_data'], JSON_UNESCAPED_SLASHES),
            ]);

            return response()->json(['status' => 'success', 'message' => 'Shift change approval processed successfully.']);
        } catch (\Throwable $e) {
            return $this->failure('Shift change approval failed.', $e);
        }
    }

private function query(string $mode, array $bindings = [])
{
    try {

        switch ($mode) {

            case 'Inquiry':
                $sql = '
                    EXEC '.self::SPROC.'
                        @mode = ?,
                        @params = NULL,
                        @emp = ?,
                        @startdate = ?,
                        @enddate = ?,
                        @view = ?,
                        @hrflag = ?,
                        @approver = ?
                ';
                break;

            case 'ApprInq':
                $sql = '
                    EXEC '.self::SPROC.'
                        @mode = ?,
                        @params = NULL,
                        @emp = ?
                ';
                break;

            case 'ApprHistory':
                $sql = '
                    EXEC '.self::SPROC.'
                        @mode = ?,
                        @params = NULL,
                        @emp = ?,
                        @startdate = ?,
                        @enddate = ?
                ';
                break;

            case 'TemplateData':
            case 'ShiftCodes':
                $sql = '
                    EXEC '.self::SPROC.'
                        @mode = ?,
                        @params = NULL
                ';
                break;

            default:
                throw new \Exception(
                    "Unsupported query mode: {$mode}"
                );
        }

        $params = array_merge([$mode], $bindings);

        Log::info('Employee Shift SP Request', [
            'mode' => $mode,
            'bindings' => $params,
        ]);

        $result = DB::select($sql, $params);

        /*
        |--------------------------------------------------------------------------
        | INQUIRY
        | Stored procedure returns normal SQL rows.
        |--------------------------------------------------------------------------
        */
        if ($mode === 'Inquiry') {

            Log::info('Employee Shift Inquiry Result', [
                'rowCount' => count($result),
            ]);

            return response()->json([
                'status' => 'success',
                'success' => true,
                'data' => array_map(
                    static fn ($row) => (array) $row,
                    $result
                ),
            ]);
        }

        /*
        |--------------------------------------------------------------------------
        | Other modes currently return JSON in column [result]
        |--------------------------------------------------------------------------
        */
        if (empty($result)) {
            return response()->json([
                'status' => 'success',
                'success' => true,
                'data' => [],
            ]);
        }

        $row = $result[0];

        $raw = $row->result
            ?? $row->RESULT
            ?? $row->Result
            ?? null;

        if (is_resource($raw)) {
            rewind($raw);
            $raw = stream_get_contents($raw);
        }

        if ($raw === null || $raw === '') {
            return response()->json([
                'status' => 'success',
                'success' => true,
                'data' => [],
            ]);
        }

        if (is_string($raw)) {

            $data = json_decode($raw, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \Exception(
                    'Invalid JSON returned by stored procedure: ' .
                    json_last_error_msg()
                );
            }

        } else {
            $data = $raw;
        }

        return response()->json([
            'status' => 'success',
            'success' => true,
            'data' => $data ?? [],
        ]);

    } catch (\Throwable $e) {

        Log::error('Employee Shift Query Failed', [
            'mode' => $mode,
            'bindings' => $bindings,
            'error' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
        ]);

        return $this->failure(
            'Unable to retrieve employee shift data.',
            $e
        );
    }
}

    private function failure(string $message, \Throwable $e)
    {
        Log::error($message, ['error' => $e->getMessage()]);
        return response()->json(['status' => 'error', 'success' => false, 'message' => $message], 500);
    }
}