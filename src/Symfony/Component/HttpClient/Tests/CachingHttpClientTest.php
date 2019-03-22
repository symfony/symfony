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

use Symfony\Component\HttpClient\CachingHttpClient;
use Symfony\Component\HttpClient\Exception\TransportException;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\HttpKernel\HttpCache\StoreInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;
use Symfony\Contracts\HttpClient\Test\HttpClientTestCase;

class TestCachingHttpClient extends CachingHttpClient
{
    public function request(string $method, string $url, array $options = []): ResponseInterface
    {
        try {
            $response = parent::request($method, $url, $options);
//        dump('-----------------------------------'.PHP_EOL.__FILE__.' l.'.__LINE__, $response, '-----------------------------------');
            return $response;
        } catch (TransportException $exception) {
            return new MockResponse('', [
                'error' => $exception->getMessage(),
            ]);
        }
    }
}

class CachingHttpClientTest extends HttpClientTestCase
{
    protected function getHttpClient(string $testCase): HttpClientInterface
    {
        $headers = [
            'Host: localhost:8057',
            'Content-Type: application/json',
        ];

        $body = '{
            "SERVER_PROTOCOL": "HTTP/1.1",
            "SERVER_NAME": "127.0.0.1",
            "REQUEST_URI": "/",
            "REQUEST_METHOD": "GET",
            "HTTP_FOO": "baR",
            "HTTP_HOST": "localhost:8057"
        }';

        $bodyToArray = json_decode($body, true);

        $client = new MockHttpClient(function (string $method, string $url, array $options) use ($headers, $body, $bodyToArray, $testCase) {
            switch ($testCase) {
                case 'testUnsupportedOption':
                    $this->markTestSkipped('As CachingHttpClientTest is using MockHttpClient which accepts any options by default');
                    break;

                case 'testChunkedEncoding':
                    $this->markTestSkipped("As CachingHttpClientTest is using MockHttpClient which doesn't dechunk");
                    break;

                case 'testGzipBroken':
                    $this->markTestSkipped("As CachingHttpClientTest is using MockHttpClient which doesn't unzip");
                    break;

                case 'testDestruct':
                    $this->markTestSkipped("As CachingHttpClientTest is using MockHttpClient which doesn't timeout on destruct");
                    break;

                case 'testGetRequest':
                    if (preg_match('/length-broken$/', $url)) {
                        $headers = [
                            'Host: localhost:8057',
                            'Content-Length: 1000',
                            'Content-Type: application/json',
                        ];
                    }

                    array_unshift($headers, 'HTTP/1.1 200 OK');
                    break;

                case 'testClientError':
                case 'testIgnoreErrors':
                    if (preg_match('/404$/', $url)) {
                        array_unshift($headers, 'HTTP/1.1 404 Not Found');
                    }

                    break;

                case 'testDnsError':
                    return new MockResponse('', ['error' => 'DSN error']);

                case 'testInlineAuth':
                    $bodyToArray['PHP_AUTH_USER'] = 'foo';
                    $bodyToArray['PHP_AUTH_PW'] = 'bar=bar';
                    array_unshift($headers, 'HTTP/1.1 200 OK');
                    $body = json_encode($bodyToArray);
                    break;

                case 'testRedirects':
                    $bodyToArray['HTTP_AUTHORIZATION'] = 'Basic Zm9vOmJhcg==';
                    array_unshift($headers, 'HTTP/1.1 200 OK');
                    $body = json_encode($bodyToArray);
                    break;

                case 'testPostJson':
                    $bodyToArray['content-type'] = 'application/json';
                    array_unshift($headers, 'HTTP/1.1 200 OK');
                    $body = json_encode($bodyToArray);
                    break;

                case 'testTimeoutOnAccess':
                    $mock = $this->getMockBuilder(ResponseInterface::class)->getMock();
                    $mock->expects($this->any())
                        ->method('getHeaders')
                        ->willThrowException(new TransportException('Timeout'));

                    return $mock;

                //            case 'testResolve':
                //                $responses[] = new MockResponse($body, ['raw_headers' => $headers]);
                //                $responses[] = new MockResponse($body, ['raw_headers' => $headers]);
                //                $responses[] = $client->request('GET', 'http://symfony.com:8057/');
                //                break;

                case 'testTimeoutOnStream':
                case 'testUncheckedTimeoutThrows':
                    $body = ['<1>', '', '<2>'];
                    break;
                case 'testHttpVersion':
                    array_unshift($headers, sprintf('HTTP/%s 200 OK', $options['http_version']));
                    $body = str_replace('"SERVER_PROTOCOL": "HTTP/1.1"', sprintf('"SERVER_PROTOCOL": "HTTP/%s"', $options['http_version']), $body);
                    break;

                default:
                    array_unshift($headers, 'HTTP/1.1 200 OK');
                    break;
            }

            $options['raw_headers'] = $headers;
            return new MockResponse($body, $options);
        });

        $storeInterface = $this->createMock(StoreInterface::class);

        return new TestCachingHttpClient($client, $storeInterface);
    }
}
