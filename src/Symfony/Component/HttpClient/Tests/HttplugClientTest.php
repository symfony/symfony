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

use GuzzleHttp\Promise\FulfilledPromise as GuzzleFulfilledPromise;
use Http\Client\Exception\NetworkException;
use Http\Client\Exception\RequestException;
use Http\Promise\FulfilledPromise;
use Http\Promise\Promise;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\HttpClient\Exception\TransportException;
use Symfony\Component\HttpClient\HttplugClient;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\NativeHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Contracts\HttpClient\Test\TestHttpServer;

class HttplugClientTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        TestHttpServer::start();
    }

    public function testSendRequest()
    {
        $client = new HttplugClient(new NativeHttpClient());

        $response = $client->sendRequest($client->createRequest('GET', 'http://localhost:8057'));

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('application/json', $response->getHeaderLine('content-type'));

        $body = json_decode((string) $response->getBody(), true);

        self::assertSame('HTTP/1.1', $body['SERVER_PROTOCOL']);
    }

    public function testSendAsyncRequest()
    {
        $client = new HttplugClient(new NativeHttpClient());

        $promise = $client->sendAsyncRequest($client->createRequest('GET', 'http://localhost:8057'));
        $successCallableCalled = false;
        $failureCallableCalled = false;
        $promise->then(function (ResponseInterface $response) use (&$successCallableCalled) {
            $successCallableCalled = true;

            return $response;
        }, function (\Exception $exception) use (&$failureCallableCalled) {
            $failureCallableCalled = true;

            throw $exception;
        });

        self::assertEquals(Promise::PENDING, $promise->getState());

        $response = $promise->wait(true);
        self::assertTrue($successCallableCalled, '$promise->then() was never called.');
        self::assertFalse($failureCallableCalled, 'Failure callable should not be called when request is successful.');
        self::assertEquals(Promise::FULFILLED, $promise->getState());

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('application/json', $response->getHeaderLine('content-type'));

        $body = json_decode((string) $response->getBody(), true);

        self::assertSame('HTTP/1.1', $body['SERVER_PROTOCOL']);
    }

    public function testWait()
    {
        $client = new HttplugClient(new NativeHttpClient());

        $successCallableCalled = false;
        $failureCallableCalled = false;
        $client->sendAsyncRequest($client->createRequest('GET', 'http://localhost:8057/timeout-body'))
            ->then(function (ResponseInterface $response) use (&$successCallableCalled) {
                $successCallableCalled = true;

                return $response;
            }, function (\Exception $exception) use (&$failureCallableCalled) {
                $failureCallableCalled = true;

                throw $exception;
            });

        $client->wait(0);
        self::assertFalse($successCallableCalled, '$promise->then() should not be called yet.');

        $client->wait();
        self::assertTrue($successCallableCalled, '$promise->then() should have been called.');
        self::assertFalse($failureCallableCalled, 'Failure callable should not be called when request is successful.');
    }

    public function testPostRequest()
    {
        $client = new HttplugClient(new NativeHttpClient());

        $request = $client->createRequest('POST', 'http://localhost:8057/post')
            ->withBody($client->createStream('foo=0123456789'));

        $response = $client->sendRequest($request);
        $body = json_decode((string) $response->getBody(), true);

        self::assertSame(['foo' => '0123456789', 'REQUEST_METHOD' => 'POST'], $body);
    }

    public function testNetworkException()
    {
        $client = new HttplugClient(new NativeHttpClient());

        self::expectException(NetworkException::class);
        $client->sendRequest($client->createRequest('GET', 'http://localhost:8058'));
    }

    public function testAsyncNetworkException()
    {
        $client = new HttplugClient(new NativeHttpClient());

        $promise = $client->sendAsyncRequest($client->createRequest('GET', 'http://localhost:8058'));
        $successCallableCalled = false;
        $failureCallableCalled = false;
        $promise->then(function (ResponseInterface $response) use (&$successCallableCalled) {
            $successCallableCalled = true;

            return $response;
        }, function (\Exception $exception) use (&$failureCallableCalled) {
            $failureCallableCalled = true;

            throw $exception;
        });

        $promise->wait(false);
        self::assertFalse($successCallableCalled, 'Success callable should not be called when request fails.');
        self::assertTrue($failureCallableCalled, 'Failure callable was never called.');
        self::assertEquals(Promise::REJECTED, $promise->getState());

        self::expectException(NetworkException::class);
        $promise->wait(true);
    }

    public function testRequestException()
    {
        $client = new HttplugClient(new NativeHttpClient());

        self::expectException(RequestException::class);
        $client->sendRequest($client->createRequest('BAD.METHOD', 'http://localhost:8057'));
    }

    public function testRetry404()
    {
        $client = new HttplugClient(new NativeHttpClient());

        $successCallableCalled = false;
        $failureCallableCalled = false;

        $promise = $client
            ->sendAsyncRequest($client->createRequest('GET', 'http://localhost:8057/404'))
            ->then(
                function (ResponseInterface $response) use (&$successCallableCalled, $client) {
                    self::assertSame(404, $response->getStatusCode());
                    $successCallableCalled = true;

                    return $client->sendAsyncRequest($client->createRequest('GET', 'http://localhost:8057'));
                },
                function (\Exception $exception) use (&$failureCallableCalled) {
                    $failureCallableCalled = true;

                    throw $exception;
                }
            )
        ;

        $response = $promise->wait(true);

        self::assertTrue($successCallableCalled);
        self::assertFalse($failureCallableCalled);
        self::assertSame(200, $response->getStatusCode());
    }

    public function testRetryNetworkError()
    {
        $client = new HttplugClient(new NativeHttpClient());

        $successCallableCalled = false;
        $failureCallableCalled = false;

        $promise = $client
            ->sendAsyncRequest($client->createRequest('GET', 'http://localhost:8057/chunked-broken'))
            ->then(function (ResponseInterface $response) use (&$successCallableCalled) {
                $successCallableCalled = true;

                return $response;
            }, function (\Exception $exception) use (&$failureCallableCalled, $client) {
                self::assertSame(NetworkException::class, \get_class($exception));
                self::assertSame(TransportException::class, \get_class($exception->getPrevious()));
                $failureCallableCalled = true;

                return $client->sendAsyncRequest($client->createRequest('GET', 'http://localhost:8057'));
            })
        ;

        $response = $promise->wait(true);

        self::assertFalse($successCallableCalled);
        self::assertTrue($failureCallableCalled);
        self::assertSame(200, $response->getStatusCode());
    }

    public function testRetryEarlierError()
    {
        $isFirstRequest = true;
        $errorMessage = 'Error occurred before making the actual request.';

        $client = new HttplugClient(new MockHttpClient(function () use (&$isFirstRequest, $errorMessage) {
            if ($isFirstRequest) {
                $isFirstRequest = false;
                throw new TransportException($errorMessage);
            }

            return new MockResponse('OK', ['http_code' => 200]);
        }));

        $request = $client->createRequest('GET', 'http://test');

        $successCallableCalled = false;
        $failureCallableCalled = false;

        $promise = $client
            ->sendAsyncRequest($request)
            ->then(
                function (ResponseInterface $response) use (&$successCallableCalled) {
                    $successCallableCalled = true;

                    return $response;
                },
                function (\Exception $exception) use ($errorMessage, &$failureCallableCalled, $client, $request) {
                    self::assertSame(NetworkException::class, \get_class($exception));
                    self::assertSame($errorMessage, $exception->getMessage());
                    $failureCallableCalled = true;

                    // Ensure arbitrary levels of promises work.
                    return (new FulfilledPromise(null))->then(function () use ($client, $request) {
                        return (new GuzzleFulfilledPromise(null))->then(function () use ($client, $request) {
                            return $client->sendAsyncRequest($request);
                        });
                    });
                }
            )
        ;

        $response = $promise->wait(true);

        self::assertFalse($successCallableCalled);
        self::assertTrue($failureCallableCalled);
        self::assertSame(200, $response->getStatusCode());
        self::assertSame('OK', (string) $response->getBody());
    }
}
