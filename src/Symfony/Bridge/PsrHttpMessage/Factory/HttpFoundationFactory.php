<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\PsrHttpMessage\Factory;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UploadedFileInterface;
use Psr\Http\Message\UriInterface;
use Symfony\Bridge\PsrHttpMessage\HttpFoundationFactoryInterface;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * @author Kévin Dunglas <dunglas@gmail.com>
 */
class HttpFoundationFactory implements HttpFoundationFactoryInterface
{
    /**
     * @param int $responseBufferMaxLength The maximum output buffering size for each iteration when sending the response
     */
    public function __construct(
        private readonly int $responseBufferMaxLength = 16372,
    ) {
    }

    public function createRequest(ServerRequestInterface $psrRequest, bool $streamed = false): Request
    {
        $server = [];
        $uri = $psrRequest->getUri();

        if ($uri instanceof UriInterface) {
            $server['SERVER_NAME'] = $uri->getHost();
            $server['SERVER_PORT'] = $uri->getPort() ?: ('https' === $uri->getScheme() ? 443 : 80);
            $server['REQUEST_URI'] = $uri->getPath();
            $server['QUERY_STRING'] = $uri->getQuery();

            if ('' !== $server['QUERY_STRING']) {
                $server['REQUEST_URI'] .= '?'.$server['QUERY_STRING'];
            }

            if ('https' === $uri->getScheme()) {
                $server['HTTPS'] = 'on';
            }
        }

        $server['REQUEST_METHOD'] = $psrRequest->getMethod();

        $server = array_replace($psrRequest->getServerParams(), $server);

        $parsedBody = $psrRequest->getParsedBody();
        $parsedBody = \is_array($parsedBody) ? $parsedBody : [];

        $request = new Request(
            $psrRequest->getQueryParams(),
            $parsedBody,
            $psrRequest->getAttributes(),
            $psrRequest->getCookieParams(),
            $this->getFiles($psrRequest->getUploadedFiles()),
            $server,
            $streamed ? $psrRequest->getBody()->detach() : $psrRequest->getBody()->__toString()
        );
        $request->headers->add($psrRequest->getHeaders());

        return $request;
    }

    /**
     * Converts to the input array to $_FILES structure.
     */
    private function getFiles(array $uploadedFiles): array
    {
        $files = [];

        foreach ($uploadedFiles as $key => $value) {
            if ($value instanceof UploadedFileInterface) {
                $files[$key] = $this->createUploadedFile($value);
            } else {
                $files[$key] = $this->getFiles($value);
            }
        }

        return $files;
    }

    /**
     * Creates Symfony UploadedFile instance from PSR-7 ones.
     */
    private function createUploadedFile(UploadedFileInterface $psrUploadedFile): UploadedFile
    {
        return new UploadedFile($psrUploadedFile, function () { return $this->getTemporaryPath(); });
    }

    /**
     * Gets a temporary file path.
     */
    protected function getTemporaryPath(): string
    {
        return tempnam(sys_get_temp_dir(), uniqid('symfony', true));
    }

    public function createResponse(ResponseInterface $psrResponse, bool $streamed = false): Response
    {
        $cookies = $psrResponse->getHeader('Set-Cookie');
        $psrResponse = $psrResponse->withoutHeader('Set-Cookie');

        if ($streamed) {
            $response = new StreamedResponse(
                $this->createStreamedResponseCallback($psrResponse->getBody()),
                $psrResponse->getStatusCode(),
                $psrResponse->getHeaders()
            );
        } else {
            $response = new Response(
                $psrResponse->getBody()->__toString(),
                $psrResponse->getStatusCode(),
                $psrResponse->getHeaders()
            );
        }

        $response->setProtocolVersion($psrResponse->getProtocolVersion());

        foreach ($cookies as $cookie) {
            $response->headers->setCookie($this->createCookie($cookie));
        }

        return $response;
    }

    /**
     * Creates a Cookie instance from a cookie string.
     *
     * Some snippets have been taken from the Guzzle project: https://github.com/guzzle/guzzle/blob/5.3/src/Cookie/SetCookie.php#L34
     *
     * @throws \InvalidArgumentException
     */
    private function createCookie(string $cookie): Cookie
    {
        foreach (explode(';', $cookie) as $part) {
            $part = trim($part);

            $data = explode('=', $part, 2);
            $name = $data[0];
            $value = isset($data[1]) ? trim($data[1], " \n\r\t\0\x0B\"") : null;

            if (!isset($cookieName)) {
                $cookieName = $name;
                $cookieValue = $value;

                continue;
            }

            if ('expires' === strtolower($name) && null !== $value) {
                $cookieExpire = new \DateTime($value);

                continue;
            }

            if ('path' === strtolower($name) && null !== $value) {
                $cookiePath = $value;

                continue;
            }

            if ('domain' === strtolower($name) && null !== $value) {
                $cookieDomain = $value;

                continue;
            }

            if ('secure' === strtolower($name)) {
                $cookieSecure = true;

                continue;
            }

            if ('httponly' === strtolower($name)) {
                $cookieHttpOnly = true;

                continue;
            }

            if ('samesite' === strtolower($name) && null !== $value) {
                $samesite = $value;

                continue;
            }
        }

        if (!isset($cookieName)) {
            throw new \InvalidArgumentException('The value of the Set-Cookie header is malformed.');
        }

        return new Cookie(
            $cookieName,
            $cookieValue,
            $cookieExpire ?? 0,
            $cookiePath ?? '/',
            $cookieDomain ?? null,
            isset($cookieSecure),
            isset($cookieHttpOnly),
            true,
            $samesite ?? null
        );
    }

    private function createStreamedResponseCallback(StreamInterface $body): callable
    {
        return function () use ($body) {
            if ($body->isSeekable()) {
                $body->rewind();
            }

            if (!$body->isReadable()) {
                echo $body;

                return;
            }

            while (!$body->eof()) {
                echo $body->read($this->responseBufferMaxLength);
            }
        };
    }
}
