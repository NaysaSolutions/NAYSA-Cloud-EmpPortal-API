<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Throwable;

class EmployeeAccessSettingsController extends Controller
{
    /**
     * GET /api/employeePortalSettings
     *
     * Returns every employee together with portal rights, role flags,
     * approval assignments, and fields used by the React filters/grouping.
     */
    public function index(Request $request)
    {
        try {
            $result = DB::select(
                'EXEC dbo.sproc_PHP_EmployeePortalSettings @mode = ?, @params = ?',
                ['Load', null]
            );

            return response()->json([
                'success' => true,
                'data'    => $result,
            ]);
        } catch (Throwable $e) {
            Log::error('Employee portal settings load failed', [
                'message' => $e->getMessage(),
                'trace'   => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Unable to load employee access settings.',
            ], 500);
        }
    }

    /**
     * POST /api/updateEmployeePortalSettings
     *
     * Expected body from EmployeeAccessSettings.jsx:
     * {
     *   "employees": [
     *      {
     *        "empNo": "000001",
     *        "portalLV": "Y",
     *        "approver1": "000010"
     *      }
     *   ]
     * }
     *
     * Only changed properties are sent by the frontend, so the SQL procedure
     * preserves fields that are absent from each employee object.
     */
    public function update(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'employees'                     => ['required', 'array', 'min:1'],
            'employees.*.empNo'             => ['required', 'string', 'max:50'],

            'employees.*.portalLV'          => ['nullable', 'in:Y,N,1,0'],
            'employees.*.portalOB'          => ['nullable', 'in:Y,N,1,0'],
            'employees.*.portalOT'          => ['nullable', 'in:Y,N,1,0'],
            'employees.*.portalOffSet'      => ['nullable', 'in:Y,N,1,0'],
            'employees.*.portalTK'          => ['nullable', 'in:Y,N,1,0'],
            'employees.*.portalDTR'         => ['nullable', 'in:Y,N,1,0'],
            'employees.*.portalDTRConfirm'  => ['nullable', 'in:Y,N,1,0'],

            'employees.*.approver'          => ['nullable', 'in:Y,N,1,0'],
            'employees.*.mgrFlag'           => ['nullable', 'in:Y,N,1,0'],
            'employees.*.supFlag'           => ['nullable', 'in:Y,N,1,0'],

            'employees.*.approver1'         => ['nullable', 'string', 'max:50'],
            'employees.*.approver2'         => ['nullable', 'string', 'max:50'],
            'employees.*.approver3'         => ['nullable', 'string', 'max:50'],
            'employees.*.manager'           => ['nullable', 'string', 'max:50'],
            'employees.*.supervisor'        => ['nullable', 'string', 'max:50'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid employee access settings payload.',
                'errors'  => $validator->errors(),
            ], 422);
        }

        try {
            $payload = json_encode([
                'employees' => $request->input('employees', []),
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

            DB::beginTransaction();

            $result = DB::select(
                'EXEC dbo.sproc_PHP_EmployeePortalSettings @mode = ?, @params = ?',
                ['Update', $payload]
            );

            DB::commit();

            $updatedCount = $result[0]->updatedCount ?? count($request->input('employees', []));

            return response()->json([
                'success'      => true,
                'message'      => 'Employee access settings saved successfully.',
                'updatedCount' => (int) $updatedCount,
            ]);
        } catch (Throwable $e) {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }

            Log::error('Employee portal settings update failed', [
                'message' => $e->getMessage(),
                'payload' => $request->input('employees', []),
                'trace'   => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Unable to save employee access settings.',
            ], 500);
        }
    }
}
