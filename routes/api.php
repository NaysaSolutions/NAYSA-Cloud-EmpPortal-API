<?php
use App\Http\Controllers\AuthController;
use App\Http\Controllers\LeaveController;
use App\Http\Controllers\OvertimeController;
use App\Http\Controllers\OfficialBusinessController;
use App\Http\Controllers\DashBoardController;
use App\Http\Controllers\OffsetController;
use App\Http\Controllers\RegisterController;
use App\Http\Controllers\TimekeepingController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\FaceIOController;
use Illuminate\Support\Facades\Route;
use Carbon\Carbon;
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

Route::get('/debug-user-model', function () {
    $user = new \App\Models\User();
    $reflection = new \ReflectionClass($user);

    return response()->json([
        'class' => get_class($user),
        'table' => $user->getTable(),
        'primaryKey' => $user->getKeyName(),
        'file' => $reflection->getFileName(),
        'basePath' => base_path(),
        'appPath' => app_path(),
    ]);
});


// Route::get('/server-time', function () {
//     return response()->json([
//         'serverTime' => now('Asia/Manila')->toIso8601String(),
//         'timezone' => 'Asia/Manila',
//     ]);
// });


Route::get('/server-time', function () {
    return response()->json([
        'serverTime' => now('Asia/Manila')->toIso8601String(),
        'timezone'   => 'Asia/Manila',
        'timestamp'  => now('Asia/Manila')->timestamp,
    ])->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
});


Route::get('/me', [AuthController::class, 'me']);
Route::post('/loginDB', [AuthController::class, 'loginDB'])->middleware('throttle:login');

Route::post('/dashBoard', [DashBoardController::class, 'index']);
Route::post('/regEmp', [RegisterController::class, 'regEmp']);
Route::post('/getDTR', [DashBoardController::class, 'getDTR']);
Route::post('/loginEmp', [RegisterController::class, 'loginEmp'])->middleware('throttle:login');


Route::post('/getLVApprInq', [LeaveController::class, 'getApprInq']);
Route::post('/getLVApprHistory', [LeaveController::class, 'getApprHistory']);
Route::post('/getLVAppInq', [LeaveController::class, 'getAppInq']);
Route::post('/getLVAppHistory', [LeaveController::class, 'getAppHistory']);
Route::post('/upsertLV', [LeaveController::class, 'upsert']);
Route::post('/approvalLV', [LeaveController::class, 'approval']);
Route::post('/leaveTypes', [LeaveController::class, 'leaveTypes']);
Route::post('/cancelLV', [LeaveController::class, 'cancel']);





Route::post('/getOTApprInq', [OvertimeController::class, 'getApprInq']);
Route::post('/getOTApprHistory', [OvertimeController::class, 'getApprHistory']);
Route::post('/getOTAppInq', [OvertimeController::class, 'getAppInq']);
Route::post('/getOTAppHistory', [OvertimeController::class, 'getAppHistory']);
Route::post('/OTupsert', [OvertimeController::class, 'upsert']);
Route::post('/approvalOT', [OvertimeController::class, 'approval']);
Route::post('/cancelOT', [OvertimeController::class, 'cancel']);



Route::post('/getOBApprInq', [OfficialBusinessController::class, 'getApprInq']);
Route::post('/getOBApprHistory', [OfficialBusinessController::class, 'getApprHistory']);
Route::post('/getOBAppInq', [OfficialBusinessController::class, 'getAppInq']);
Route::post('/getOBAppHistory', [OfficialBusinessController::class, 'getAppHistory']);
Route::post('/upsertOB', [OfficialBusinessController::class, 'upsert']);
Route::post('/approvalOB', [OfficialBusinessController::class, 'approval']);
Route::post('/cancelOB', [OfficialBusinessController::class, 'cancel']);


// routes/api.php
Route::get('/reports/payslip', [ReportController::class, 'payslipReport']);
Route::get('/reports/payslipLV', [ReportController::class, 'payslipReport_LV']);
Route::get('/reports/payslipLN', [ReportController::class, 'payslipReport_LN']);
Route::get('/reports/payslipYTD', [ReportController::class, 'payslipReport_YTD']);
Route::get('/reports/payslipCutoff', [ReportController::class, 'payslipReport_Cutoff']);



// Route::post('/upsertTimeIn', [TimekeepingController::class, 'upsertTimeIn']);
// Route::post('/saveImage', [TimekeepingController::class, 'saveImage']);
// Route::get('/getNewImageId', [TimekeepingController::class, 'getNewImageId']);
// Route::get('/dtrRecords/{empNo}/{startDate}/{endDate}', [TimekeepingController::class, 'getDTRRecords']);
// Route::get('/getDTRHistory', [TimekeepingController::class, 'getDTRHistory']);
// Route::get('/empBranchLocation/{empNo}', [TimekeepingController::class, 'getBranchLocation']);

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
Route::post('/cancelDTR', [TimekeepingController::class, 'cancel']);

Route::post('/dtr/confirm', [TimekeepingController::class, 'confirmDTR']);
Route::get('/getAllDTR', [TimekeepingController::class, 'getAllDTR']);
Route::get('/getAllDTRHR', [TimekeepingController::class, 'getAllDTRHR']);

// Route::get('/faceio/check/{empNo}', [FaceIOController::class, 'check']);
// Route::post('/faceio/enroll', [FaceIOController::class, 'enroll']);
// Route::post('/faceio/delete', [FaceIOController::class, 'delete']);
// Route::get('/faceio/enrollment-status/{userCode}', [FaceIOController::class, 'enrollmentStatus']);


Route::middleware('auth:sanctum')->group(function () {
    Route::get('/faceio/check/{empNo}', [FaceIOController::class, 'check']);
    Route::post('/faceio/enroll', [FaceIOController::class, 'enroll']);
    Route::post('/faceio/delete', [FaceIOController::class, 'delete']);
});

Route::post('/upsertOffset', [OffsetController::class, 'upsert']);
Route::post('/cancelOffset', [OffsetController::class, 'cancel']);
Route::post('/getOffsetAppHistory', [OffsetController::class, 'getAppHistory']);
Route::get('/getDTROffset/{empNo}/{startDate}/{endDate}',[OffsetController::class, 'getDTROffset']);
Route::post('/getOffsetApprInq', [OffsetController::class, 'getOffsetApprInq']);
Route::post('/getOffsetApprHistory', [OffsetController::class, 'getOffsetApprHistory']);
Route::post('/approvalOffset', [OffsetController::class, 'approval']);


use Illuminate\Support\Facades\DB;

Route::get('/check-db-host', function () {
    return DB::connection()->getConfig('host');
});



// Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
//     return $request->user();
// });
