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

        // Save to the public directory
        // $path = "public/timekeeping_images/{$imageId}.{$extension}"; // Save within public/storage
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
    public function getDTRRecords(Request $request, $empNo, $date = null)
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
            // The @params parameter is not used for this mode.
            $records = DB::select("EXEC sproc_PHP_EmpInq_DTR
                @mode = 'get_DTR',
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
            // The @params parameter is not used for this mode.
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
        // Your existing DB query code...

        $records = DB::select("EXEC sproc_PHP_EmpInq_DTR
            @mode = 'get_BranchLocation',
            @stat = null,
            @emp = ?,
            @date = ?,
            @userid = ?,
            @cutoff = ?,
            @params = ?",
            [$empNo, $date, auth()->user()->id ?? 'system', $endDate, null]
        );
        
        // Ensure there is a record and return the first one inside an array
        $branchLocation = count($records) > 0 ? [$records[0]] : []; // 👈 Return a single item in an array
        
        return response()->json([
            'success' => true,
            'message' => 'Branch location fetched successfully.',
            'records' => $branchLocation // 👈 Use the 'records' key
        ]);

    } catch (\Exception $e) {
        Log::error('Error fetching Employee Branch Location records:', [
            'message' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
            'empNo' => $empNo
        ]);
        return response()->json([
            'success' => false,
            'message' => 'Failed to fetch branch location. Please try again later.',
            'error_details' => $e->getMessage()
        ], 500);
    }
}

}