<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpClient;

use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\HttpClient\Response\ResponseStream;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\HttpCache\HttpCache;
use Symfony\Component\HttpKernel\HttpCache\StoreInterface;
use Symfony\Component\HttpKernel\HttpClientKernel;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;
use Symfony\Contracts\HttpClient\ResponseStreamInterface;
use Symfony\Contracts\Service\ResetInterface;

/**
 * Adds caching on top of an HTTP client.
 *
 * The implementation buffers responses in memory and doesn't stream directly from the network.
 * You can disable/enable this layer by setting option "no_cache" under "extra" to true/false.
 * By default, caching is enabled unless the "buffer" option is set to false.
 *
 * @author Nicolas Grekas <p@tchwork.com>
 */
class CachingHttpClient implements HttpClientInterface, ResetInterface
{
    use HttpClientTrait;

    private HttpClientInterface $client;
    private HttpCache $cache;
    private array $defaultOptions = self::OPTIONS_DEFAULTS;

    public function __construct(HttpClientInterface $client, StoreInterface $store, array $defaultOptions = [])
    {
        if (!class_exists(HttpClientKernel::class)) {
            throw new \LogicException(sprintf('Using "%s" requires that the HttpKernel component version 4.3 or higher is installed, try running "composer require symfony/http-kernel:^5.4".', __CLASS__));
        }

        $this->client = $client;
        $kernel = new HttpClientKernel($client);
        $this->cache = new HttpCache($kernel, $store, null, $defaultOptions);

        unset($defaultOptions['debug']);
        unset($defaultOptions['default_ttl']);
        unset($defaultOptions['private_headers']);
        unset($defaultOptions['skip_response_headers']);
        unset($defaultOptions['allow_reload']);
        unset($defaultOptions['allow_revalidate']);
        unset($defaultOptions['stale_while_revalidate']);
        unset($defaultOptions['stale_if_error']);
        unset($defaultOptions['trace_level']);
        unset($defaultOptions['trace_header']);

        if ($defaultOptions) {
            [, $this->defaultOptions] = self::prepareRequest(null, null, $defaultOptions, $this->defaultOptions);
        }
    }

    public function request(string $method, string $url, array $options = []): ResponseInterface
    {
        [$url, $options] = $this->prepareRequest($method, $url, $options, $this->defaultOptions, true);
        $url = implode('', $url);

        if (!empty($options['body']) || !empty($options['extra']['no_cache']) || !\in_array($method, ['GET', 'HEAD', 'OPTIONS'])) {
            return $this->client->request($method, $url, $options);
        }

        $request = Request::create($url, $method);
        $request->attributes->set('http_client_options', $options);

        foreach ($options['normalized_headers'] as $name => $values) {
            if ('cookie' !== $name) {
                foreach ($values as $value) {
                    $request->headers->set($name, substr($value, 2 + \strlen($name)), false);
                }

                continue;
            }

            foreach ($values as $cookies) {
                foreach (explode('; ', substr($cookies, \strlen('Cookie: '))) as $cookie) {
                    if ('' !== $cookie) {
                        $cookie = explode('=', $cookie, 2);
                        $request->cookies->set($cookie[0], $cookie[1] ?? '');
                    }
                }
            }
        }

        $response = $this->cache->handle($request);
        $response = new MockResponse($response->getContent(), [
            'http_code' => $response->getStatusCode(),
            'response_headers' => $response->headers->allPreserveCase(),
        ]);

        return MockResponse::fromRequest($method, $url, $options, $response);
    }

    public function stream(ResponseInterface|iterable $responses, ?float $timeout = null): ResponseStreamInterface
    {
        if ($responses instanceof ResponseInterface) {
            $responses = [$responses];
        }

        $mockResponses = [];
        $clientResponses = [];

        foreach ($responses as $response) {
            if ($response instanceof MockResponse) {
                $mockResponses[] = $response;
            } else {
                $clientResponses[] = $response;
            }
        }

        if (!$mockResponses) {
            return $this->client->stream($clientResponses, $timeout);
        }

        if (!$clientResponses) {
            return new ResponseStream(MockResponse::stream($mockResponses, $timeout));
        }

        return new ResponseStream((function () use ($mockResponses, $clientResponses, $timeout) {
            yield from MockResponse::stream($mockResponses, $timeout);
            yield $this->client->stream($clientResponses, $timeout);
        })());
    }

    /**
     * @return void
     */
    public function reset()
    {
        if ($this->client instanceof ResetInterface) {
            $this->client->reset();
        }
    }
}
