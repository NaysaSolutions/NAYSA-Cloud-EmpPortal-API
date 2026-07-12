<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class TimekeepingController extends Controller
{
//     public function upsertTimeIn(Request $request)
// {
//     $data = $request->all();

//     if (!is_array($data)) {
//         Log::error('Invalid data format for upsertTimeIn: Expected array.', ['data' => $data]);
//         return response()->json([
//             'status' => 'error',
//             'message' => 'Invalid data format. Expected array of timekeeping records.'
//         ], 422);
//     }

//     try {
//         foreach ($data as $index => $item) {
//             if (!isset($item['detail']) || !is_array($item['detail'])) {
//                 return response()->json([
//                     'status' => 'error',
//                     'message' => 'Invalid data format. "detail" is missing or not an array for item at index ' . $index
//                 ], 422);
//             }

//             $validator = Validator::make($item, [
//                 'empNo' => 'required|string',
//                 'detail' => 'required|array',
//                 'detail.empNo' => 'required|string',
//                 'detail.date' => 'required|date_format:Y-m-d',
//                 'detail.timeIn' => 'nullable|string',
//                 'detail.BreakIn' => 'nullable|string',
//                 'detail.BreakOut' => 'nullable|string',
//                 'detail.timeOut' => 'nullable|string',
//                 'detail.timeInImageId' => 'nullable|string',
//                 'detail.timeOutImageId' => 'nullable|string',
//                 'detail.breakInImageId' => 'nullable|string',
//                 'detail.breakOutImageId' => 'nullable|string',
//             ]);

//             if ($validator->fails()) {
//                 Log::error('Validation failed for upsertTimeIn record.', ['index' => $index, 'errors' => $validator->errors(), 'item' => $item]);
//                 return response()->json([
//                     'status' => 'error',
//                     'message' => 'Validation failed for item at index ' . $index,
//                     'errors' => $validator->errors()
//                 ], 422);
//             }
//         }

//         $params = collect($data)->values()->toJson(); // JSON format for @params

//         $emp = $data[0]['empNo'];
//         $date = $data[0]['detail']['date'];
//         $userid = $data[0]['empNo'];
//         $cutoff = null;

//         Log::info('Calling sproc_PHP_EmpInq_DTR with parameters.', [
//             'mode' => 'upsert_TimeIn',
//             'emp' => $emp,
//             'date' => $date,
//             'userid' => $userid,
//             'params' => $params
//         ]);

//         DB::statement(
//             "EXEC sproc_PHP_EmpInq_DTR 
//                 @mode = :mode,
//                 @stat = :stat,
//                 @emp = :emp,
//                 @date = :date,
//                 @userid = :userid,
//                 @cutoff = :cutoff,
//                 @params = :params",
//             [
//                 'mode' => 'upsert_TimeIn',
//                 'stat' => null,
//                 'emp' => $emp,
//                 'date' => $date,
//                 'userid' => $userid,
//                 'cutoff' => $cutoff,
//                 'params' => $params
//             ]
//         );


//         return response()->json([
//             'status' => 'success',
//             'message' => 'Time In/Out record upserted successfully.'
//         ]);

//     } catch (\Exception $e) {
//         Log::error('Failed to upsert Time In/Out record.', [
//             'error_message' => $e->getMessage(),
//             'stack_trace' => $e->getTraceAsString(),
//             'request_data' => $data
//         ]);
//         return response()->json([
//             'status' => 'error',
//             'message' => 'Failed to upsert Time In/Out record. Please try again later.',
//             'error_details' => $e->getMessage()
//         ], 500);
//     }
// }

public function upsertTimeIn(Request $request)
{
    $data = $request->all();

    if (!is_array($data)) {
        Log::error('Invalid data format for upsertTimeIn: Expected array.', ['data' => $data]);
        return response()->json([
            'status' => 'error',
            'message' => 'Invalid data format. Expected array of timekeeping records.'
        ], 422);
    }

    try {
        foreach ($data as $index => &$item) {
            if (!isset($item['detail']) || !is_array($item['detail'])) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Invalid data format. "detail" is missing or not an array for item at index ' . $index
                ], 422);
            }

            $validator = Validator::make($item, [
                'empNo' => 'required|string',
                'detail' => 'required|array',
                'detail.empNo' => 'required|string',
                'detail.date' => 'required|date_format:Y-m-d',
                'detail.timeIn' => 'nullable|string',
                'detail.breakIn' => 'nullable|string',
                'detail.breakOut' => 'nullable|string',
                'detail.timeOut' => 'nullable|string',

                'detail.timeInImageId' => 'nullable|string',
                'detail.timeOutImageId' => 'nullable|string',
                'detail.breakInImageId' => 'nullable|string',
                'detail.breakOutImageId' => 'nullable|string',

                'detail.timeInImagePath' => 'nullable|string',
                'detail.timeOutImagePath' => 'nullable|string',
                'detail.breakInImagePath' => 'nullable|string',
                'detail.breakOutImagePath' => 'nullable|string',

                'detail.timeInImageBase64' => 'nullable|string',
                'detail.timeOutImageBase64' => 'nullable|string',
                'detail.breakInImageBase64' => 'nullable|string',
                'detail.breakOutImageBase64' => 'nullable|string',

                'detail.latitude' => 'nullable',
                'detail.longitude' => 'nullable',
                'detail.locationAccuracy' => 'nullable',
                'detail.locationAddress' => 'nullable|string',

                'detail.faceioFacialId' => 'nullable|string',
                'detail.faceioPayloadEmpNo' => 'nullable|string',
                'detail.faceioTenantCode' => 'nullable|string',
                'detail.faceioPayloadTenantCode' => 'nullable|string',
                'detail.faceioVerifiedAt' => 'nullable|string',
            ]);

            if ($validator->fails()) {
                Log::error('Validation failed for upsertTimeIn record.', [
                    'index' => $index,
                    'errors' => $validator->errors(),
                    'item' => $item
                ]);

                return response()->json([
                    'status' => 'error',
                    'message' => 'Validation failed for item at index ' . $index,
                    'errors' => $validator->errors()
                ], 422);
            }

            $detail = &$item['detail'];

            $this->validateFaceIoVerification($request, $item, $detail);

            // TIME IN
            if (!empty($detail['timeInImageBase64'])) {
                $imageId = $this->generateImageId();
                $imagePath = $this->saveBase64ImageWithId($imageId, $detail['timeInImageBase64']);
                $detail['timeInImageId'] = $imageId;
                $detail['timeInImagePath'] = $imagePath;
            }

            // TIME OUT
            if (!empty($detail['timeOutImageBase64'])) {
                $imageId = $this->generateImageId();
                $imagePath = $this->saveBase64ImageWithId($imageId, $detail['timeOutImageBase64']);
                $detail['timeOutImageId'] = $imageId;
                $detail['timeOutImagePath'] = $imagePath;
            }

            // BREAK IN
            if (!empty($detail['breakInImageBase64'])) {
                $imageId = $this->generateImageId();
                $imagePath = $this->saveBase64ImageWithId($imageId, $detail['breakInImageBase64']);
                $detail['breakInImageId'] = $imageId;
                $detail['breakInImagePath'] = $imagePath;
            }

            // BREAK OUT
            if (!empty($detail['breakOutImageBase64'])) {
                $imageId = $this->generateImageId();
                $imagePath = $this->saveBase64ImageWithId($imageId, $detail['breakOutImageBase64']);
                $detail['breakOutImageId'] = $imageId;
                $detail['breakOutImagePath'] = $imagePath;
            }

            // optional cleanup so huge base64 blobs are not passed to SQL
            unset(
                $detail['timeInImageBase64'],
                $detail['timeOutImageBase64'],
                $detail['breakInImageBase64'],
                $detail['breakOutImageBase64']
            );
        }

        unset($item);

        $params = collect($data)->values()->toJson();

        $emp = $data[0]['empNo'];
        $date = $data[0]['detail']['date'];
        $userid = $data[0]['empNo'];
        $cutoff = null;

        Log::info('Calling sproc_PHP_EmpInq_DTR with parameters.', [
            'mode' => 'upsert_TimeIn',
            'emp' => $emp,
            'date' => $date,
            'userid' => $userid,
            'params' => $params
        ]);

        DB::statement(
            "EXEC sproc_PHP_EmpInq_DTR 
                @mode = :mode,
                @stat = :stat,
                @emp = :emp,
                @date = :date,
                @userid = :userid,
                @cutoff = :cutoff,
                @params = :params",
            [
                'mode' => 'upsert_TimeIn',
                'stat' => null,
                'emp' => $emp,
                'date' => $date,
                'userid' => $userid,
                'cutoff' => $cutoff,
                'params' => $params
            ]
        );

        return response()->json([
            'status' => 'success',
            'message' => 'Time In/Out record upserted successfully.',
            'data' => $data
        ]);

    } catch (\Exception $e) {
        Log::error('Failed to upsert Time In/Out record.', [
            'error_message' => $e->getMessage(),
            'stack_trace' => $e->getTraceAsString(),
            'request_data' => $data
        ]);

        return response()->json([
            'status' => 'error',
            'message' => 'Failed to upsert Time In/Out record. Please try again later.',
            'error_details' => $e->getMessage()
        ], 500);
    }
}


