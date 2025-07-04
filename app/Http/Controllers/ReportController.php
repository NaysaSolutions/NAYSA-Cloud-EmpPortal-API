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
            ->select('TRANS_CODE', 'DESCRIP', 'AMOUNT')
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
}
