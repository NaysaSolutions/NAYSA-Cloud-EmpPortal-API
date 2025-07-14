<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Hash;
use Illuminate\Http\JsonResponse;

class ReportController extends Controller
{
    public function payslipReport(Request $request): JsonResponse
    {
        $empno = $request->query('empno');
        $cutoff = $request->query('cutoff');

        if (!$empno || !$cutoff) {
            return response()->json([
                'success' => false,
                'message' => 'Missing empno or cutoff parameter.'
            ], 400);
        }

        // Get detailed payslip items
        $transactions = DB::table('Vw_vbnet_Rpt_Payslip')
            ->select('TRANS_CODE', 'DESCRIP', 'HOURS', 'AMOUNT')
            ->where('EMPNO', $empno)
            ->where('CUT_OFF', $cutoff)
            ->orderBy('TRANS_CODE')
            ->get();

        // Group earnings & deductions
        $earnings = $transactions->filter(fn($t) => str_starts_with($t->TRANS_CODE, 'E'));
        $deductions = $transactions->filter(fn($t) => str_starts_with($t->TRANS_CODE, 'D'));

        $totalEarnings = $earnings->sum('AMOUNT');
        $totalDeductions = $deductions->sum('AMOUNT');
        $netPay = $totalEarnings - $totalDeductions;

        // Get employee & cutoff info
        $empInfo = DB::table('Vw_vbnet_Rpt_Payslip')
            ->where('EMPNO', $empno)
            ->where('CUT_OFF', $cutoff)
            ->select(
                'EMPNO', 'EMP_NAME', 'POSITION', 'BRANCHNAME', 'ORG_NAME','COMP_NAME',
                'EMP_STAT', 'PAY_GROUP', 'FREQUENCY', 'CUT_OFF', 'CUTOFFNAME', 'MONTH'
            )
            ->first();

        if (!$empInfo) {
            return response()->json([
                'success' => false,
                'message' => 'Payslip not found for given employee and cutoff.'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'employee' => $empInfo,
            'earnings' => $earnings->values(),
            'deductions' => $deductions->values(),
            'total_earnings' => $totalEarnings,
            'total_deductions' => $totalDeductions,
            'net_pay' => $netPay,
        ]);
    }


    public function payslipReport_LV(Request $request): JsonResponse
{
    $empno = $request->query('empno');
    $cutoff = $request->query('cutoff');

    if (!$empno || !$cutoff) {
        return response()->json([
            'success' => false,
            'message' => 'Missing empno or cutoff parameter.'
        ], 400);
    }

    $emplvInfo = DB::table('Vw_vbnet_Rpt_LVBal')
        ->where('EMPNO', $empno)
        ->where('CUT_OFF', $cutoff)
        ->select('LV_TYPE', 'AVAILED_HRS', 'ENDBAL_HRS')
        ->orderBy('LV_TYPE')
        ->get();

    if ($emplvInfo->isEmpty()) {
        return response()->json([
            'success' => false,
            'message' => 'No leave balance found for this employee and cutoff.'
        ], 404);
    }

    return response()->json([
        'success' => true,
        'employeelv' => $emplvInfo,
    ]);
}


    public function payslipReport_LN(Request $request): JsonResponse
{
    $empno = $request->query('empno');
    $cutoff = $request->query('cutoff');

    if (!$empno || !$cutoff) {
        return response()->json([
            'success' => false,
            'message' => 'Missing empno or cutoff parameter.'
        ], 400);
    }

    $emplnInfo = DB::table('Vw_vbnet_Rpt_LNBal')
        ->where('EMPNO', $empno)
        ->where('CUT_OFF', $cutoff)
        ->where('CUTOFF_CURR', $cutoff)
        ->select('LOAN_DESC', 'LOAN_AMT', 'LOAN_BAL','TOTAL_PAID')
        ->orderBy('LOAN_DESC')
        ->get();

    if ($emplnInfo->isEmpty()) {
        return response()->json([
            'success' => false,
            'message' => 'No loan balance found for this employee and cutoff.'
        ], 404);
    }

    return response()->json([
        'success' => true,
        'employeeln' => $emplnInfo,
    ]);
}

    public function payslipReport_YTD(Request $request): JsonResponse
{
    $empno = $request->query('empno');
    $cutoff = $request->query('cutoff');

    if (!$empno || !$cutoff) {
        return response()->json([
            'success' => false,
            'message' => 'Missing empno or cutoff parameter.'
        ], 400);
    }

    $empytdInfo = DB::table('Vw_vbnet_Rpt_YTDBal')
        ->where('EMPNO', $empno)
        ->where('CUT_OFF', $cutoff)
        ->select('YTD_GROSS', 'YTD_TAXABLE', 'YTD_TAX','YTD_SSS','YTD_HDMF','YTD_MED')
        ->orderBy('CUT_OFF')
        ->get();

    if ($empytdInfo->isEmpty()) {
        return response()->json([
            'success' => false,
            'message' => 'No YTD balance found for this employee and cutoff.'
        ], 404);
    }

    return response()->json([
        'success' => true,
        'employeeytd' => $empytdInfo,
    ]);
}


    public function payslipReport_Cutoff(Request $request): JsonResponse
{
    $empno = $request->query('empno');

    if (!$empno) {
        return response()->json([
            'success' => false,
            'message' => 'Missing empno parameter.'
        ], 400);
    }

    $empCutoffInfo = DB::table('Vw_vbnet_Rpt_Payslip')
        ->where('EMPNO', $empno)
        ->select('CUT_OFF', 'CUTOFFNAME')
        ->groupBy('CUT_OFF', 'CUTOFFNAME')
        ->orderBy('CUT_OFF')
        ->get();

    if ($empCutoffInfo->isEmpty()) {
        return response()->json([
            'success' => false,
            'message' => 'No Cutoff found for this employee.'
        ], 404);
    }

    return response()->json([
        'success' => true,
        'employeecutoff' => $empCutoffInfo,
    ]);
}


}