private function validateFaceIoVerification(Request $request, array $item, array $detail): void
{
    $facialId = trim((string) ($detail['faceioFacialId'] ?? ''));

    if ($facialId === '') {
        return;
    }

    $empNo = trim((string) ($detail['empNo'] ?? $item['empNo'] ?? ''));
    $payloadEmpNo = trim((string) ($detail['faceioPayloadEmpNo'] ?? ''));
    $requestTenantCode = $this->normalizeTenantCode(
        $detail['faceioTenantCode'] ?? $request->header('X-Tenant-Code') ?? $request->input('tenantCode')
    );
    $payloadTenantCode = $this->normalizeTenantCode($detail['faceioPayloadTenantCode'] ?? null);

    if ($empNo === '') {
        throw new \Exception('Employee number is required for FACEIO validation.');
    }

    if ($payloadEmpNo !== '' && strcasecmp($payloadEmpNo, $empNo) !== 0) {
        throw new \Exception('FACEIO verification employee mismatch.');
    }

    $employee = DB::table('paymast')
        ->select('empno', 'faceio_facial_id')
        ->where('empno', $empNo)
        ->first();

    if (!$employee) {
        throw new \Exception('Employee not found for FACEIO validation.');
    }

    $savedFacialId = trim((string) ($employee->faceio_facial_id ?? ''));

    if ($savedFacialId === '') {
        throw new \Exception('Employee has no saved FACEIO enrollment.');
    }

    if (strcasecmp($savedFacialId, $facialId) !== 0) {
        Log::warning('FACEIO facial ID mismatch on timekeeping.', [
            'empNo' => $empNo,
            'providedFacialId' => $facialId,
            'savedFacialId' => $savedFacialId,
            'requestTenantCode' => $requestTenantCode,
            'payloadTenantCode' => $payloadTenantCode,
        ]);

        throw new \Exception('FACEIO verification does not match the logged-in employee enrollment.');
    }

    if ($requestTenantCode !== null && $payloadTenantCode !== null && strcasecmp($requestTenantCode, $payloadTenantCode) !== 0) {
        Log::warning('FACEIO tenant code mismatch on timekeeping.', [
            'empNo' => $empNo,
            'requestTenantCode' => $requestTenantCode,
            'payloadTenantCode' => $payloadTenantCode,
            'facialId' => $facialId,
        ]);

        throw new \Exception('FACEIO verification tenant mismatch.');
    }
}

