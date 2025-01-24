<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;


class DashBoardController extends Controller
{
    
 
// ** Leave Approval Inquiry Current
public function index(Request $request) {

    $request->validate([
        'EMP_NO' => 'required|string',
    ]);

    $employee_no = $request->input('EMP_NO');


    try {
        $results = DB::select(
            'EXEC sproc_PHP_EmpInq_SummInfo  @emp =?',
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



}
