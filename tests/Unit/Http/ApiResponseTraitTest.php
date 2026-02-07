<?php

declare(strict_types=1);

namespace Tests\Unit\Http;

use App\Http\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Pagination\LengthAwarePaginator;
use Tests\TestCase;

class ApiResponseTraitTest extends TestCase
{
    protected object $trait;

    protected function setUp(): void
    {
        parent::setUp();

        $this->trait = new class {
            use ApiResponseTrait {
                successResponse as public;
                errorResponse as public;
                paginatedResponse as public;
                validationErrorResponse as public;
                notFoundResponse as public;
                unauthorizedResponse as public;
                forbiddenResponse as public;
                serverErrorResponse as public;
            }
        };
    }

    public function test_success_response_returns_correct_format(): void
    {
        $response = $this->trait->successResponse(['foo' => 'bar']);

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(200, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        $this->assertTrue($data['success']);
        $this->assertEquals(['foo' => 'bar'], $data['data']);
        $this->assertArrayNotHasKey('message', $data); // Empty message is not included
    }

    public function test_success_response_with_message(): void
    {
        $response = $this->trait->successResponse(['foo' => 'bar'], 'Operation successful');

        $data = json_decode($response->getContent(), true);
        $this->assertEquals('Operation successful', $data['message']);
    }

    public function test_success_response_with_custom_code(): void
    {
        $response = $this->trait->successResponse(['foo' => 'bar'], '', 201);

        $this->assertEquals(201, $response->getStatusCode());
    }

    public function test_success_response_with_meta(): void
    {
        $response = $this->trait->successResponse(['foo' => 'bar'], '', 200, ['extra' => 'info']);

        $data = json_decode($response->getContent(), true);
        $this->assertEquals('info', $data['meta']['extra']);
    }

    public function test_error_response_returns_correct_format(): void
    {
        $response = $this->trait->errorResponse('Something went wrong', 400);

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(400, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        $this->assertFalse($data['success']);
        $this->assertEquals('Something went wrong', $data['error']['message']);
    }

    public function test_error_response_with_error_code(): void
    {
        $response = $this->trait->errorResponse('Something went wrong', 400, 'INVALID_REQUEST');

        $data = json_decode($response->getContent(), true);
        $this->assertEquals('INVALID_REQUEST', $data['error']['code']);
    }

    public function test_error_response_with_errors(): void
    {
        $errors = ['field' => ['Field is required']];
        $response = $this->trait->errorResponse('Validation failed', 422, 'VALIDATION_ERROR', $errors);

        $data = json_decode($response->getContent(), true);
        $this->assertEquals($errors, $data['error']['errors']);
    }

    public function test_validation_error_response(): void
    {
        $errors = ['email' => ['The email field is required.']];
        $response = $this->trait->validationErrorResponse($errors);

        $this->assertEquals(422, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        $this->assertFalse($data['success']);
        $this->assertEquals('VALIDATION_ERROR', $data['error']['code']);
        $this->assertEquals($errors, $data['error']['errors']);
    }

    public function test_not_found_response(): void
    {
        $response = $this->trait->notFoundResponse('Resource not found');

        $this->assertEquals(404, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        $this->assertFalse($data['success']);
        $this->assertEquals('NOT_FOUND', $data['error']['code']);
        $this->assertEquals('Resource not found', $data['error']['message']);
    }

    public function test_unauthorized_response(): void
    {
        $response = $this->trait->unauthorizedResponse();

        $this->assertEquals(401, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        $this->assertEquals('UNAUTHORIZED', $data['error']['code']);
        $this->assertEquals('Unauthorized', $data['error']['message']);
    }

    public function test_forbidden_response(): void
    {
        $response = $this->trait->forbiddenResponse('Access denied');

        $this->assertEquals(403, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        $this->assertEquals('FORBIDDEN', $data['error']['code']);
        $this->assertEquals('Access denied', $data['error']['message']);
    }

    public function test_server_error_response(): void
    {
        $response = $this->trait->serverErrorResponse('Internal server error');

        $this->assertEquals(500, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        $this->assertEquals('SERVER_ERROR', $data['error']['code']);
        $this->assertEquals('Internal server error', $data['error']['message']);
    }

    public function test_paginated_response_returns_correct_format(): void
    {
        $items = collect([
            ['id' => 1, 'name' => 'Item 1'],
            ['id' => 2, 'name' => 'Item 2'],
        ]);

        $paginator = new LengthAwarePaginator(
            $items,
            50,
            15,
            1,
            ['path' => 'http://example.com/items']
        );

        $response = $this->trait->paginatedResponse($paginator);

        $this->assertEquals(200, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        $this->assertTrue($data['success']);
        $this->assertCount(2, $data['data']);
        $this->assertEquals(1, $data['meta']['current_page']);
        $this->assertEquals(4, $data['meta']['last_page']);
        $this->assertEquals(15, $data['meta']['per_page']);
        $this->assertEquals(50, $data['meta']['total']);
        $this->assertArrayHasKey('links', $data);
    }
}