private function normalizeTenantCode($value): ?string
{
    $value = trim((string) ($value ?? ''));
    return $value === '' ? null : $value;
}

private function generateImageId()
{
    $result = DB::select('EXEC sproc_php_auto_newid');

    if (empty($result) || !isset($result[0]->new_id)) {
        throw new \Exception('Failed to generate a new image ID from the database.');
    }

    return $result[0]->new_id;
}

private function saveBase64ImageWithId($imageId, $imageData)
{
    if (!preg_match('/^data:image\/(\w+);base64,/', $imageData, $matches)) {
        throw new \Exception('Invalid image data format. Expected base64 image data URL.');
    }

    $extension = strtolower($matches[1]);
    if ($extension === 'jpeg') {
        $extension = 'jpg';
    }

    $base64Data = substr($imageData, strpos($imageData, ',') + 1);
    $imageBinary = base64_decode($base64Data);

    if ($imageBinary === false) {
        throw new \Exception('Failed to decode base64 image data.');
    }

    $path = "timekeeping_images/{$imageId}.{$extension}";
    // $path = "images/timekeeping_images/{$imageId}.{$extension}";
    $saved = Storage::disk('public')->put($path, $imageBinary);

    if (!$saved) {
        throw new \Exception("Failed to save image to {$path}");
    }

    Log::info('Image saved successfully.', [
        'imageId' => $imageId,
        'path' => $path,
        'full_path' => storage_path('app/public/' . $path),
    ]);

    return $path;
}


    public function getNewImageId()
    {
        try {
            $result = DB::select('EXEC sproc_php_auto_newid');

            Log::info('Stored procedure sproc_php_auto_newid result:', ['result' => $result]);

            if (empty($result) || !isset($result[0]->new_id)) {
                Log::error('No result or missing new_id from sproc_php_auto_newid.');
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to generate a new image ID from the database.'
                ], 500);
            }

            $newId = $result[0]->new_id;

            return response()->json([
                'success' => true,
                'id' => $newId,
            ]);
        } catch (\Exception $e) {
            Log::error('Error in getNewImageId:', ['message' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while generating a new image ID.',
                'error_details' => $e->getMessage()
            ], 500);
        }
    }

    public function saveImage(Request $request)
{
    try {
        $request->validate([
            'imageId' => 'required|string',
            'imageData' => 'required|string',
            'branchCode' => 'nullable|string',
            'empNo' => 'nullable|string',
        ]);

        $imageId = $request->input('imageId');
        $imageData = $request->input('imageData');

        $branchCode = $request->input('branchCode', 'NO_BRANCH');
        $empNo = $request->input('empNo', 'NO_EMPLOYEE');

        // Sanitize branch folder name
        $branchCode = strtoupper(trim($branchCode));
        $branchCode = preg_replace('/[^A-Z0-9_-]/', '_', $branchCode);

        if ($branchCode === '') {
            $branchCode = 'NO_BRANCH';
        }

        // Sanitize employee folder name
        $empNo = strtoupper(trim($empNo));
        $empNo = preg_replace('/[^A-Z0-9_-]/', '_', $empNo);

        if ($empNo === '') {
            $empNo = 'NO_EMPLOYEE';
        }

        if (!preg_match('/^data:image\/(\w+);base64,/', $imageData, $matches)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid image data format.'
            ], 422);
        }

        $extension = strtolower($matches[1]);

        if ($extension === 'jpeg') {
            $extension = 'jpg';
        }

        $allowedExtensions = ['jpg', 'png', 'webp'];

        if (!in_array($extension, $allowedExtensions)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid image type.'
            ], 422);
        }

        $base64Data = substr($imageData, strpos($imageData, ',') + 1);
        $imageBinary = base64_decode($base64Data);

        if ($imageBinary === false) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to decode image data.'
            ], 422);
        }

        // Final path:
        // timekeeping_images/HO/1479/imageId.jpg
        $path = "timekeeping_images/{$branchCode}/{$empNo}/{$imageId}.{$extension}";

