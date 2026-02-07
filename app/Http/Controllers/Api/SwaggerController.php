<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use OpenApi\Attributes as OA;

#[OA\Info(
    version: '1.0.0',
    title: 'VSISPanel API',
    description: 'VSISPanel - Web Hosting Control Panel API Documentation',
    contact: new OA\Contact(
        name: 'VSISPanel Support',
        email: 'admin@vsispanel.local'
    ),
    license: new OA\License(
        name: 'MIT',
        url: 'https://opensource.org/licenses/MIT'
    )
)]
#[OA\Server(
    url: L5_SWAGGER_CONST_HOST,
    description: 'API Server'
)]
#[OA\SecurityScheme(
    securityScheme: 'bearerAuth',
    type: 'http',
    scheme: 'bearer',
    bearerFormat: 'JWT',
    description: 'Enter your Bearer token'
)]
#[OA\Tag(name: 'Authentication', description: 'Authentication endpoints')]
#[OA\Tag(name: 'Dashboard', description: 'Dashboard endpoints')]
#[OA\Tag(name: 'Health', description: 'Health check endpoints')]
#[OA\Schema(
    schema: 'SuccessResponse',
    properties: [
        new OA\Property(property: 'success', type: 'boolean', example: true),
        new OA\Property(property: 'data', type: 'object'),
        new OA\Property(property: 'message', type: 'string', example: 'Operation successful'),
    ]
)]
#[OA\Schema(
    schema: 'ErrorResponse',
    properties: [
        new OA\Property(property: 'success', type: 'boolean', example: false),
        new OA\Property(
            property: 'error',
            type: 'object',
            properties: [
                new OA\Property(property: 'code', type: 'string', example: 'ERROR_CODE'),
                new OA\Property(property: 'message', type: 'string', example: 'Error description'),
                new OA\Property(property: 'errors', type: 'object'),
            ]
        ),
    ]
)]
#[OA\Schema(
    schema: 'PaginatedResponse',
    properties: [
        new OA\Property(property: 'success', type: 'boolean', example: true),
        new OA\Property(
            property: 'data',
            type: 'array',
            items: new OA\Items(type: 'object')
        ),
        new OA\Property(
            property: 'meta',
            type: 'object',
            properties: [
                new OA\Property(property: 'current_page', type: 'integer', example: 1),
                new OA\Property(property: 'last_page', type: 'integer', example: 10),
                new OA\Property(property: 'per_page', type: 'integer', example: 15),
                new OA\Property(property: 'total', type: 'integer', example: 150),
            ]
        ),
        new OA\Property(
            property: 'links',
            type: 'object',
            properties: [
                new OA\Property(property: 'first', type: 'string'),
                new OA\Property(property: 'last', type: 'string'),
                new OA\Property(property: 'prev', type: 'string', nullable: true),
                new OA\Property(property: 'next', type: 'string', nullable: true),
            ]
        ),
    ]
)]
#[OA\Schema(
    schema: 'User',
    properties: [
        new OA\Property(property: 'id', type: 'string', format: 'uuid'),
        new OA\Property(property: 'name', type: 'string', example: 'John Doe'),
        new OA\Property(property: 'email', type: 'string', format: 'email', example: 'john@example.com'),
        new OA\Property(property: 'role', type: 'string', enum: ['admin', 'reseller', 'user']),
        new OA\Property(property: 'status', type: 'string', enum: ['active', 'suspended', 'terminated']),
        new OA\Property(property: 'locale', type: 'string', example: 'vi'),
        new OA\Property(property: 'timezone', type: 'string', example: 'Asia/Ho_Chi_Minh'),
        new OA\Property(property: 'two_factor_enabled', type: 'boolean'),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
        new OA\Property(property: 'updated_at', type: 'string', format: 'date-time'),
    ]
)]
class SwaggerController
{
    // This class only contains OpenAPI attributes
}
