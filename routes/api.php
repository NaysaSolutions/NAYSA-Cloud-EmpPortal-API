<?php
use App\Http\Controllers\LeaveController;
use App\Http\Controllers\OvertimeController;
use App\Http\Controllers\OfficialBusinessController;
use App\Http\Controllers\DashBoardController;
use App\Http\Controllers\RegisterController;
use App\Http\Controllers\TimekeepingController;
use Illuminate\Support\Facades\Route;
/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/


Route::post('/dashBoard', [DashBoardController::class, 'index']);
Route::post('/regEmp', [RegisterController::class, 'regEmp']);
Route::post('/loginEmp', [RegisterController::class, 'loginEmp']);
Route::post('/getDTR', [DashBoardController::class, 'getDTR']);


Route::post('/getLVApprInq', [LeaveController::class, 'getApprInq']);
Route::post('/getLVApprHistory', [LeaveController::class, 'getApprHistory']);
Route::post('/getLVAppInq', [LeaveController::class, 'getAppInq']);
Route::post('/getLVAppHistory', [LeaveController::class, 'getAppHistory']);
Route::post('/upsertLV', [LeaveController::class, 'upsert']);
Route::post('/approvalLV', [LeaveController::class, 'approval']);



Route::post('/getOTApprInq', [OvertimeController::class, 'getApprInq']);
Route::post('/getOTApprHistory', [OvertimeController::class, 'getApprHistory']);
Route::post('/getOTAppInq', [OvertimeController::class, 'getAppInq']);
Route::post('/getOTAppHistory', [OvertimeController::class, 'getAppHistory']);
Route::post('/OTupsert', [OvertimeController::class, 'upsert']);
Route::post('/approvalOT', [OvertimeController::class, 'approval']);



Route::post('/getOBApprInq', [OfficialBusinessController::class, 'getApprInq']);
Route::post('/getOBApprHistory', [OfficialBusinessController::class, 'getApprHistory']);
Route::post('/getOBAppInq', [OfficialBusinessController::class, 'getAppInq']);
Route::post('/getOBAppHistory', [OfficialBusinessController::class, 'getAppHistory']);
Route::post('/upsertOB', [OfficialBusinessController::class, 'upsert']);
Route::post('/approvalOB', [OfficialBusinessController::class, 'approval']);


// routes/api.php
Route::get('/reports/payslip', [App\Http\Controllers\ReportController::class, 'payslipReport']);
Route::get('/reports/payslipLV', [App\Http\Controllers\ReportController::class, 'payslipReport_LV']);
Route::get('/reports/payslipLN', [App\Http\Controllers\ReportController::class, 'payslipReport_LN']);
Route::get('/reports/payslipYTD', [App\Http\Controllers\ReportController::class, 'payslipReport_YTD']);
Route::get('/reports/payslipCutoff', [App\Http\Controllers\ReportController::class, 'payslipReport_Cutoff']);



Route::post('/upsertTimeIn', [TimekeepingController::class, 'upsertTimeIn']);
Route::post('/saveImage', [TimekeepingController::class, 'saveImage']);
Route::get('/getNewImageId', [TimekeepingController::class, 'getNewImageId']);
Route::get('/dtrRecords/{empNo}/{startDate}/{endDate}', [TimekeepingController::class, 'getDTRRecords']);
Route::get('/getDTRHistory', [TimekeepingController::class, 'getDTRHistory']);
Route::get('/empBranchLocation/{empNo}', [TimekeepingController::class, 'getBranchLocation']);

Route::post('/getDTRApprInq', [TimekeepingController::class, 'getApprInq']);
Route::post('/getDTRApprHistory', [TimekeepingController::class, 'getApprHistory']);
Route::post('/getDTRAppInq', [TimekeepingController::class, 'getAppInq']);
Route::post('/getDTRAppHistory', [TimekeepingController::class, 'getAppHistory']);
Route::post('/upsertDTR', [TimekeepingController::class, 'upsert']);
Route::post('/approvalDTR', [TimekeepingController::class, 'approval']);


use Illuminate\Support\Facades\DB;

Route::get('/check-db-host', function () {
    return DB::connection()->getConfig('host');
});



// Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
//     return $request->user();
// });
