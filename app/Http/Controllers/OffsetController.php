<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class OffsetController extends Controller
{


public function upsert(Request $request)
    {
        try {

            $data = $request->json('json_data');

            if (!$data || empty($data['empNo']) || empty($data['offsetDate']) || empty($data['offsetHrs'])) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Required fields are missing.'
                ], 400);
            }

            $jsonParams = json_encode([
                'json_data' => $data
            ]);

            // Capture returned offsetStamp
            $result = DB::select(
                "EXEC sproc_PHP_EmpInq_Offset @mode = ?, @params = ?",
                ['upsert', $jsonParams]
            );

            return response()->json([
                'status' => 'success',
                'message' => 'Offset application submitted successfully.',
                'offsetStamp' => $result[0]->offsetStamp ?? null
            ]);

        } catch (\Exception $e) {

            Log::error('Offset Upsert Error: ' . $e->getMessage());

            return response()->json([
                'status' => 'error',
                'message' => 'An error occurred while processing the request.'
            ], 500);
        }
    }

    public function cancel(Request $request)
{
    try {

        // Accept JSON body safely
        $payload = $request->input('json_data', $request->json('json_data'));

        $empNo = $payload['empNo'] ?? null;
        $stamp = $payload['offsetStamp'] ?? null;

        if (!$empNo || !$stamp) {
            return response()->json([
                'status'  => 'error',
                'message' => 'empNo and offsetStamp are required'
            ], 400);
        }

        $jsonParams = json_encode([
            'empNo'      => $empNo,
            'offsetStamp'=> $stamp,
        ], JSON_UNESCAPED_SLASHES);

        DB::statement(
            "EXEC sproc_PHP_EmpInq_Offset @mode = ?, @userid = ?, @params = ?",
            ['Cancel', auth()->user()->empNo ?? null, $jsonParams]
        );

        return response()->json([
            'status'  => 'success',
            'message' => 'Offset application cancelled successfully.'
        ]);

    } catch (\Throwable $e) {

        Log::error('Error in cancelOffset: '.$e->getMessage());

        return response()->json([
            'status'  => 'error',
            'message' => 'An error occurred while processing the request.'
        ], 500);
    }
}

public function getAppHistory(Request $request)
{
    $validator = Validator::make($request->all(), [
        'EMP_NO'     => 'required|string',
        'START_DATE' => 'required|date_format:Y-m-d',
        'END_DATE'   => 'required|date_format:Y-m-d',
    ]);

    if ($validator->fails()) {
        return response()->json([
            'status'  => 'error',
            'message' => $validator->errors()->first()
        ], 400);
    }

    $empNo     = $request->input('EMP_NO');
    $startDate = $request->input('START_DATE');
    $endDate   = $request->input('END_DATE');

    try {

        $jsonParams = json_encode([
            'empNo'     => $empNo,
            'startDate' => $startDate,
            'endDate'   => $endDate,
        ], JSON_UNESCAPED_SLASHES);

        $result = DB::select(
            "EXEC sproc_PHP_EmpInq_Offset @mode = ?, @params = ?",
            ['AppHistory', $jsonParams]
        );

        // SP returns JSON string in column "result"
        $decoded = [];

        if (!empty($result) && isset($result[0]->result)) {
            $decoded = json_decode($result[0]->result, true);
        }

        return response()->json([
            'status' => 'success',
            'data'   => $decoded
        ], 200);

    } catch (\Exception $e) {

        Log::error('Offset AppHistory Error: ' . $e->getMessage());

        return response()->json([
            'status'  => 'error',
            'message' => 'An error occurred while retrieving history.'
        ], 500);
    }
}

