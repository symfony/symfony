<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\BrowserKit\Tests;

use Symfony\Component\BrowserKit\CookieJar;
use Symfony\Component\BrowserKit\History;
use Symfony\Component\BrowserKit\HttpBrowser;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

class HttpBrowserTest extends AbstractBrowserTest
{
    public function getBrowser(array $server = [], ?History $history = null, ?CookieJar $cookieJar = null)
    {
        return new TestHttpClient($server, $history, $cookieJar);
    }

    /**
     * @dataProvider validContentTypes
     */
    public function testRequestHeaders(array $requestArguments, array $expectedArguments)
    {
        $client = $this->createMock(HttpClientInterface::class);
        $client
            ->expects($this->once())
            ->method('request')
            ->with(...$expectedArguments)
            ->willReturn($this->createMock(ResponseInterface::class));

        $browser = new HttpBrowser($client);
        $browser->request(...$requestArguments);
    }

    public static function validContentTypes()
    {
        $defaultHeaders = ['user-agent' => 'Symfony BrowserKit', 'host' => 'example.com'];
        yield 'GET/HEAD' => [
            ['GET', 'http://example.com/', ['key' => 'value']],
            ['GET', 'http://example.com/', ['headers' => $defaultHeaders, 'body' => '', 'max_redirects' => 0]],
        ];
        yield 'empty form' => [
            ['POST', 'http://example.com/'],
            ['POST', 'http://example.com/', ['headers' => $defaultHeaders, 'body' => '', 'max_redirects' => 0]],
        ];
        yield 'form' => [
            ['POST', 'http://example.com/', ['key' => 'value', 'key2' => 'value']],
            ['POST', 'http://example.com/', ['headers' => $defaultHeaders + ['Content-Type' => 'application/x-www-form-urlencoded'], 'body' => 'key=value&key2=value', 'max_redirects' => 0]],
        ];
        yield 'content' => [
            ['POST', 'http://example.com/', [], [], [], 'content'],
            ['POST', 'http://example.com/', ['headers' => $defaultHeaders + ['Content-Type: text/plain; charset=utf-8', 'Content-Transfer-Encoding: 8bit'], 'body' => 'content', 'max_redirects' => 0]],
        ];
        yield 'POST JSON' => [
            ['POST', 'http://example.com/', [], [], ['CONTENT_TYPE' => 'application/json'], '["content"]'],
            ['POST', 'http://example.com/', ['headers' => $defaultHeaders + ['content-type' => 'application/json'], 'body' => '["content"]', 'max_redirects' => 0]],
        ];
        yield 'custom header with HTTP_ prefix' => [
            ['PUT', 'http://example.com/', [], [], ['HTTP_CONTENT_TYPE' => 'application/json'], '["content"]'],
            ['PUT', 'http://example.com/', ['headers' => $defaultHeaders + ['content-type' => 'application/json'], 'body' => '["content"]', 'max_redirects' => 0]],
        ];
        yield 'modify notation of custom header with HTTP_ prefix' => [
            ['PUT', 'http://example.com/', [], [], ['HTTP_Content-Type' => 'application/json'], '["content"]'],
            ['PUT', 'http://example.com/', ['headers' => $defaultHeaders + ['content-type' => 'application/json'], 'body' => '["content"]', 'max_redirects' => 0]],
        ];
        yield 'modify notation of custom header' => [
            ['PUT', 'http://example.com/', [], [], ['Content-Type' => 'application/json'], '["content"]'],
            ['PUT', 'http://example.com/', ['headers' => $defaultHeaders + ['content-type' => 'application/json'], 'body' => '["content"]', 'max_redirects' => 0]],
        ];
        yield 'GET JSON' => [
            ['GET', 'http://example.com/jsonrpc', [], [], ['CONTENT_TYPE' => 'application/json'], '["content"]'],
            ['GET', 'http://example.com/jsonrpc', ['headers' => $defaultHeaders + ['content-type' => 'application/json'], 'body' => '["content"]', 'max_redirects' => 0]],
        ];
        yield 'HEAD JSON' => [
            ['HEAD', 'http://example.com/jsonrpc', [], [], ['CONTENT_TYPE' => 'application/json'], '["content"]'],
            ['HEAD', 'http://example.com/jsonrpc', ['headers' => $defaultHeaders + ['content-type' => 'application/json'], 'body' => '["content"]', 'max_redirects' => 0]],
        ];
    }

    public function testMultiPartRequestWithSingleFile()
    {
        $client = $this->createMock(HttpClientInterface::class);
        $client
            ->expects($this->once())
            ->method('request')
            ->with('POST', 'http://example.com/', $this->callback(function ($options) {
                $this->assertStringContainsString('Content-Type: multipart/form-data', implode('', $options['headers']));
                $this->assertInstanceOf(\Generator::class, $options['body']);
                $values = implode('', iterator_to_array($options['body'], false));
                $this->assertStringContainsString('name="foo[file]"', $values);
                $this->assertStringContainsString('my_file', $values);
                $this->assertStringContainsString('name="foo[bar]"', $values);
                $this->assertStringContainsString('foo2', $values);

                return true;
            }))
            ->willReturn($this->createMock(ResponseInterface::class));

        $browser = new HttpBrowser($client);
        $path = tempnam(sys_get_temp_dir(), 'http');
        file_put_contents($path, 'my_file');
        $browser->request('POST', 'http://example.com/', ['foo' => ['bar' => 'foo2']], ['foo' => ['file' => ['tmp_name' => $path, 'name' => 'foo']]]);
    }

