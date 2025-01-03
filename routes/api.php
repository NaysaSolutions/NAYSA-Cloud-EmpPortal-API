<?php
use App\Http\Controllers\LeaveController;
use App\Http\Controllers\OvertimeController;
use App\Http\Controllers\OfficialBusinessController;
use Illuminate\Http\Request;
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

Route::post('/getLVApprInq', [LeaveController::class, 'getApprInq']);
Route::post('/getLVApprHistory', [LeaveController::class, 'getApprHistory']);
Route::post('/getLVAppInq', [LeaveController::class, 'getAppInq']);
Route::post('/getLVAppHistory', [LeaveController::class, 'getAppHistory']);
Route::post('/upsertLV', [LeaveController::class, 'upsert']);


Route::post('/getOTApprInq', [OvertimeController::class, 'getApprInq']);
Route::post('/getOTApprHistory', [OvertimeController::class, 'getApprHistory']);
Route::post('/getOTAppInq', [OvertimeController::class, 'getAppInq']);
Route::post('/getOTAppHistory', [OvertimeController::class, 'getAppHistory']);
Route::post('/upsertOT', [OvertimeController::class, 'upsert']);


Route::post('/getOBApprInq', [OfficialBusinessController::class, 'getApprInq']);
Route::post('/getOBApprHistory', [OfficialBusinessController::class, 'getApprHistory']);
Route::post('/getOBAppInq', [OfficialBusinessController::class, 'getAppInq']);
Route::post('/getOBAppHistory', [OfficialBusinessController::class, 'getAppHistory']);
Route::post('/upsertOB', [OfficialBusinessController::class, 'upsert']);



// Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
//     return $request->user();
// });
