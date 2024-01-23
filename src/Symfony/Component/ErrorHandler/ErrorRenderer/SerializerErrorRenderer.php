<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\ErrorHandler\ErrorRenderer;

use Symfony\Component\ErrorHandler\Exception\FlattenException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Serializer\Exception\NotEncodableValueException;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * Formats an exception using Serializer for rendering.
 *
 * @author Nicolas Grekas <p@tchwork.com>
 */
class SerializerErrorRenderer implements ErrorRendererInterface
{
    private $serializer;
    private $format;
    private $fallbackErrorRenderer;
    private $debug;

    /**
     * @param string|callable(FlattenException) $format The format as a string or a callable that should return it
     *                                                  formats not supported by Request::getMimeTypes() should be given as mime types
     * @param bool|callable                     $debug  The debugging mode as a boolean or a callable that should return it
     */
    public function __construct(SerializerInterface $serializer, $format, ?ErrorRendererInterface $fallbackErrorRenderer = null, $debug = false)
    {
        if (!\is_string($format) && !\is_callable($format)) {
            throw new \TypeError(sprintf('Argument 2 passed to "%s()" must be a string or a callable, "%s" given.', __METHOD__, \gettype($format)));
        }

        if (!\is_bool($debug) && !\is_callable($debug)) {
            throw new \TypeError(sprintf('Argument 4 passed to "%s()" must be a boolean or a callable, "%s" given.', __METHOD__, \gettype($debug)));
        }

        $this->serializer = $serializer;
        $this->format = $format;
        $this->fallbackErrorRenderer = $fallbackErrorRenderer ?? new HtmlErrorRenderer();
        $this->debug = $debug;
    }

    /**
     * {@inheritdoc}
     */
    public function render(\Throwable $exception): FlattenException
    {
        $headers = ['Vary' => 'Accept'];
        $debug = \is_bool($this->debug) ? $this->debug : ($this->debug)($exception);
        if ($debug) {
            $headers['X-Debug-Exception'] = rawurlencode($exception->getMessage());
            $headers['X-Debug-Exception-File'] = rawurlencode($exception->getFile()).':'.$exception->getLine();
        }

        $flattenException = FlattenException::createFromThrowable($exception, null, $headers);

        try {
            $format = \is_string($this->format) ? $this->format : ($this->format)($flattenException);
            $headers['Content-Type'] = Request::getMimeTypes($format)[0] ?? $format;

            $flattenException->setAsString($this->serializer->serialize($flattenException, $format, [
                'exception' => $exception,
                'debug' => $debug,
            ]));
        } catch (NotEncodableValueException $e) {
            $flattenException = $this->fallbackErrorRenderer->render($exception);
        }

        return $flattenException->setHeaders($flattenException->getHeaders() + $headers);
    }

    public static function getPreferredFormat(RequestStack $requestStack): \Closure
    {
        return static function () use ($requestStack) {
            if (!$request = $requestStack->getCurrentRequest()) {
                throw new NotEncodableValueException();
            }

            return $request->getPreferredFormat();
        };
    }
}
