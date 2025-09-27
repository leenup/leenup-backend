<?php

namespace App\Tests\Api;

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;

class CategoriesTest extends ApiTestCase
{
    public function testCreateCategory(): void
    {
        static::createClient()->request('POST', '/categories', [
            'json' => [
                'title' => 'Développement',
                'description' => 'Compétences liées au développement logiciel',
            ],
            'headers' => [
                'Content-Type' => 'application/ld+json',
            ],
        ]);

        $this->assertResponseStatusCodeSame(201);
        $this->assertJsonContains([
            '@context' => '/contexts/Category',
            '@type' => 'Category',
            'title' => 'Développement',
        ]);
    }
}
