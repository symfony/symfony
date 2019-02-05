<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Contracts\HttpClient;

use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\Tests\HttpClient\ApiClientTest;

/**
 * Provides flexible methods for interacting with HTTP APIs.
 *
 * When responses need to be streamed, an HttpClientInterface implementation should be used instead.
 *
 * Implementations SHOULD send a JSON-compatible "accept" header by default, typically "application/json".
 *
 * @see HttpClientInterface for a description of options and how they MUST work
 * @see ApiClientTestCase   for a reference test suite
 *
 * @author Nicolas Grekas <p@tchwork.com>
 */
interface ApiClientInterface
{
    const OPTIONS_DEFAULTS = HttpClientInterface::OPTIONS_DEFAULTS;

    /**
     * Requests an HTTP resource using the GET verb.
     *
     * @throws TransportExceptionInterface When an unsupported option is passed
     */
    public function get(string $url, array $options = []): ResponseInterface;

    /**
     * Requests an HTTP resource using the HEAD verb.
     *
     * @throws TransportExceptionInterface When an unsupported option is passed
     */
    public function head(string $url, array $options = []): ResponseInterface;

    /**
     * Requests an HTTP resource using the POST verb.
     *
     * @throws TransportExceptionInterface When an unsupported option is passed
     */
    public function post(string $url, array $options = []): ResponseInterface;

    /**
     * Requests an HTTP resource using the PUT verb.
     *
     * @throws TransportExceptionInterface When an unsupported option is passed
     */
    public function put(string $url, array $options = []): ResponseInterface;

    /**
     * Requests an HTTP resource using the PATCH verb.
     *
     * @throws TransportExceptionInterface When an unsupported option is passed
     */
    public function patch(string $url, array $options = []): ResponseInterface;

    /**
     * Requests an HTTP resource using the DELETE verb.
     *
     * @throws TransportExceptionInterface When an unsupported option is passed
     */
    public function delete(string $url, array $options = []): ResponseInterface;

    /**
     * Requests an HTTP resource using the OPTIONS verb.
     *
     * @throws TransportExceptionInterface When an unsupported option is passed
     */
    public function options(string $url, array $options = []): ResponseInterface;

    /**
     * Yields responses as they complete.
     *
     * @param ResponseInterface|ResponseInterface[]|iterable $responses One or more responses created by the current client
     * @param float|null                                     $timeout   The inactivity timeout before exiting the iterator;
     *                                                                  unless any of their public methods have been called,
     *                                                                  the destructor of timed out responses MUST throw a
     *                                                                  TransportExceptionInterface to ensure none of them
     *                                                                  was been left unhandled
     */
    public function complete($responses, float $timeout = null): ResponseIteratorInterface;
}