$fullPath = public_path("images/" . $path);

if (!file_exists(dirname($fullPath))) {
    mkdir(dirname($fullPath), 0755, true);
}

file_put_contents($fullPath, $imageBinary);

return response()->json([
    'success' => true,
    'path' => $path,
    'url' => asset("images/" . $path),
]);
    } catch (\Exception $e) {
        Log::error('Error saving image:', [
            'message' => $e->getMessage(),
        ]);

        return response()->json([
            'success' => false,
            'message' => 'Failed to save image.',
            'error_details' => $e->getMessage()
        ], 500);
    }
}

    /**
     * Fetches DTR records for a given employee and optional date range.
     *
     * @param string $empNo The employee number.
     * @param string|null $date The specific date (YYYY-MM-DD) or start date for a range.
     * @param string|null $endDate The end date for a range (YYYY-MM-DD).
     * @return \Illuminate\Http\JsonResponse
     */
    public function getDTRRecords(Request $request, $empNo, $startDate, $endDate)
    {
        try {

            Log::info('Fetching DTR records.', [
                'empNo' => $empNo,
                'startDate' => $startDate,
                'endDate' => $endDate
            ]);

            // Call the stored procedure with 'get_DTR' mode
            $records = DB::select("EXEC sproc_PHP_EmpInq_DTR
                @mode = 'get_DTR',
                @stat = null,
                @emp = ?,
                @date = ?,
                @userid = ?,
                @cutoff = ?,
                @params = ?",
                [$empNo, $startDate, auth()->user()->id ?? 'system', $endDate, null] // Pass endDate as @cutoff for flexibility
            );

            return response()->json([
                'success' => true,
                'message' => 'DTR records fetched successfully.',
                'records' => $records
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching DTR records:', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'empNo' => $empNo,
                'date' => $startDate
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch DTR records. Please try again later.',
                'error_details' => $e->getMessage()
            ], 500);
        }
    }


    public function getDTRHistory(Request $request)
    {
        try {
            $endDate = $request->query('endDate'); // Get endDate from query parameter

            // If no date is provided, default to today's date
            if (empty($date)) {
                $date = now()->format('Y-m-d');
            }

            Log::info('Fetching DTR records.', [
                'empNo' => $empNo,
                'startDate' => $date,
                'endDate' => $endDate
            ]);

            // Call the stored procedure with 'get_DTR' mode
            $records = DB::select("EXEC sproc_PHP_EmpInq_DTR
                @mode = 'get_DTRHistory',
                @stat = null,
                @emp = ?,
                @date = ?,
                @userid = ?,
                @cutoff = ?,
                @params = ?",
                [$empNo, $date, auth()->user()->id ?? 'system', $endDate, null] // Pass endDate as @cutoff for flexibility
            );

            return response()->json([
                'success' => true,
                'message' => 'DTR records fetched successfully.',
                'records' => $records
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching DTR records:', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'empNo' => $empNo,
                'date' => $date
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch DTR records. Please try again later.',
                'error_details' => $e->getMessage()
            ], 500);
        }
    }

