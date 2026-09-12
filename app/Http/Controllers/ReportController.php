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
                'EMP_STAT', 'PAY_GROUP', 'FREQUENCY', 'CUT_OFF', 'CUTOFFNAME', 'MONTH', 
                'GROUP_NAME', 'FREQ_TYPE', 'BASIC', 'DAILY_RATE'
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
            'message' => 'Missing empno or cutoff parameter.',
            'employeelv' => []
        ], 400);
    }

    $emplvInfo = DB::table('Vw_vbnet_Rpt_LVBal')
        ->where('EMPNO', $empno)
        ->where('CUT_OFF', $cutoff)
        ->select('LV_TYPE', 'AVAILED_HRS', 'ENDBAL_HRS', 'AVAILED', 'ENDBAL')
        ->orderBy('LV_TYPE')
        ->get();

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
    $empno = trim((string) $request->query('empno'));

    if ($empno === '') {
        return response()->json([
            'success' => false,
            'message' => 'Missing empno parameter.',
            'employeecutoff' => [],
        ], 400);
    }

    $cutoffs = DB::table('Vw_vbnet_Rpt_Payslip')
        ->where('EMPNO', $empno)
        ->select([
            'CUT_OFF',
            'CUTOFFNAME',
        ])
        ->distinct()
        ->orderByDesc('CUT_OFF')
        ->get();

    return response()
        ->json([
            'success' => true,
            'employeecutoff' => $cutoffs,
        ])
        ->header('Cache-Control', 'private, max-age=300');
}