    public function testMultiPartRequestWithNormalFlatArray()
    {
        $client = $this->createMock(HttpClientInterface::class);
        $this->expectClientToSendRequestWithFiles($client, ['file1_content', 'file2_content']);

        $browser = new HttpBrowser($client);
        $browser->request('POST', 'http://example.com/', [], [
            'file1' => $this->getUploadedFile('file1'),
            'file2' => $this->getUploadedFile('file2'),
        ]);
    }

    public function testMultiPartRequestWithNormalNestedArray()
    {
        $client = $this->createMock(HttpClientInterface::class);
        $this->expectClientToSendRequestWithFiles($client, ['file1_content', 'file2_content']);

        $browser = new HttpBrowser($client);
        $browser->request('POST', 'http://example.com/', [], [
            'level1' => [
                'level2' => [
                    'file1' => $this->getUploadedFile('file1'),
                    'file2' => $this->getUploadedFile('file2'),
                ],
            ],
        ]);
    }

    public function testMultiPartRequestWithBracketedArray()
    {
        $client = $this->createMock(HttpClientInterface::class);
        $this->expectClientToSendRequestWithFiles($client, ['file1_content', 'file2_content']);

        $browser = new HttpBrowser($client);
        $browser->request('POST', 'http://example.com/', [], [
            'form[file1]' => $this->getUploadedFile('file1'),
            'form[file2]' => $this->getUploadedFile('file2'),
        ]);
    }

    public function testMultiPartRequestWithInvalidItem()
    {
        $client = $this->createMock(HttpClientInterface::class);
        $this->expectClientToSendRequestWithFiles($client, ['file1_content']);

        $browser = new HttpBrowser($client);
        $browser->request('POST', 'http://example.com/', [], [
            'file1' => $this->getUploadedFile('file1'),
            'file2' => 'INVALID',
        ]);
    }

    public function testMultiPartRequestWithAdditionalParameters()
    {
        $client = $this->createMock(HttpClientInterface::class);
        $this->expectClientToSendRequestWithFiles($client, ['file1_content', 'baz']);

        $browser = new HttpBrowser($client);
        $browser->request('POST', 'http://example.com/', ['bar' => 'baz'], [
            'file1' => $this->getUploadedFile('file1'),
        ]);
    }

    public function testMultiPartRequestWithAdditionalParametersOfTheSameName()
    {
        $client = $this->createMock(HttpClientInterface::class);
        $this->expectClientToNotSendRequestWithFiles($client, ['baz']);

        $browser = new HttpBrowser($client);
        $browser->request('POST', 'http://example.com/', ['file1' => 'baz'], [
            'file1' => $this->getUploadedFile('file1'),
        ]);
    }

    /**
     * @dataProvider forwardSlashesRequestPathProvider
     */
    public function testMultipleForwardSlashesRequestPath(string $requestPath)
    {
        $client = $this->createMock(HttpClientInterface::class);
        $client
            ->expects($this->once())
            ->method('request')
            ->with('GET', 'http://localhost'.$requestPath)
            ->willReturn($this->createMock(ResponseInterface::class));
        $browser = new HttpBrowser($client);
        $browser->request('GET', $requestPath);
    }

    public static function forwardSlashesRequestPathProvider()
    {
        return [
            'one slash' => ['/'],
            'two slashes' => ['//'],
            'multiple slashes' => ['////'],
        ];
    }

    private function uploadFile(string $data): string
    {
        $path = tempnam(sys_get_temp_dir(), 'http');
        file_put_contents($path, $data);

        return $path;
    }

    private function getUploadedFile(string $name): array
    {
        return [
            'tmp_name' => $this->uploadFile($name.'_content'),
            'name' => $name.'_name',
        ];
    }

    protected function expectClientToSendRequestWithFiles(HttpClientInterface $client, $fileContents)
    {
        $client
            ->expects($this->once())
            ->method('request')
            ->with('POST', 'http://example.com/', $this->callback(function ($options) use ($fileContents) {
                $this->assertStringContainsString('Content-Type: multipart/form-data', implode('', $options['headers']));
                $this->assertInstanceOf(\Generator::class, $options['body']);
                $body = implode('', iterator_to_array($options['body'], false));
                foreach ($fileContents as $content) {
                    $this->assertStringContainsString($content, $body);
                }

                return true;
            }))
            ->willReturn($this->createMock(ResponseInterface::class));
    }

    protected function expectClientToNotSendRequestWithFiles(HttpClientInterface $client, $fileContents)
    {
        $client
            ->expects($this->once())
            ->method('request')
            ->with('POST', 'http://example.com/', $this->callback(function ($options) use ($fileContents) {
                $this->assertStringContainsString('Content-Type: multipart/form-data', implode('', $options['headers']));
                $this->assertInstanceOf(\Generator::class, $options['body']);
                $body = implode('', iterator_to_array($options['body'], false));
                foreach ($fileContents as $content) {
                    $this->assertStringNotContainsString($content, $body);
                }

                return true;
            }))
            ->willReturn($this->createMock(ResponseInterface::class));
    }
}