public function getBranchLocation(Request $request, $empNo)
{
try {

            Log::info('Fetching DTR records.', [
                'empNo' => $empNo,
            ]);

            $records = DB::select("EXEC sproc_PHP_EmpInq_DTR
                @mode = 'get_BranchLocation',
                @stat = null,
                @emp = ?,
                @date = ?,
                @userid = ?,
                @cutoff = ?,
                @params = ?",
                [$empNo, null, auth()->user()->id ?? 'system', null, null] // Pass endDate as @cutoff for flexibility
            );

            return response()->json([
                'success' => true,
                'message' => 'DTR records fetched successfully.',
                'records' => $records
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching DTR records:', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'empNo' => $empNo,
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch DTR records. Please try again later.',
                'error_details' => $e->getMessage()
            ], 500);
        }

}



// ** DTR Approval Inquiry Current
public function getApprInq(Request $request) {

    $request->validate([
        'EMP_NO' => 'required|string',
    ]);

    $stat = $request->input('STAT');
    $employee_no = $request->input('EMP_NO');


    try {
        $results = DB::select(
            'EXEC sproc_PHP_EmpInq_DTR @mode = ?, @stat = ?, @emp =?',
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


 // ** DTR Approval Inquiry HIstory
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
            'EXEC sproc_PHP_EmpInq_DTR @mode = ?, @startdate = ?, @enddate =?, @emp =?',
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


// ** DTR Application Inquiry Current
public function getAppInq(Request $request) {

    $request->validate([
        'EMP_NO' => 'required|string',
    ]);

    $stat = $request->input('STAT');
    $employee_no = $request->input('EMP_NO');


    try {
        $results = DB::select(
            'EXEC sproc_PHP_EmpInq_DTR @mode = ?, @emp =?',
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


 // ** DTR Application Inquiry History
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
            'EXEC sproc_PHP_EmpInq_DTR @mode = ?, @startdate = ?, @enddate =?, @emp =?',
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


// public function upsert(Request $request)
// {
//     try {
//         $data = $request->json('json_data');
//         $empNo = $data['empNo'] ?? null;
//         $details = $data['detail'] ?? [];

//         if (!$empNo || empty($details)) {
//             return response()->json(['status' => 'error', 'message' => 'Invalid data provided'], 400);
//         }

//         $jsonParams = json_encode(['json_data' => $data]);
        
//         DB::statement("EXEC sproc_PHP_EmpInq_DTR @mode = 'upsert', @params = ?", [$jsonParams]);

//         return response()->json(['status' => 'success', 'message' => 'Official Business application submitted successfully']);
//     } catch (\Exception $e) {
//         Log::error('Error in upsertOB: ' . $e->getMessage());
//         return response()->json(['status' => 'error', 'message' => 'An error occurred while processing the request'], 500);
//     }
// }

// Route: POST /upsertDTR
public function upsert(Request $request) {
    try {
        $data = $request->json('json_data');
        if (!$data || empty($data['empNo']) || empty($data['detail'])) {
            return response()->json(['status' => 'error', 'message' => 'Invalid data provided'], 400);
        }
        $jsonParams = json_encode(['json_data' => $data]);
        DB::statement("EXEC sproc_PHP_EmpInq_DTR @mode = 'upsert', @params = ?", [$jsonParams]);
        return response()->json(['status' => 'success', 'message' => 'DTR application submitted successfully']);
    } catch (\Exception $e) {
        \Log::error('Error in upsertDTR: ' . $e->getMessage());
        return response()->json(['status' => 'error', 'message' => 'An error occurred while processing the request'], 500);
    }
}



// public function approval(Request $request)
// {
//     try {
//         // Validate that json_data is a required string
//         $request->validate([
//             'json_data' => 'required|string',
//         ]);

//         // Decode the JSON string
//         $jsonString = $request->input('json_data');
//         $data = json_decode($jsonString, true);

//         // Check if decoding was successful and contains 'json_data'
//         if (json_last_error() !== JSON_ERROR_NONE || !isset($data['json_data'])) {
//             return response()->json([
//                 'status' => 'error',
//                 'message' => 'Invalid JSON data format.',
//             ], 400);
//         }

//         // Convert json_data to a JSON string for SQL execution
//         $jsonParams = json_encode($data['json_data']);

//         // Log the formatted data for debugging
//         Log::info('Approval request sent:', ['json_data' => $jsonParams]);

//         // Execute the stored procedure
//         DB::statement("EXEC sproc_PHP_EmpInq_DTR @mode = 'Approval', @params = ?", [$jsonParams]);

//         return response()->json([
//             'status' => 'success',
//             'message' => 'Official Business approval processed successfully',
//         ]);
//     } catch (\Exception $e) {
//         Log::error('Error in approval process:', ['error' => $e->getMessage()]);

//         return response()->json([
//             'status' => 'error',
//             'message' => 'Failed to process approval: ' . $e->getMessage(),
//         ], 500);
//     }
// }

// Route: POST /approvalDTR
public function approval(Request $request)
{
    try {
        $payload = $request->input('json_data'); // may be string or object

        // If it's an array/object, wrap it. If it's already a string, leave as-is.
        if (is_array($payload)) {
            $jsonParams = json_encode($payload); // becomes the inner json_data
        } else {
            // Expecting the current shape you use in OBReview.jsx (string containing {"json_data":{...}})
            $decoded = json_decode($payload, true);
            if (json_last_error() !== JSON_ERROR_NONE || !isset($decoded['json_data'])) {
                return response()->json(['status' => 'error', 'message' => 'Invalid JSON data format.'], 400);
            }
            $jsonParams = json_encode($decoded['json_data']);
        }

        \Log::info('Approval DTR request:', ['json_data' => $jsonParams]);
        DB::statement("EXEC sproc_PHP_EmpInq_DTR @mode = 'Approval', @params = ?", [$jsonParams]);

        return response()->json(['status' => 'success', 'message' => 'DTR approval processed successfully']);
    } catch (\Exception $e) {
        \Log::error('Error in approvalDTR:', ['error' => $e->getMessage()]);
        return response()->json(['status' => 'error', 'message' => 'Failed to process approval: ' . $e->getMessage()], 500);
    }
}


public function cancel(Request $request)
{
    try {
        // accept from json body or form urlencoded just in case
        $payload = $request->input('json_data', $request->json('json_data'));

        $empNo   = $payload['empNo']   ?? null;
        $stamp   = $payload['dtrStamp'] ?? null;

        if (!$empNo || !$stamp) {
            return response()->json([
                'status'  => 'error',
                'message' => 'empNo and Stamp are required'
            ], 400);
        }

        $jsonParams = json_encode([
            'empNo'   => $empNo,
            'dtrStamp' => $stamp,
        ], JSON_UNESCAPED_SLASHES);

        // EXEC sproc_PHP_EmpInq_Overtime @mode='Cancel', @params='{"empNo":"...","otStamp":"..."}'
        DB::statement("EXEC sproc_PHP_EmpInq_DTR @mode = 'Cancel', @params = ?", [$jsonParams]);

        return response()->json([
            'status'  => 'success',
            'message' => 'DTR cancelled successfully'
        ]);
    } catch (\Throwable $e) {
        Log::error('Error in cancelDTR: '.$e->getMessage());
        return response()->json([
            'status'  => 'error',
            'message' => 'An error occurred while processing the request'
        ], 500);
    }
}

public function confirmDTR(Request $request)
    {
        $request->validate([
            'empNo'     => 'required|string',
            'startDate' => 'required|date',
            'endDate'   => 'required|date',
        ]);

        $empNo     = $request->input('empNo');
        $startDate = $request->input('startDate');
        $endDate   = $request->input('endDate');

        // user id for audit trail
        $userId = auth()->user()->empno ?? auth()->user()->userid ?? 'SYSTEM';

        try {
            // Call your SPROC with the new mode
            $rows = DB::select("
                EXEC sproc_PHP_EmpInq_DTR
                    @mode      = :mode,
                    @stat      = NULL,
                    @emp       = :emp,
                    @startdate = :startdate,
                    @enddate   = :enddate,
                    @date      = NULL,
                    @userid    = :userid,
                    @cutoff    = NULL,
                    @params    = NULL
            ", [
                'mode'      => 'ConfirmDTR',
                'emp'       => $empNo,
                'startdate' => $startDate,
                'enddate'   => $endDate,
                'userid'    => $userId,
            ]);

            // SPROC returns a single row with status/message
            $result   = $rows[0] ?? null;
            $status   = $result->status  ?? 'success';
            $message  = $result->message ?? 'DTR successfully confirmed.';

            return response()->json([
                'success' => $status === 'success',
                'status'  => $status,
                'message' => $message,
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'success' => false,
                'status'  => 'error',
                'message' => $th->getMessage(),
            ], 500);
        }
    }

    private function runEmpInqDTR(string $mode, ?string $empNo, ?string $startDate, ?string $endDate, ?string $userId = null): array
{
    $pdo = DB::connection()->getPdo();

    $stmt = $pdo->prepare("
        SET NOCOUNT ON;

        EXEC dbo.sproc_PHP_EmpInq_DTR
            @mode      = ?,
            @stat      = ?,
            @emp       = ?,
            @startdate = ?,
            @enddate   = ?,
            @date      = ?,
            @userid    = ?,
            @cutoff    = ?,
            @params    = ?
    ");

    $stmt->execute([
        $mode,
        null,
        $empNo,
        $startDate,
        $endDate,
        null,
        $userId,
        null,
        null,
    ]);

    $rows = [];

    // SQL Server may return non-select result sets first.
    // This prevents: "The active result for the query contains no fields."
    do {
        if ($stmt->columnCount() > 0) {
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            break;
        }
    } while ($stmt->nextRowset());

    $stmt->closeCursor();

    return $rows;
}

public function getAllDTR(Request $request)
{
    $request->merge([
        'empNo'     => $request->input('empNo', $request->input('EMP_NO')),
        'startDate' => $request->input('startDate', $request->input('START_DATE')),
        'endDate'   => $request->input('endDate', $request->input('END_DATE')),
    ]);

    $request->validate([
        'startDate' => 'required|date_format:Y-m-d',
        'endDate'   => 'required|date_format:Y-m-d',
        'empNo'     => 'nullable|string',
    ]);

    $user = auth()->user();

    $userId =
        $request->input('userId') ??
        $user->empno ??
        $user->userid ??
        $user->id ??
        'SYSTEM';

    try {
        $records = $this->runEmpInqDTR(
            'getAll_DTR',
            $request->input('empNo'),
            $request->input('startDate'),
            $request->input('endDate'),
            $userId
        );

        return response()->json([
            'success' => true,
            'message' => 'All DTR records fetched successfully.',
            'records' => $records,
            'data'    => $records,
        ]);
    } catch (\Throwable $e) {
        Log::error('Error fetching getAll_DTR records:', [
            'message'   => $e->getMessage(),
            'trace'     => $e->getTraceAsString(),
            'startDate' => $request->input('startDate'),
            'endDate'   => $request->input('endDate'),
            'empNo'     => $request->input('empNo'),
        ]);

        return response()->json([
            'success' => false,
            'message' => 'Failed to fetch all DTR records. Please try again later.',
            'error_details' => $e->getMessage(),
        ], 500);
    }
}

public function getAllDTRHR(Request $request)
{
    $request->merge([
        'empNo'     => $request->input('empNo', $request->input('EMP_NO')),
        'startDate' => $request->input('startDate', $request->input('START_DATE')),
        'endDate'   => $request->input('endDate', $request->input('END_DATE')),
    ]);

    $request->validate([
        // For getAll_DTR_HR, empNo is the approver/HR employee number.
        'empNo'     => 'required|string',
        'startDate' => 'required|date_format:Y-m-d',
        'endDate'   => 'required|date_format:Y-m-d',
    ]);

    $user = auth()->user();

    $userId =
        $request->input('userId') ??
        $user->empno ??
        $user->userid ??
        $user->id ??
        'SYSTEM';

    try {
        $records = $this->runEmpInqDTR(
            'getAll_DTR_HR',
            $request->input('empNo'),
            $request->input('startDate'),
            $request->input('endDate'),
            $userId
        );

        return response()->json([
            'success' => true,
            'message' => 'HR DTR records fetched successfully.',
            'records' => $records,
            'data'    => $records,
        ]);
    } catch (\Throwable $e) {
        Log::error('Error fetching getAll_DTR_HR records:', [
            'message'   => $e->getMessage(),
            'trace'     => $e->getTraceAsString(),
            'startDate' => $request->input('startDate'),
            'endDate'   => $request->input('endDate'),
            'empNo'     => $request->input('empNo'),
        ]);

        return response()->json([
            'success' => false,
            'message' => 'Failed to fetch HR DTR records. Please try again later.',
            'error_details' => $e->getMessage(),
        ], 500);
    }
}

}