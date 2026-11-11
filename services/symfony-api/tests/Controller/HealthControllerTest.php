<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class HealthControllerTest extends WebTestCase
{
    public function testHealthAnswersOk(): void
    {
        $client = self::createClient();
        $client->request('GET', '/health');

        self::assertResponseStatusCodeSame(200);
        self::assertResponseHeaderSame('Content-Type', 'application/json');
        self::assertStringContainsString('no-store', (string) $client->getResponse()->headers->get('Cache-Control'));
        self::assertJsonStringEqualsJsonString('{"status":"ok"}', (string) $client->getResponse()->getContent());
    }
}
