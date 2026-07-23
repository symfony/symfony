<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpClient\Internal;

use Symfony\Component\HttpClient\Response\AsyncContext;
use Symfony\Component\HttpClient\Response\AsyncResponse;
use Symfony\Contracts\HttpClient\ChunkInterface;

/**
 * Follows redirections in userland so that decorators can inspect each hop.
 *
 * @author Nicolas Grekas <p@tchwork.com>
 *
 * @internal
 */
trait FollowRedirectsTrait
{
    /**
     * @param string                                                     $url        An already prepared, absolute URL
     * @param string                                                     $host       The host name found in $url
     * @param \Closure(string $host, string $url, array &$options): void $onRedirect Called before each followed redirect
     */
    private function followRedirects(string $method, string $url, string $host, array $options, \Closure $onRedirect): AsyncResponse
    {
        if (0 >= $maxRedirects = $options['max_redirects']) {
            return new AsyncResponse($this->client, $method, $url, $options);
        }

        $options['max_redirects'] = 0;
        $redirectHeaders = [
            'host' => $host,
            'port' => parse_url($url, \PHP_URL_PORT),
            'with_auth' => $options['headers'],
            'no_auth' => $options['headers'],
        ];

        if (isset($options['normalized_headers']['host']) || isset($options['normalized_headers']['authorization']) || isset($options['normalized_headers']['cookie'])) {
            $redirectHeaders['no_auth'] = array_filter($redirectHeaders['no_auth'], static fn ($h) => 0 !== stripos($h, 'Host:') && 0 !== stripos($h, 'Authorization:') && 0 !== stripos($h, 'Cookie:'));
        }

        return new AsyncResponse($this->client, $method, $url, $options, static function (ChunkInterface $chunk, AsyncContext $context) use (&$method, &$options, $maxRedirects, &$redirectHeaders, $onRedirect): \Generator {
            if (null !== $chunk->getError() || $chunk->isTimeout() || !$chunk->isFirst()) {
                yield $chunk;

                return;
            }

            $statusCode = $context->getStatusCode();

            if ($statusCode < 300 || 400 <= $statusCode || null === $url = $context->getInfo('redirect_url')) {
                $context->passthru();

                yield $chunk;

                return;
            }

            $host = parse_url($url, \PHP_URL_HOST);
            $onRedirect($host, $url, $options);

            // Do like curl and browsers: turn POST to GET on 301, 302 and 303
            if (303 === $statusCode || 'POST' === $method && \in_array($statusCode, [301, 302], true)) {
                $method = 'HEAD' === $method ? 'HEAD' : 'GET';
                unset($options['body'], $options['json']);

                if (isset($options['normalized_headers']['content-length']) || isset($options['normalized_headers']['content-type']) || isset($options['normalized_headers']['transfer-encoding'])) {
                    $filterContentHeaders = static fn ($h) => 0 !== stripos($h, 'Content-Length:') && 0 !== stripos($h, 'Content-Type:') && 0 !== stripos($h, 'Transfer-Encoding:');
                    $options['headers'] = array_filter($options['headers'], $filterContentHeaders);
                    $redirectHeaders['no_auth'] = array_filter($redirectHeaders['no_auth'], $filterContentHeaders);
                    $redirectHeaders['with_auth'] = array_filter($redirectHeaders['with_auth'], $filterContentHeaders);
                }
            }

            // Authorization and Cookie headers MUST NOT follow except for the initial host name
            $port = parse_url($url, \PHP_URL_PORT);
            $options['headers'] = $redirectHeaders['host'] === $host && ($redirectHeaders['port'] ?? null) === $port ? $redirectHeaders['with_auth'] : $redirectHeaders['no_auth'];

            static $redirectCount = 0;
            $context->setInfo('redirect_count', ++$redirectCount);

            $context->replaceRequest($method, $url, $options);

            if ($redirectCount >= $maxRedirects) {
                $context->passthru();
            }
        });
    }
}
