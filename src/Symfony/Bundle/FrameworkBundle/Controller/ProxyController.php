<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Controller;

use Symfony\Component\HttpClient\Exception\InvalidArgumentException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Proxies a request to a remote URL.
 *
 * @author Jérôme Tamarelle <jerome@tamarelle.net>
 *
 * @final
 */
class ProxyController
{
    /** @var HttpClientInterface */
    private $httpClient;

    public function __construct(HttpClientInterface $httpClient)
    {
        $this->httpClient = $httpClient;
    }

    /**
     * Creates a response by fetching the content from a remote URL.
     *
     * @param Request $request         The request instance
     * @param string  $url             The URL to fetch
     * @param string  $method          The HTTP method to use
     * @param array   $options         Options passed to the HttpClient
     * @param array   $responseHeaders HTTP headers added to the response
     *
     * @throws HttpException In case the route name is empty
     */
    public function __invoke(Request $request, string $url, string $method = 'GET', $options = [], $responseHeaders = []): Response
    {
        try {
            $remoteResponse = $this->httpClient->request($method, $url, $options);
        } catch (InvalidArgumentException $e) {
            throw new \InvalidArgumentException(sprintf('Invalid proxy configuration for route "%s"', $request->attributes->get('_route')), 0, $e);
        }

        $response = new Response();
        $response->setContent($remoteResponse->getContent());
        $response->setStatusCode($remoteResponse->getStatusCode());
        $response->headers->add($responseHeaders);

        return $response;
    }
}
