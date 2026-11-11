<?php

declare(strict_types=1);

namespace App\Http;

use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * RFC 9457 error body ("problem details"), the one error format of the API.
 */
final class ProblemResponse
{
    public static function create(int $status, string $type, string $title, string $detail): JsonResponse
    {
        $response = new JsonResponse(
            ['type' => $type, 'title' => $title, 'status' => $status, 'detail' => $detail],
            $status,
        );
        $response->headers->set('Content-Type', 'application/problem+json');
        $response->headers->set('Cache-Control', 'no-store');

        return $response;
    }
}
