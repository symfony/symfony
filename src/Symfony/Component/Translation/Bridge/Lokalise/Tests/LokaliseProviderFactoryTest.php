<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Translation\Bridge\Lokalise\Tests;

use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\Translation\Bridge\Lokalise\LokaliseProviderFactory;
use Symfony\Component\Translation\Provider\Dsn;
use Symfony\Component\Translation\Provider\ProviderFactoryInterface;
use Symfony\Component\Translation\Test\ProviderFactoryTestCase;

class LokaliseProviderFactoryTest extends ProviderFactoryTestCase
{
    public static function supportsProvider(): iterable
    {
        yield [true, 'lokalise://PROJECT_ID:API_KEY@default'];
        yield [false, 'somethingElse://PROJECT_ID:API_KEY@default'];
    }

    public static function unsupportedSchemeProvider(): iterable
    {
        yield ['somethingElse://PROJECT_ID:API_KEY@default'];
    }

    public static function createProvider(): iterable
    {
        yield [
            'lokalise://api.lokalise.com',
            'lokalise://PROJECT_ID:API_KEY@default',
        ];
    }

    public static function incompleteDsnProvider(): iterable
    {
        yield ['lokalise://default'];
    }

    public function testBaseUri()
    {
        $response = new MockResponse(json_encode(['files' => []]));
        $httpClient = new MockHttpClient([$response]);
        $factory = new LokaliseProviderFactory($httpClient, $this->getLogger(), $this->getDefaultLocale(), $this->getLoader());
        $provider = $factory->create(new Dsn('lokalise://PROJECT_ID:API_KEY@default'));

        // Make a real HTTP request.
        $provider->read(['messages'], ['en']);

        $this->assertMatchesRegularExpression('/https:\/\/api.lokalise.com\/api2\/projects\/PROJECT_ID\/*/', $response->getRequestUrl());
    }

    public function createFactory(): ProviderFactoryInterface
    {
        return new LokaliseProviderFactory($this->getClient(), $this->getLogger(), $this->getDefaultLocale(), $this->getLoader());
    }
}
