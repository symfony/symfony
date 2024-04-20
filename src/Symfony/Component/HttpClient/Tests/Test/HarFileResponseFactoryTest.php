<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpClient\Tests\Test;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\Exception\TransportException;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Test\HarFileResponseFactory;

class HarFileResponseFactoryTest extends TestCase
{
    private string $fixtureDir;

    protected function setUp(): void
    {
        $this->fixtureDir = \dirname(__DIR__).'/Fixtures/har';
    }

    public function testResponseGeneration()
    {
        $factory = new HarFileResponseFactory("{$this->fixtureDir}/symfony.com_archive.har");
        $client = new MockHttpClient($factory, 'https://symfony.com');

        $response = $client->request('GET', '/releases.json');

        $this->assertSame(200, $response->getStatusCode());

        $body = $response->toArray();
        $headers = $response->getHeaders();

        $this->assertCount(23, $headers);
        $this->assertArrayHasKey('symfony_versions', $body);
    }

    public function testResponseGenerationWithPayload()
    {
        $factory = new HarFileResponseFactory("{$this->fixtureDir}/graphql.github.io_archive.har");
        $client = new MockHttpClient($factory, 'https://swapi-graphql.netlify.app');
        $query = <<<'GRAPHQL'
{
  allFilms(first: 5) {
    edges {
      node {
        title
        director
      }
    }
    totalCount
  }
}
GRAPHQL;

        $response = $client->request('POST', '/graphql', [
            'json' => ['query' => $query],
        ]);

        $this->assertSame(200, $response->getStatusCode());

        $body = $response->toArray();
        // In fixture file first response is "allPlanets"
        $this->assertArrayHasKey('allFilms', $body['data']);
    }

    public function testFactoryThrowsWhenUnableToMatchResponse()
    {
        $this->expectException(TransportException::class);
        $factory = new HarFileResponseFactory("{$this->fixtureDir}/symfony.com_archive.har");
        $client = new MockHttpClient($factory, 'https://symfony.com');

        $client->request('GET', '/not-found');
    }

    public function testFactoryThrowsWhenJsonIsInvalid()
    {
        $this->expectException(\JsonException::class);
        $factory = new HarFileResponseFactory("{$this->fixtureDir}/invalid_archive.har");
        $client = new MockHttpClient($factory, 'https://symfony.com');

        $client->request('GET', '/releases.json');
    }
}
