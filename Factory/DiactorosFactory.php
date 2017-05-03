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

use Symfony\Bridge\PsrHttpMessage\HttpMessageFactoryInterface;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Zend\Diactoros\Response as DiactorosResponse;
use Zend\Diactoros\ServerRequest;
use Zend\Diactoros\ServerRequestFactory as DiactorosRequestFactory;
use Zend\Diactoros\Stream as DiactorosStream;
use Zend\Diactoros\UploadedFile as DiactorosUploadedFile;

/**
 * Builds Psr\HttpMessage instances using the Zend Diactoros implementation.
 *
 * @author Kévin Dunglas <dunglas@gmail.com>
 */
class DiactorosFactory implements HttpMessageFactoryInterface
{
    public function __construct()
    {
        if (!class_exists('Zend\Diactoros\ServerRequestFactory')) {
            throw new \RuntimeException('Zend Diactoros must be installed to use the DiactorosFactory.');
        }
    }

    /**
     * {@inheritdoc}
     */
    public function createRequest(Request $symfonyRequest)
    {
        $server = DiactorosRequestFactory::normalizeServer($symfonyRequest->server->all());
        $headers = $symfonyRequest->headers->all();

        if (PHP_VERSION_ID < 50600) {
            $body = new DiactorosStream('php://temp', 'wb+');
            $body->write($symfonyRequest->getContent());
        } else {
            $body = new DiactorosStream($symfonyRequest->getContent(true));
        }

        $request = new ServerRequest(
            $server,
            DiactorosRequestFactory::normalizeFiles($this->getFiles($symfonyRequest->files->all())),
            $symfonyRequest->getSchemeAndHttpHost().$symfonyRequest->getRequestUri(),
            $symfonyRequest->getMethod(),
            $body,
            $headers
        );

        $request = $request
            ->withCookieParams($symfonyRequest->cookies->all())
            ->withQueryParams($symfonyRequest->query->all())
            ->withParsedBody($symfonyRequest->request->all())
            ->withRequestTarget($symfonyRequest->getRequestUri())
        ;

        foreach ($symfonyRequest->attributes->all() as $key => $value) {
            $request = $request->withAttribute($key, $value);
        }

        return $request;
    }

    /**
     * Converts Symfony uploaded files array to the PSR one.
     *
     * @param array $uploadedFiles
     *
     * @return array
     */
    private function getFiles(array $uploadedFiles)
    {
        $files = array();

        foreach ($uploadedFiles as $key => $value) {
            if (null === $value) {
                $files[$key] = new DiactorosUploadedFile(null, 0, UPLOAD_ERR_NO_FILE, null, null);
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
     *
     * @param UploadedFile $symfonyUploadedFile
     *
     * @return UploadedFileInterface
     */
    private function createUploadedFile(UploadedFile $symfonyUploadedFile)
    {
        return new DiactorosUploadedFile(
            $symfonyUploadedFile->getRealPath(),
            $symfonyUploadedFile->getClientSize(),
            $symfonyUploadedFile->getError(),
            $symfonyUploadedFile->getClientOriginalName(),
            $symfonyUploadedFile->getClientMimeType()
        );
    }

    /**
     * {@inheritdoc}
     */
    public function createResponse(Response $symfonyResponse)
    {
        if ($symfonyResponse instanceof BinaryFileResponse) {
            $stream = new DiactorosStream($symfonyResponse->getFile()->getPathname(), 'r');
        } else {
            $stream = new DiactorosStream('php://temp', 'wb+');
            if ($symfonyResponse instanceof StreamedResponse) {
                ob_start(function ($buffer) use ($stream) {
                    $stream->write($buffer);

                    return false;
                });

                $symfonyResponse->sendContent();
                ob_end_clean();
            } else {
                $stream->write($symfonyResponse->getContent());
            }
        }

        $headers = $symfonyResponse->headers->all();

        $cookies = $symfonyResponse->headers->getCookies();
        if (!empty($cookies)) {
            $headers['Set-Cookie'] = array();

            foreach ($cookies as $cookie) {
                $headers['Set-Cookie'][] = $cookie->__toString();
            }
        }

        $response = new DiactorosResponse(
            $stream,
            $symfonyResponse->getStatusCode(),
            $headers
        );

        $protocolVersion = $symfonyResponse->getProtocolVersion();
        if ('1.1' !== $protocolVersion) {
            $response = $response->withProtocolVersion($protocolVersion);
        }

        return $response;
    }
}
