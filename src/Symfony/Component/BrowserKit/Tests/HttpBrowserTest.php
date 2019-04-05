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
use Symfony\Component\BrowserKit\Response;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

class SpecialHttpResponse extends Response
{
}

class TestHttpClient extends HttpBrowser
{
    protected $nextResponse = null;
    protected $nextScript = null;

    public function __construct(array $server = [], History $history = null, CookieJar $cookieJar = null)
    {
        $client = new MockHttpClient(function (string $method, string $url, array $options) {
            if (null === $this->nextResponse) {
                return new MockResponse();
            }

            return new MockResponse($this->nextResponse->getContent(), [
                'http_code' => $this->nextResponse->getStatusCode(),
                'response_headers' => $this->nextResponse->getHeaders(),
            ]);
        });
        parent::__construct($client);

        $this->setServerParameters($server);
        $this->history = $history ?? new History();
        $this->cookieJar = $cookieJar ?? new CookieJar();
    }

    public function setNextResponse(Response $response)
    {
        $this->nextResponse = $response;
    }

    public function setNextScript($script)
    {
        $this->nextScript = $script;
    }

    protected function filterResponse($response)
    {
        if ($response instanceof SpecialHttpResponse) {
            return new Response($response->getContent(), $response->getStatusCode(), $response->getHeaders());
        }

        return $response;
    }

    protected function doRequest($request)
    {
        $response = parent::doRequest($request);

        if (null === $this->nextResponse) {
            return $response;
        }

        $class = \get_class($this->nextResponse);
        $response = new $class($response->getContent(), $response->getStatusCode(), $response->getHeaders());
        $this->nextResponse = null;

        return $response;
    }

    protected function getScript($request)
    {
        $r = new \ReflectionClass('Symfony\Component\BrowserKit\Response');
        $path = $r->getFileName();

        return <<<EOF
<?php

require_once('$path');

echo serialize($this->nextScript);
EOF;
    }
}

class HttpBrowserTest extends AbstractBrowserTest
{
    public function getBrowser(array $server = [], History $history = null, CookieJar $cookieJar = null)
    {
        return new TestHttpClient($server, $history, $cookieJar);
    }

    public function testGetInternalResponse()
    {
        $client = $this->getBrowser();
        $client->setNextResponse(new SpecialHttpResponse('foo'));
        $client->request('GET', 'http://example.com/');

        $this->assertInstanceOf('Symfony\Component\BrowserKit\Response', $client->getInternalResponse());
        $this->assertNotInstanceOf('Symfony\Component\BrowserKit\Tests\SpecialHttpResponse', $client->getInternalResponse());
        $this->assertInstanceOf('Symfony\Component\BrowserKit\Tests\SpecialHttpResponse', $client->getResponse());
    }

    /**
     * @dataProvider validContentTypes
     */
    public function testRequestHeaders(array $request, array $exepectedCall)
    {
        $client = $this->createMock(HttpClientInterface::class);
        $client
            ->expects($this->once())
            ->method('request')
            ->with(...$exepectedCall)
            ->willReturn($this->createMock(ResponseInterface::class));

        $browser = new HttpBrowser($client);
        $browser->request(...$request);
    }

    public function validContentTypes()
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
    }

    public function testMultiPartRequest()
    {
        $client = $this->createMock(HttpClientInterface::class);
        $client
            ->expects($this->once())
            ->method('request')
            ->with('POST', 'http://example.com/', $this->callback(function ($options) {
                $this->assertContains('Content-Type: multipart/form-data', implode('', $options['headers']));
                $this->assertInstanceOf('\Generator', $options['body']);
                $this->assertContains('my_file', implode('', iterator_to_array($options['body'])));

                return true;
            }))
            ->willReturn($this->createMock(ResponseInterface::class));

        $browser = new HttpBrowser($client);
        $path = tempnam(sys_get_temp_dir(), 'http');
        file_put_contents($path, 'my_file');
        $browser->request('POST', 'http://example.com/', [], ['file' => ['tmp_name' => $path, 'name' => 'foo']]);
    }
}