public function getDTROffset(Request $request, $empNo, $startDate, $endDate)
{
    try {

        Log::info('Fetching DTR Offset records.', [
            'empNo'     => $empNo,
            'startDate' => $startDate,
            'endDate'   => $endDate
        ]);

        $records = DB::select(
            "EXEC sproc_PHP_EmpInq_DTR
                @mode = ?,
                @emp = ?,
                @startdate = ?,
                @enddate = ?",
            [
                'get_DTR_Offset',
                $empNo,
                $startDate,
                $endDate
            ]
        );

        return response()->json([
            'success' => true,
            'message' => 'DTR Offset records fetched successfully.',
            'records' => $records
        ], 200);

    } catch (\Exception $e) {

        Log::error('Error fetching DTR Offset records:', [
            'message' => $e->getMessage(),
            'trace'   => $e->getTraceAsString(),
            'empNo'   => $empNo,
            'startDate' => $startDate,
            'endDate' => $endDate
        ]);

        return response()->json([
            'success' => false,
            'message' => 'Failed to fetch DTR Offset records.',
            'error_details' => $e->getMessage()
        ], 500);
    }
}

// ** Offset Approval Inquiry (Employee Side)
public function getOffsetApprInq(Request $request)
{
    $request->validate([
        'EMP_NO' => 'required|string',
        'STATUS' => 'nullable|string',
    ]);

    try {

        $params = json_encode([
            'empNo'  => $request->EMP_NO,
            'status' => $request->STATUS ?? null,
        ]);

        $results = DB::select(
            'EXEC sproc_PHP_EmpInq_Offset @mode = ?, @userid = ?, @params = ?',
            [
                'ApprInq',
                auth()->user()->user_id ?? null,
                $params
            ]
        );

        return response()->json([
            'success' => true,
            'data' => $results ? json_decode($results[0]->result ?? '[]') : [],
        ], 200);

    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => $e->getMessage(),
        ], 500);
    }
}

// ** Offset Approval History (Manager Side)
public function getOffsetApprHistory(Request $request)
{
    $request->validate([
        'EMP_NO'     => 'required|string',
        'START_DATE' => 'required|date_format:Y-m-d',
        'END_DATE'   => 'required|date_format:Y-m-d',
    ]);

    try {

        $params = json_encode([
            'empNo'     => $request->EMP_NO,
            'startDate' => $request->START_DATE,
            'endDate'   => $request->END_DATE,
        ]);

        $results = DB::select(
            'EXEC sproc_PHP_EmpInq_Offset @mode = ?, @userid = ?, @params = ?',
            [
                'ApprHistory',
                auth()->user()->user_id ?? null,
                $params
            ]
        );

        return response()->json([
            'success' => true,
            'data' => $results ? json_decode($results[0]->result ?? '[]') : [],
        ], 200);

    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => $e->getMessage(),
        ], 500);
    }
}

public function approval(Request $request)
{
    try {
        // Validate that json_data is a required string
        $request->validate([
            'json_data' => 'required|string',
        ]);

        // Decode the JSON string
        $jsonString = $request->input('json_data');
        $data = json_decode($jsonString, true);

        // Check if decoding was successful and contains 'json_data'
        if (json_last_error() !== JSON_ERROR_NONE || !isset($data['json_data'])) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Invalid JSON data format.',
            ], 400);
        }

        $jsonParams = json_encode($data['json_data']);

        Log::info('Offset Approval request sent:', ['json_data' => $jsonParams]);

        // Execute the stored procedure (Offset)
        DB::statement("EXEC sproc_PHP_EmpInq_Offset @mode = 'Approval', @userid = ?, @params = ?", [
            $request->user()->USER_CODE ?? $request->input('userid') ?? 'ADMIN', // adjust to your auth
            $jsonParams
        ]);

        return response()->json([
            'status'  => 'success',
            'message' => 'Offset approval processed successfully',
        ]);
    } catch (\Exception $e) {
        Log::error('Error in offset approval process:', ['error' => $e->getMessage()]);

        return response()->json([
            'status'  => 'error',
            'message' => 'Failed to process offset approval: ' . $e->getMessage(),
        ], 500);
    }
}

};
