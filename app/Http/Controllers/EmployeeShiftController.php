<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class EmployeeShiftController extends Controller
{
    private const SPROC = 'sproc_PHP_EmpInq_EmployeeShift';

    public function employeeShifts(Request $request)
    {
        $validated = $request->validate([
            'EMP_NO' => 'required|string',
            'START_DATE' => 'required|date_format:Y-m-d',
            'END_DATE' => 'required|date_format:Y-m-d|after_or_equal:START_DATE',
            'ALL' => 'nullable|string|in:Y,N,y,n',
        ]);

        return $this->query('Inquiry', [
            $validated['EMP_NO'],
            $validated['START_DATE'],
            $validated['END_DATE'],
            strtoupper($validated['ALL'] ?? 'N'),
        ]);
    }

    public function shiftCodes()
    {
        return $this->query('ShiftCodes');
    }

    public function uploadEmployeeShifts(Request $request)
    {
        $validated = $request->validate([
            'empNo' => 'required|string',
            'detail' => 'required|array|min:1',
            'detail.*.empNo' => 'required|string',
            'detail.*.shiftDate' => 'required|date_format:Y-m-d',
            'detail.*.shiftCode' => 'nullable|string',
            'detail.*.rd' => 'nullable',
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
            $sql = 'EXEC '.self::SPROC.' @mode = ?';
            if ($mode === 'Inquiry') {
                $sql .= ', @emp = ?, @startdate = ?, @enddate = ?, @all = ?';
            } elseif ($mode === 'ApprInq') {
                $sql .= ', @emp = ?';
            } elseif ($mode === 'ApprHistory') {
                $sql .= ', @emp = ?, @startdate = ?, @enddate = ?';
            }

            $result = DB::select($sql, array_merge([$mode], $bindings));
            $raw = $result[0]->result ?? null;
            $data = is_string($raw) ? (json_decode($raw, true) ?: []) : ($raw ?: $result);

            return response()->json(['status' => 'success', 'success' => true, 'data' => $data]);
        } catch (\Throwable $e) {
            return $this->failure('Unable to retrieve employee shift data.', $e);
        }
    }

    private function failure(string $message, \Throwable $e)
    {
        Log::error($message, ['error' => $e->getMessage()]);
        return response()->json(['status' => 'error', 'success' => false, 'message' => $message], 500);
    }
}
