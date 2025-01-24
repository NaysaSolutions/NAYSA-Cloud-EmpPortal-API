<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class OvertimeController extends Controller
{
  



// ** OT Approval Inquiry Current
public function getApprInq(Request $request) {

    $request->validate([
        'EMP_NO' => 'required|string',
    ]);

    $stat = $request->input('STAT');
    $employee_no = $request->input('EMP_NO');


    try {
        $results = DB::select(
            'EXEC sproc_PHP_EmpInq_Overtime @mode = ?, @stat = ?, @emp =?',
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





 // ** OT Approval Inquiry HIstory
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
            'EXEC sproc_PHP_EmpInq_Overtime @mode = ?, @startdate = ?, @enddate =?, @emp =?',
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












// ** OT Application Inquiry Current
public function getAppInq(Request $request) {

    $request->validate([
        'EMP_NO' => 'required|string',
    ]);

    $stat = $request->input('STAT');
    $employee_no = $request->input('EMP_NO');


    try {
        $results = DB::select(
            'EXEC sproc_PHP_EmpInq_Overtime @mode = ?, @emp =?',
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











 // ** OT Application Inquiry History
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
            'EXEC sproc_PHP_EmpInq_Overtime @mode = ?, @startdate = ?, @enddate =?, @emp =?',
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
        $request->validate([
            'json_data' => 'required|json',
        ]);

        $params = $request->get('json_data');

      

        if (json_last_error() !== JSON_ERROR_NONE) {
            return response()->json([
                'status' => 'error',
                'message' => 'Invalid JSON data provided.',
            ], 400);
        }


        DB::statement('EXEC sproc_PHP_EmpInq_Overtime @params = :json_data, @mode = :mode', [
            'json_data' => $params,
            'mode' => 'upsert'
        ]);


        return response()->json([
            'status' => 'success',
            'message' => 'Transaction saved successfully.',
        ], 200);
    } catch (\Exception $e) {
        Log::error('Transaction save failed:', ['error' => $e->getMessage()]);

        return response()->json([
            'status' => 'error',
            'message' => 'Failed to save transaction: ' . $e->getMessage(),
        ], 500);
    }
}




public function approval(Request $request)
{
    try {
        $request->validate([
            'json_data' => 'required|json',
        ]);

        $params = $request->get('json_data');

      

        if (json_last_error() !== JSON_ERROR_NONE) {
            return response()->json([
                'status' => 'error',
                'message' => 'Invalid JSON data provided.',
            ], 400);
        }


        DB::statement('EXEC sproc_PHP_EmpInq_Overtime @params = :json_data, @mode = :mode', [
            'json_data' => $params,
            'mode' => 'approval'
        ]);


        return response()->json([
            'status' => 'success',
            'message' => 'Transaction saved successfully.',
        ], 200);
    } catch (\Exception $e) {
        Log::error('Transaction save failed:', ['error' => $e->getMessage()]);

        return response()->json([
            'status' => 'error',
            'message' => 'Failed to save transaction: ' . $e->getMessage(),
        ], 500);
    }
}



};