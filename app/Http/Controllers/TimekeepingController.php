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
        foreach ($data as $index => $item) {
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
                'detail.BreakIn' => 'nullable|string',
                'detail.BreakOut' => 'nullable|string',
                'detail.timeOut' => 'nullable|string',
                'detail.timeInImageId' => 'nullable|string',
                'detail.timeOutImageId' => 'nullable|string',
                'detail.breakInImageId' => 'nullable|string',
                'detail.breakOutImageId' => 'nullable|string',
            ]);

            if ($validator->fails()) {
                Log::error('Validation failed for upsertTimeIn record.', ['index' => $index, 'errors' => $validator->errors(), 'item' => $item]);
                return response()->json([
                    'status' => 'error',
                    'message' => 'Validation failed for item at index ' . $index,
                    'errors' => $validator->errors()
                ], 422);
            }
        }

        $params = collect($data)->values()->toJson(); // JSON format for @params

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
            'message' => 'Time In/Out record upserted successfully.'
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
        ]);

        $imageId = $request->imageId;
        $imageData = $request->imageData;

        if (!preg_match('/^data:image\/(\w+);base64,/', $imageData, $matches)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid image data format. Expected base64 image data URL.'
            ], 422);
        }
        $extension = $matches[1];
        $base64Data = substr($imageData, strpos($imageData, ',') + 1);
        $imageBinary = base64_decode($base64Data);

        if ($imageBinary === false) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to decode base64 image data.'
            ], 422);
        }

        $path = "timekeeping_images/{$imageId}.{$extension}"; // Relative path inside public storage

        Storage::disk('public')->put($path, $imageBinary); // Use 'public' disk for accessibility

        Log::info('Image saved successfully.', ['imageId' => $imageId, 'path' => $path]);

        return response()->json([
            'success' => true,
            'message' => 'Image saved successfully.',
            'path' => $path // The URL path where the image can be accessed
        ]);
    } catch (\Illuminate\Validation\ValidationException $e) {
        Log::error('Validation error in saveImage:', ['errors' => $e->errors(), 'request_data' => $request->all()]);
        return response()->json([
            'success' => false,
            'message' => 'Validation failed for image data.',
            'errors' => $e->errors()
        ], 422);
    } catch (\Exception $e) {
        Log::error('Error saving image:', ['message' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
        return response()->json([
            'success' => false,
            'message' => 'Failed to save image. Please try again later.',
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

}