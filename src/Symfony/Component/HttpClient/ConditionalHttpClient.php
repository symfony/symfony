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

use phpDocumentor\Reflection\DocBlock\Tags\Uses;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;
use Symfony\Contracts\HttpClient\ResponseStreamInterface;

/**
 * @author Anthony Martin <anthony.martin@sensiolabs.com>
 *
 * @experimental in 4.3
 */
class ConditionalHttpClient implements HttpClientInterface
{
    private $options;

    private $client;

    /**
     * @param HttpClientInterface $client
     * @param array $options should contain an array with regexp as key to filter the url and an array of options as value to be given for the HttpClient request() function
     */
    public function __construct(HttpClientInterface $client, array $options)
    {
        $this->client = $client;
        $this->options = $options;
    }

    /**
     * {@inheritdoc}
     */
    public function request(string $method, string $url, array $options = []): ResponseInterface
    {
        foreach ($this->options as $regexp => $clientOptions) {
            if (preg_match($regexp, $url)) {
                return $this->client->request($method, $url, array_merge($clientOptions, $options));
            }
        }
        
        return $this->client->request($method, $url, $options);
    }

    /**
     * {@inheritdoc}q
     */
    public function stream($responses, float $timeout = null): ResponseStreamInterface
    {
        return $this->client->stream($responses, $timeout);
    }
}
