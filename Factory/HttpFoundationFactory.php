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

use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\UploadedFileInterface;
use Psr\Http\Message\UriInterface;
use Symfony\Bridge\PsrHttpMessage\HttpFoundationFactoryInterface;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * {@inheritdoc}
 *
 * @author Kévin Dunglas <dunglas@gmail.com>
 */
class HttpFoundationFactory implements HttpFoundationFactoryInterface
{
    /**
     * {@inheritdoc}
     */
    public function createRequest(ServerRequestInterface $psrRequest)
    {
        $server = array();
        $uri = $psrRequest->getUri();

        if ($uri instanceof UriInterface) {
            $server['SERVER_NAME'] = $uri->getHost();
            $server['SERVER_PORT'] = $uri->getPort();
            $server['REQUEST_URI'] = $uri->getPath();
            $server['QUERY_STRING'] = $uri->getQuery();
        }

        $server['REQUEST_METHOD'] = $psrRequest->getMethod();

        $server = array_replace($server, $psrRequest->getServerParams());

        $parsedBody = $psrRequest->getParsedBody();
        $parsedBody = is_array($parsedBody) ? $parsedBody : array();

        $request = new Request(
            $psrRequest->getQueryParams(),
            $parsedBody,
            $psrRequest->getAttributes(),
            $psrRequest->getCookieParams(),
            $this->getFiles($psrRequest->getUploadedFiles()),
            $server,
            $psrRequest->getBody()->__toString()
        );
        $request->headers->replace($psrRequest->getHeaders());

        return $request;
    }

    /**
     * Converts to the input array to $_FILES structure.
     *
     * @param array $uploadedFiles
     *
     * @return array
     */
    private function getFiles(array $uploadedFiles)
    {
        $files = array();

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
     *
     * @param UploadedFileInterface $psrUploadedFile
     *
     * @return UploadedFile
     */
    private function createUploadedFile(UploadedFileInterface $psrUploadedFile)
    {
        $temporaryPath = '';
        $clientFileName = '';
        if (UPLOAD_ERR_NO_FILE !== $psrUploadedFile->getError()) {
            $temporaryPath = $this->getTemporaryPath();
            $psrUploadedFile->moveTo($temporaryPath);

            $clientFileName = $psrUploadedFile->getClientFilename();
        }

        if (class_exists('Symfony\Component\HttpFoundation\HeaderUtils')) {
            // Symfony 4.1+
            return new UploadedFile(
                $temporaryPath,
                null === $clientFileName ? '' : $clientFileName,
                $psrUploadedFile->getClientMediaType(),
                $psrUploadedFile->getError(),
                true
            );
        }

        return new UploadedFile(
            $temporaryPath,
            null === $clientFileName ? '' : $clientFileName,
            $psrUploadedFile->getClientMediaType(),
            $psrUploadedFile->getSize(),
            $psrUploadedFile->getError(),
            true
        );
    }

    /**
     * Gets a temporary file path.
     *
     * @return string
     */
    protected function getTemporaryPath()
    {
        return tempnam(sys_get_temp_dir(), uniqid('symfony', true));
    }

    /**
     * {@inheritdoc}
     */
    public function createResponse(ResponseInterface $psrResponse)
    {
        $response = new Response(
            $psrResponse->getBody()->__toString(),
            $psrResponse->getStatusCode(),
            $psrResponse->getHeaders()
        );
        $response->setProtocolVersion($psrResponse->getProtocolVersion());

        foreach ($psrResponse->getHeader('Set-Cookie') as $cookie) {
            $response->headers->setCookie($this->createCookie($cookie));
        }

        return $response;
    }

    /**
     * Creates a Cookie instance from a cookie string.
     *
     * Some snippets have been taken from the Guzzle project: https://github.com/guzzle/guzzle/blob/5.3/src/Cookie/SetCookie.php#L34
     *
     * @param string $cookie
     *
     * @return Cookie
     *
     * @throws \InvalidArgumentException
     */
    private function createCookie($cookie)
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
        }

        if (!isset($cookieName)) {
            throw new \InvalidArgumentException('The value of the Set-Cookie header is malformed.');
        }

        return new Cookie(
            $cookieName,
            $cookieValue,
            isset($cookieExpire) ? $cookieExpire : 0,
            isset($cookiePath) ? $cookiePath : '/',
            isset($cookieDomain) ? $cookieDomain : null,
            isset($cookieSecure),
            isset($cookieHttpOnly)
        );
    }
}
