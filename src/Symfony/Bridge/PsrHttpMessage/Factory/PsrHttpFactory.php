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

use Http\Discovery\Psr17Factory as DiscoveryPsr17Factory;
use Nyholm\Psr7\Factory\Psr17Factory as NyholmPsr17Factory;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestFactoryInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\UploadedFileFactoryInterface;
use Psr\Http\Message\UploadedFileInterface;
use Symfony\Bridge\PsrHttpMessage\HttpMessageFactoryInterface;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Builds Psr\HttpMessage instances using a PSR-17 implementation.
 *
 * @author Antonio J. García Lagar <aj@garcialagar.es>
 * @author Aurélien Pillevesse <aurelienpillevesse@hotmail.fr>
 */
class PsrHttpFactory implements HttpMessageFactoryInterface
{
    private readonly ServerRequestFactoryInterface $serverRequestFactory;
    private readonly StreamFactoryInterface $streamFactory;
    private readonly UploadedFileFactoryInterface $uploadedFileFactory;
    private readonly ResponseFactoryInterface $responseFactory;

    public function __construct(
        ?ServerRequestFactoryInterface $serverRequestFactory = null,
        ?StreamFactoryInterface $streamFactory = null,
        ?UploadedFileFactoryInterface $uploadedFileFactory = null,
        ?ResponseFactoryInterface $responseFactory = null,
    ) {
        if (null === $serverRequestFactory || null === $streamFactory || null === $uploadedFileFactory || null === $responseFactory) {
            $psr17Factory = match (true) {
                class_exists(DiscoveryPsr17Factory::class) => new DiscoveryPsr17Factory(),
                class_exists(NyholmPsr17Factory::class) => new NyholmPsr17Factory(),
                default => throw new \LogicException(sprintf('You cannot use the "%s" as no PSR-17 factories have been provided. Try running "composer require php-http/discovery psr/http-factory-implementation:*".', self::class)),
            };

            $serverRequestFactory ??= $psr17Factory;
            $streamFactory ??= $psr17Factory;
            $uploadedFileFactory ??= $psr17Factory;
            $responseFactory ??= $psr17Factory;
        }

        $this->serverRequestFactory = $serverRequestFactory;
        $this->streamFactory = $streamFactory;
        $this->uploadedFileFactory = $uploadedFileFactory;
        $this->responseFactory = $responseFactory;
    }

    public function createRequest(Request $symfonyRequest): ServerRequestInterface
    {
        $uri = $symfonyRequest->server->get('QUERY_STRING', '');
        $uri = $symfonyRequest->getSchemeAndHttpHost().$symfonyRequest->getBaseUrl().$symfonyRequest->getPathInfo().('' !== $uri ? '?'.$uri : '');

        $request = $this->serverRequestFactory->createServerRequest(
            $symfonyRequest->getMethod(),
            $uri,
            $symfonyRequest->server->all()
        );

        foreach ($symfonyRequest->headers->all() as $name => $value) {
            try {
                $request = $request->withHeader($name, $value);
            } catch (\InvalidArgumentException $e) {
                // ignore invalid header
            }
        }

        $body = $this->streamFactory->createStreamFromResource($symfonyRequest->getContent(true));
        $format = $symfonyRequest->getContentTypeFormat();

        if ('json' === $format) {
            $parsedBody = json_decode($symfonyRequest->getContent(), true, 512, \JSON_BIGINT_AS_STRING);

            if (!\is_array($parsedBody)) {
                $parsedBody = null;
            }
        } else {
            $parsedBody = $symfonyRequest->request->all();
        }

        $request = $request
            ->withBody($body)
            ->withUploadedFiles($this->getFiles($symfonyRequest->files->all()))
            ->withCookieParams($symfonyRequest->cookies->all())
            ->withQueryParams($symfonyRequest->query->all())
            ->withParsedBody($parsedBody)
        ;

        foreach ($symfonyRequest->attributes->all() as $key => $value) {
            $request = $request->withAttribute($key, $value);
        }

        return $request;
    }

    /**
     * Converts Symfony uploaded files array to the PSR one.
     */
    private function getFiles(array $uploadedFiles): array
    {
        $files = [];

        foreach ($uploadedFiles as $key => $value) {
            if (null === $value) {
                $files[$key] = $this->uploadedFileFactory->createUploadedFile($this->streamFactory->createStream(), 0, \UPLOAD_ERR_NO_FILE);
                continue;
            }
            if ($value instanceof UploadedFile) {
                $files[$key] = $this->createUploadedFile($value);
            } else {
                $files[$key] = $this->getFiles($value);
            }
        }

        return $files;
    }

    /**
     * Creates a PSR-7 UploadedFile instance from a Symfony one.
     */
    private function createUploadedFile(UploadedFile $symfonyUploadedFile): UploadedFileInterface
    {
        return $this->uploadedFileFactory->createUploadedFile(
            $this->streamFactory->createStreamFromFile(
                $symfonyUploadedFile->getRealPath()
            ),
            (int) $symfonyUploadedFile->getSize(),
            $symfonyUploadedFile->getError(),
            $symfonyUploadedFile->getClientOriginalName(),
            $symfonyUploadedFile->getClientMimeType()
        );
    }

    public function createResponse(Response $symfonyResponse): ResponseInterface
    {
        $response = $this->responseFactory->createResponse($symfonyResponse->getStatusCode(), Response::$statusTexts[$symfonyResponse->getStatusCode()] ?? '');

        if ($symfonyResponse instanceof BinaryFileResponse && !$symfonyResponse->headers->has('Content-Range')) {
            $stream = $this->streamFactory->createStreamFromFile(
                $symfonyResponse->getFile()->getPathname()
            );
        } else {
            $stream = $this->streamFactory->createStreamFromFile('php://temp', 'wb+');
            if ($symfonyResponse instanceof StreamedResponse || $symfonyResponse instanceof BinaryFileResponse) {
                ob_start(function ($buffer) use ($stream) {
                    $stream->write($buffer);

                    return '';
                }, 1);

                $symfonyResponse->sendContent();
                ob_end_clean();
            } else {
                $stream->write($symfonyResponse->getContent());
            }
        }

        $response = $response->withBody($stream);

        $headers = $symfonyResponse->headers->all();
        $cookies = $symfonyResponse->headers->getCookies();
        if (!empty($cookies)) {
            $headers['Set-Cookie'] = [];

            foreach ($cookies as $cookie) {
                $headers['Set-Cookie'][] = $cookie->__toString();
            }
        }

        foreach ($headers as $name => $value) {
            try {
                $response = $response->withHeader($name, $value);
            } catch (\InvalidArgumentException $e) {
                // ignore invalid header
            }
        }

        $protocolVersion = $symfonyResponse->getProtocolVersion();
        $response = $response->withProtocolVersion($protocolVersion);

        return $response;
    }
}
