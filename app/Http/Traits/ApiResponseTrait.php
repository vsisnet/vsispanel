<?php

declare(strict_types=1);

namespace App\Http\Traits;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Pagination\LengthAwarePaginator;

trait ApiResponseTrait
{
    /**
     * Return a success JSON response
     */
    protected function successResponse(
        mixed $data = null,
        string $message = '',
        int $code = 200,
        array $meta = []
    ): JsonResponse {
        $response = [
            'success' => true,
            'data' => $data,
        ];

        if (!empty($message)) {
            $response['message'] = $message;
        }

        if (!empty($meta)) {
            $response['meta'] = $meta;
        }

        return response()->json($response, $code);
    }

    /**
     * Return an error JSON response
     */
    protected function errorResponse(
        string $message,
        int $code = 400,
        ?string $errorCode = null,
        array $errors = []
    ): JsonResponse {
        $response = [
            'success' => false,
            'error' => [
                'code' => $errorCode ?? $this->getErrorCodeFromHttpStatus($code),
                'message' => $message,
            ],
        ];

        if (!empty($errors)) {
            $response['error']['errors'] = $errors;
        }

        return response()->json($response, $code);
    }

    /**
     * Return a paginated JSON response
     */
    protected function paginatedResponse(
        LengthAwarePaginator $paginator,
        ?string $resourceClass = null,
        string $message = ''
    ): JsonResponse {
        $data = $resourceClass
            ? $resourceClass::collection($paginator->items())
            : $paginator->items();

        $response = [
            'success' => true,
            'data' => $data,
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
            ],
            'links' => [
                'first' => $paginator->url(1),
                'last' => $paginator->url($paginator->lastPage()),
                'prev' => $paginator->previousPageUrl(),
                'next' => $paginator->nextPageUrl(),
            ],
        ];

        if (!empty($message)) {
            $response['message'] = $message;
        }

        return response()->json($response, 200);
    }

    /**
     * Return a resource response
     */
    protected function resourceResponse(
        JsonResource|ResourceCollection $resource,
        string $message = '',
        int $code = 200
    ): JsonResponse {
        $response = [
            'success' => true,
            'data' => $resource,
        ];

        if (!empty($message)) {
            $response['message'] = $message;
        }

        return response()->json($response, $code);
    }

    /**
     * Return a no content response (204)
     */
    protected function noContentResponse(): JsonResponse
    {
        return response()->json(null, 204);
    }

    /**
     * Return a created response (201)
     */
    protected function createdResponse(
        mixed $data = null,
        string $message = 'Resource created successfully'
    ): JsonResponse {
        return $this->successResponse($data, $message, 201);
    }

    /**
     * Return an accepted response (202)
     */
    protected function acceptedResponse(
        mixed $data = null,
        string $message = 'Request accepted for processing'
    ): JsonResponse {
        return $this->successResponse($data, $message, 202);
    }

    /**
     * Return a not found response (404)
     */
    protected function notFoundResponse(string $message = 'Resource not found'): JsonResponse
    {
        return $this->errorResponse($message, 404, 'NOT_FOUND');
    }

    /**
     * Return a forbidden response (403)
     */
    protected function forbiddenResponse(string $message = 'Access denied'): JsonResponse
    {
        return $this->errorResponse($message, 403, 'FORBIDDEN');
    }

    /**
     * Return an unauthorized response (401)
     */
    protected function unauthorizedResponse(string $message = 'Unauthorized'): JsonResponse
    {
        return $this->errorResponse($message, 401, 'UNAUTHORIZED');
    }

    /**
     * Return a validation error response (422)
     */
    protected function validationErrorResponse(
        array $errors,
        string $message = 'Validation failed'
    ): JsonResponse {
        return $this->errorResponse($message, 422, 'VALIDATION_ERROR', $errors);
    }

    /**
     * Return a server error response (500)
     */
    protected function serverErrorResponse(string $message = 'Internal server error'): JsonResponse
    {
        return $this->errorResponse($message, 500, 'SERVER_ERROR');
    }

    /**
     * Return a too many requests response (429)
     */
    protected function tooManyRequestsResponse(
        string $message = 'Too many requests',
        int $retryAfter = 60
    ): JsonResponse {
        return response()->json([
            'success' => false,
            'error' => [
                'code' => 'TOO_MANY_REQUESTS',
                'message' => $message,
                'retry_after' => $retryAfter,
            ],
        ], 429)->header('Retry-After', $retryAfter);
    }

    /**
     * Get error code from HTTP status code
     */
    protected function getErrorCodeFromHttpStatus(int $status): string
    {
        return match ($status) {
            400 => 'BAD_REQUEST',
            401 => 'UNAUTHORIZED',
            403 => 'FORBIDDEN',
            404 => 'NOT_FOUND',
            405 => 'METHOD_NOT_ALLOWED',
            409 => 'CONFLICT',
            422 => 'VALIDATION_ERROR',
            429 => 'TOO_MANY_REQUESTS',
            500 => 'SERVER_ERROR',
            502 => 'BAD_GATEWAY',
            503 => 'SERVICE_UNAVAILABLE',
            default => 'ERROR',
        };
    }
}
