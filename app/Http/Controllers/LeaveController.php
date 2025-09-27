<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class LeaveController extends Controller
{
    
// ** Leave Approval Inquiry Current
public function getApprInq(Request $request) {

    $request->validate([
        'EMP_NO' => 'required|string',
    ]);

    $stat = $request->input('STAT');
    $employee_no = $request->input('EMP_NO');


    try {
        $results = DB::select(
            'EXEC sproc_PHP_EmpInq_Leave @mode = ?, @stat = ?, @emp =?',
            ['ApprInq', $stat, $employee_no] 
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





 // ** Leave Approval Inquiry HIstory
public function getApprHistory(Request $request) {

    $request->validate([
        'EMP_NO' => 'required|string',
        'START_DATE' => 'required|date_format:Y-m-d',
        'END_DATE' => 'required|date_format:Y-m-d',
    ]);

    $employee_no = $request->input('EMP_NO');
    $start_date = $request->input('START_DATE');
    $end_date = $request->input('END_DATE');

    try {
        $results = DB::select(
            'EXEC sproc_PHP_EmpInq_Leave @mode = ?, @startdate = ?, @enddate =?, @emp =?',
            ['ApprHistory', $start_date, $end_date, $employee_no] 
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


// ** Leave Application Inquiry Current
public function getAppInq(Request $request) {

    $request->validate([
        'EMP_NO' => 'required|string',
    ]);

    $stat = $request->input('STAT');
    $employee_no = $request->input('EMP_NO');


    try {
        $results = DB::select(
            'EXEC sproc_PHP_EmpInq_Leave @mode = ?, @emp =?',
            ['AppInq', $employee_no] 
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


 // ** Leave Application Inquiry History
public function getAppHistory(Request $request) {

    $request->validate([
        'EMP_NO' => 'required|string',
        'START_DATE' => 'required|date_format:Y-m-d',
        'END_DATE' => 'required|date_format:Y-m-d',
    ]);

    $employee_no = $request->input('EMP_NO');
    $start_date = $request->input('START_DATE');
    $end_date = $request->input('END_DATE');

    try {
        $results = DB::select(
            'EXEC sproc_PHP_EmpInq_Leave @mode = ?, @startdate = ?, @enddate =?, @emp =?',
            ['AppHistory', $start_date, $end_date, $employee_no] 
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


public function upsert(Request $request)
    {
        try {
            $data = $request->json('json_data');
            $empNo = $data['empNo'] ?? null;
            $details = $data['detail'] ?? [];

            if (!$empNo || empty($details)) {
                return response()->json(['status' => 'error', 'message' => 'Invalid data provided'], 400);
            }

            $jsonParams = json_encode(['json_data' => $data]);
            
            DB::statement("EXEC sproc_PHP_EmpInq_Leave @mode = 'upsert', @params = ?", [$jsonParams]);

            return response()->json(['status' => 'success', 'message' => 'Leave application submitted successfully']);
        } catch (\Exception $e) {
            Log::error('Error in upsertLV: ' . $e->getMessage());
            return response()->json(['status' => 'error', 'message' => 'An error occurred while processing the request'], 500);
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
                    'status' => 'error',
                    'message' => 'Invalid JSON data format.',
                ], 400);
            }
    
            // Convert json_data to a JSON string for SQL execution
            $jsonParams = json_encode($data['json_data']);
    
            // Log the formatted data for debugging
            Log::info('Approval request sent:', ['json_data' => $jsonParams]);
    
            // Execute the stored procedure
            DB::statement("EXEC sproc_PHP_EmpInq_Leave @mode = 'Approval', @params = ?", [$jsonParams]);
    
            return response()->json([
                'status' => 'success',
                'message' => 'Leave approval processed successfully',
            ]);
        } catch (\Exception $e) {
            Log::error('Error in approval process:', ['error' => $e->getMessage()]);
    
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to process approval: ' . $e->getMessage(),
            ], 500);
        }
    }
    

// ** Leave Types
public function leaveTypes(Request $request) {

    $request->validate([
        'EMP_NO' => 'required|string',
    ]);

    $employee_no = $request->input('EMP_NO');


    try {
        $results = DB::select(
            'EXEC sproc_PHP_EmpInq_Leave @mode = ?, @emp =?',
            ['leaveTypes', $employee_no]
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


public function cancel(Request $request)
{
    try {
        // accept from json body or form urlencoded just in case
        $payload = $request->input('json_data', $request->json('json_data'));

        $empNo   = $payload['empNo']   ?? null;
        $stamp   = $payload['lvStamp'] ?? null;

        if (!$empNo || !$stamp) {
            return response()->json([
                'status'  => 'error',
                'message' => 'empNo and Stamp are required'
            ], 400);
        }

        $jsonParams = json_encode([
            'empNo'   => $empNo,
            'lvStamp' => $stamp,
        ], JSON_UNESCAPED_SLASHES);

        // EXEC sproc_PHP_EmpInq_Overtime @mode='Cancel', @params='{"empNo":"...","otStamp":"..."}'
        DB::statement("EXEC sproc_PHP_EmpInq_Leave @mode = 'Cancel', @params = ?", [$jsonParams]);

        return response()->json([
            'status'  => 'success',
            'message' => 'Leave cancelled successfully'
        ]);
    } catch (\Throwable $e) {
        Log::error('Error in cancelLeave: '.$e->getMessage());
        return response()->json([
            'status'  => 'error',
            'message' => 'An error occurred while processing the request'
        ], 500);
    }
}

};
