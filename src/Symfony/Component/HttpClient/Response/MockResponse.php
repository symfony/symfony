<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpClient\Response;

use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * A test-friendly response.
 *
 * @author Gary PEGEOT <garypegeot@gmail.com>
 */
class MockResponse implements ResponseInterface
{
    use ResponseTrait;

    private const DEFAULT_INFO = [
        'raw_headers' => [],    // An array modelled after the special $http_response_header variable
        'redirect_count' => 0,  // The number of redirects followed while executing the request
        'redirect_url' => null, // The resolved location of redirect responses, null otherwise
        'start_time' => 0.0,    // The time when the request was sent or 0.0 when it's pending
        'http_method' => 'GET', // The HTTP verb of the last request
        'http_code' => 0,       // The last response code or 0 when it is not known yet
        'error' => null,        // The error message when the transfer was aborted, null otherwise
        'data' => null,         // The value of the "data" request option, null if not set
        'url' => '',            // The last effective URL of the request
    ];
    /**
     * @var callable|null
     */
    private $initializer;

    public function __construct(string $content = '', int $code = 200, array $headers = [], array $info = [], ?callable $initializer = null)
    {
        $default = self::DEFAULT_INFO;
        $default['start_time'] = microtime(true);

        $this->content = $content;
        $this->info = \array_merge($default, $info);
        $this->info['http_code'] = $code;
        $this->initializer = $initializer;
        $this->headers = $headers;
    }

    /**
     * {@inheritdoc}
     */
    public function getInfo(string $type = null)
    {
        if ($type) {
            return $this->info[$type] ?? null;
        }

        return $this->info;
    }

    public function getContent(bool $throw = true): string
    {
        if ($this->initializer) {
            ($this->initializer)($this);
            $this->initializer = null;
        }

        if ($throw) {
            $this->checkStatusCode();
        }

        return $this->content;
    }

    /**
     * {@inheritdoc}
     */
    protected function close(): void
    {
    }

    /**
     * {@inheritdoc}
     */
    protected static function schedule(self $response, array &$runningResponses): void
    {
    }

    /**
     * {@inheritdoc}
     */
    protected static function perform(\stdClass $multi, array &$responses): void
    {
    }

    /**
     * {@inheritdoc}
     */
    protected static function select(\stdClass $multi, float $timeout): int
    {
        return 42;
    }
}
