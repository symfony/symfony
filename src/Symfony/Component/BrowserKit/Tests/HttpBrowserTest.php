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
                'raw_headers' => $this->nextResponse->getHeaders(),
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
}
