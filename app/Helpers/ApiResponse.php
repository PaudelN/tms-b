<?php

namespace App\Helpers;

use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class ApiResponse
{
    /** Success without data */
    public static function success(string $message = 'Success', int $status_code = 200): JsonResponse
    {
        return response()->json(['status' => 'success', 'message' => $message], $status_code);
    }

    /** Success with data */
    public static function successData($data, string $message = 'Success', int $status_code = 200): JsonResponse
    {
        return response()->json([
            'status' => 'success',
            'message' => $message,
            'data' => $data,
        ], $status_code);
    }

    /** Created — returns 201 status */
    public static function created($data = null, string $message = 'Created successfully'): JsonResponse
    {
        return response()->json([
            'status' => 'success',
            'message' => $message,
            'data' => $data,
        ], 201);
    }

    /** Error response */
    public static function error(string $message, int $status_code = 400): JsonResponse
    {
        return response()->json([
            'status' => 'error',
            'message' => $message,
        ], $status_code);
    }

    /** Validation error */
    public static function validationError($errors, string $message = 'Validation Error', int $status_code = 422): JsonResponse
    {
        return response()->json([
            'status' => 'error',
            'message' => $message,
            'errors' => $errors,
        ], $status_code);
    }

    /** Exception handler */
    public static function exception(Exception $exception, string $message = 'Something went wrong'): JsonResponse
    {
        $user = Auth::user();
        $context = [];

        if ($user) {
            $context['User'] = $user->name . ' (' . $user->email . ')';
        }

        $context['Error Message'] = $exception->getMessage();
        $context['File'] = "{$exception->getFile()} ({$exception->getLine()})";
        $context['Trace'] = $exception->getTraceAsString();

        Log::error("Exception occurred", $context);

        return response()->json([
            'status' => 'error',
            'message' => $message,
            'error' => $exception->getMessage(),
        ], 500);
    }
}
