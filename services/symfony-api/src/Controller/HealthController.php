<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Liveness check: answers as soon as Symfony boots. It does not touch the database.
 */
final class HealthController extends AbstractController
{
    #[Route('/health', name: 'health', methods: ['GET'])]
    public function __invoke(): JsonResponse
    {
        $response = new JsonResponse(['status' => 'ok']);
        $response->headers->set('Cache-Control', 'no-store');

        return $response;
    }
}
