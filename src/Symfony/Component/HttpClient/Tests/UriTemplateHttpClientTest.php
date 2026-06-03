<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpClient\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\HttpClient\UriTemplateHttpClient;

final class UriTemplateHttpClientTest extends TestCase
{
    public function testExpanderIsCalled()
    {
        $client = new UriTemplateHttpClient(
            new MockHttpClient(),
            function (string $url, array $vars): string {
                $this->assertSame('https://foo.tld/{version}/{resource}{?page}', $url);
                $this->assertSame([
                    'version' => 'v2',
                    'resource' => 'users',
                    'page' => 33,
                ], $vars);

                return 'https://foo.tld/v2/users?page=33';
            },
            [
                'version' => 'v2',
            ],
        );
        $this->assertSame('https://foo.tld/v2/users?page=33', $client->request('GET', 'https://foo.tld/{version}/{resource}{?page}', [
            'vars' => [
                'resource' => 'users',
                'page' => 33,
            ],
        ])->getInfo('url'));
    }

    public function testWithOptionsAppendsVarsToDefaultVars()
    {
        $client = new UriTemplateHttpClient(
            new MockHttpClient(),
            function (string $url, array $vars): string {
                $this->assertSame('https://foo.tld/{bar}', $url);
                $this->assertSame([
                    'bar' => 'ccc',
                ], $vars);

                return 'https://foo.tld/ccc';
            },
        );
        $this->assertSame('https://foo.tld/{bar}', $client->request('GET', 'https://foo.tld/{bar}')->getInfo('url'));

        $client = $client->withOptions([
            'vars' => [
                'bar' => 'ccc',
            ],
        ]);
        $this->assertSame('https://foo.tld/ccc', $client->request('GET', 'https://foo.tld/{bar}')->getInfo('url'));
    }

    public function testExpanderIsNotCalledWithEmptyVars()
    {
        $this->expectNotToPerformAssertions();

        $client = new UriTemplateHttpClient(new MockHttpClient(), $this->fail(...));
        $client->request('GET', 'https://foo.tld/bar', [
            'vars' => [],
        ]);
    }

    public function testExpanderIsNotCalledWithNoVarsAtAll()
    {
        $this->expectNotToPerformAssertions();

        $client = new UriTemplateHttpClient(new MockHttpClient(), $this->fail(...));
        $client->request('GET', 'https://foo.tld/bar');
    }

    public function testRequestWithNonArrayVarsOption()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('The "vars" option must be an array.');

        (new UriTemplateHttpClient(new MockHttpClient()))->request('GET', 'https://foo.tld', [
            'vars' => 'should be an array',
        ]);
    }

    public function testWithOptionsWithNonArrayVarsOption()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('The "vars" option must be an array.');

        (new UriTemplateHttpClient(new MockHttpClient()))->withOptions([
            'vars' => new \stdClass(),
        ]);
    }

    public function testVarsOptionIsNotPropagated()
    {
        $client = new UriTemplateHttpClient(
            new MockHttpClient(function (string $method, string $url, array $options): MockResponse {
                $this->assertArrayNotHasKey('vars', $options);

                return new MockResponse();
            }),
            static fn (): string => 'ccc',
        );

        $client->withOptions([
            'vars' => [
                'foo' => 'bar',
            ],
        ])->request('GET', 'https://foo.tld', [
            'vars' => [
                'foo2' => 'bar2',
            ],
        ]);
    }
}