public function payslipReportRange(Request $request): JsonResponse
{
    $empno = trim((string) $request->query('empno'));
    $from   = trim((string) $request->query('from'));
    $to     = trim((string) $request->query('to'));

    if ($empno === '' || $from === '' || $to === '') {
        return response()->json([
            'success' => false,
            'message' => 'Missing empno, from, or to parameter.',
            'payslips' => [],
        ], 400);
    }

    /*
    |--------------------------------------------------------------------------
    | 1. Get valid cutoffs
    |--------------------------------------------------------------------------
    |
    | Do not rely only on BETWEEN because CUT_OFF may be a varchar.
    | Get the actual payroll periods belonging to this employee/range.
    |
    */

    $allCutoffs = DB::table('Vw_vbnet_Rpt_Payslip')
        ->where('EMPNO', $empno)
        ->select('CUT_OFF', 'CUTOFFNAME')
        ->distinct()
        ->orderBy('CUT_OFF')
        ->get();

    if ($allCutoffs->isEmpty()) {
        return response()->json([
            'success' => true,
            'payslips' => [],
        ]);
    }

    $fromIndex = $allCutoffs->search(
        fn ($row) => (string) $row->CUT_OFF === $from
    );

    $toIndex = $allCutoffs->search(
        fn ($row) => (string) $row->CUT_OFF === $to
    );

    if ($fromIndex === false || $toIndex === false || $fromIndex > $toIndex) {
        return response()->json([
            'success' => false,
            'message' => 'Invalid payroll cutoff range.',
            'payslips' => [],
        ], 422);
    }

    $selectedCutoffs = $allCutoffs
        ->slice($fromIndex, ($toIndex - $fromIndex) + 1)
        ->values();

    $cutoffCodes = $selectedCutoffs
        ->pluck('CUT_OFF')
        ->map(fn ($value) => (string) $value)
        ->values()
        ->all();

    /*
    |--------------------------------------------------------------------------
    | 2. Payslip
    |--------------------------------------------------------------------------
    |
    | One query for ALL selected cutoffs.
    |
    */

    $transactions = DB::table('Vw_vbnet_Rpt_Payslip')
        ->where('EMPNO', $empno)
        ->whereIn('CUT_OFF', $cutoffCodes)
        ->select([
            'EMPNO',
            'EMP_NAME',
            'POSITION',
            'BRANCHNAME',
            'ORG_NAME',
            'COMP_NAME',
            'EMP_STAT',
            'PAY_GROUP',
            'FREQUENCY',
            'CUT_OFF',
            'CUTOFFNAME',
            'MONTH',
            'GROUP_NAME',
            'FREQ_TYPE',
            'BASIC',
            'DAILY_RATE',

            'TRANS_CODE',
            'DESCRIP',
            'HOURS',
            'AMOUNT',
        ])
        ->orderBy('CUT_OFF')
        ->orderBy('TRANS_CODE')
        ->get();

    /*
    |--------------------------------------------------------------------------
    | 3. Leave balances
    |--------------------------------------------------------------------------
    */

    $leaveRows = DB::table('Vw_vbnet_Rpt_LVBal')
        ->where('EMPNO', $empno)
        ->whereIn('CUT_OFF', $cutoffCodes)
        ->select([
            'CUT_OFF',
            'LV_TYPE',
            'AVAILED_HRS',
            'ENDBAL_HRS',
            'AVAILED',
            'ENDBAL',
        ])
        ->orderBy('CUT_OFF')
        ->orderBy('LV_TYPE')
        ->get()
        ->groupBy('CUT_OFF');

    /*
    |--------------------------------------------------------------------------
    | 4. Loan balances
    |--------------------------------------------------------------------------
    */

    $loanRows = DB::table('Vw_vbnet_Rpt_LNBal')
        ->where('EMPNO', $empno)
        ->whereIn('CUT_OFF', $cutoffCodes)
        ->whereColumn('CUTOFF_CURR', 'CUT_OFF')
        ->select([
            'CUT_OFF',
            'LOAN_DESC',
            'LOAN_AMT',
            'LOAN_BAL',
            'TOTAL_PAID',
        ])
        ->orderBy('CUT_OFF')
        ->orderBy('LOAN_DESC')
        ->get()
        ->groupBy('CUT_OFF');

    /*
    |--------------------------------------------------------------------------
    | 5. YTD
    |--------------------------------------------------------------------------
    */

    $ytdRows = DB::table('Vw_vbnet_Rpt_YTDBal')
        ->where('EMPNO', $empno)
        ->whereIn('CUT_OFF', $cutoffCodes)
        ->select([
            'CUT_OFF',
            'YTD_GROSS',
            'YTD_TAXABLE',
            'YTD_TAX',
            'YTD_SSS',
            'YTD_HDMF',
            'YTD_MED',
        ])
        ->orderBy('CUT_OFF')
        ->get()
        ->groupBy('CUT_OFF');

    /*
    |--------------------------------------------------------------------------
    | 6. Group main transactions by cutoff
    |--------------------------------------------------------------------------
    */

    $transactionsByCutoff = $transactions->groupBy('CUT_OFF');

    $payslips = [];

    foreach ($selectedCutoffs as $cutoffRow) {

        $cutoff = (string) $cutoffRow->CUT_OFF;

        $rows = $transactionsByCutoff->get($cutoff, collect());

        if ($rows->isEmpty()) {
            continue;
        }

        $first = $rows->first();

        $earnings = $rows
            ->filter(function ($row) {
                return str_starts_with(
                    strtoupper((string) $row->TRANS_CODE),
                    'E'
                );
            })
            ->map(function ($row) {
                return [
                    'TRANS_CODE' => $row->TRANS_CODE,
                    'DESCRIP'    => $row->DESCRIP,
                    'HOURS'      => (float) ($row->HOURS ?? 0),
                    'AMOUNT'     => (float) ($row->AMOUNT ?? 0),
                ];
            })
            ->values();

        $deductions = $rows
            ->filter(function ($row) {
                return str_starts_with(
                    strtoupper((string) $row->TRANS_CODE),
                    'D'
                );
            })
            ->map(function ($row) {
                return [
                    'TRANS_CODE' => $row->TRANS_CODE,
                    'DESCRIP'    => $row->DESCRIP,
                    'HOURS'      => (float) ($row->HOURS ?? 0),
                    'AMOUNT'     => (float) ($row->AMOUNT ?? 0),
                ];
            })
            ->values();

        $totalEarnings = (float) $earnings->sum('AMOUNT');
        $totalDeductions = (float) $deductions->sum('AMOUNT');

        $payslips[] = [
            'cutoffCode' => $cutoff,
            'cutoffName' => $first->CUTOFFNAME,

            'employee' => [
                'EMPNO'       => $first->EMPNO,
                'EMP_NAME'    => $first->EMP_NAME,
                'POSITION'    => $first->POSITION,
                'BRANCHNAME'  => $first->BRANCHNAME,
                'ORG_NAME'    => $first->ORG_NAME,
                'COMP_NAME'   => $first->COMP_NAME,
                'EMP_STAT'    => $first->EMP_STAT,
                'PAY_GROUP'   => $first->PAY_GROUP,
                'FREQUENCY'   => $first->FREQUENCY,
                'GROUP_NAME'  => $first->GROUP_NAME,
                'FREQ_TYPE'   => $first->FREQ_TYPE,
                'BASIC'       => $first->BASIC,
                'DAILY_RATE'  => $first->DAILY_RATE,
            ],

            'earnings' => $earnings,
            'deductions' => $deductions,

            'total_earnings' => $totalEarnings,
            'total_deductions' => $totalDeductions,
            'net_pay' => $totalEarnings - $totalDeductions,

            'leave' => $leaveRows
                ->get($cutoff, collect())
                ->values(),

            'loans' => $loanRows
                ->get($cutoff, collect())
                ->values(),

            'ytd' => $ytdRows
                ->get($cutoff, collect())
                ->values(),
        ];
    }

    return response()
        ->json([
            'success' => true,
            'payslips' => $payslips,
        ])
        ->header('Cache-Control', 'private, max-age=60');
}

}
