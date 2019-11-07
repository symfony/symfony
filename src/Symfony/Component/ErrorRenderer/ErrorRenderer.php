<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\ErrorRenderer;

use Symfony\Component\ErrorRenderer\ErrorRenderer\HtmlErrorRenderer;
use Symfony\Component\ErrorRenderer\ErrorRenderer\HtmlErrorRendererInterface;
use Symfony\Component\ErrorRenderer\Exception\FlattenException;
use Symfony\Component\Serializer\Exception\NotEncodableValueException;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * Formats an exception to be used as response content.
 *
 * It delegates to implementations of ErrorRendererInterface depending on the format.
 *
 * @see ErrorRendererInterface
 *
 * @author Yonel Ceruto <yonelceruto@gmail.com>
 */
class ErrorRenderer
{
    private $htmlErrorRenderer;
    private $serializer;

    public function __construct(HtmlErrorRendererInterface $htmlErrorRenderer = null, SerializerInterface $serializer = null)
    {
        $this->htmlErrorRenderer = $htmlErrorRenderer ?? new HtmlErrorRenderer();
        $this->serializer = $serializer;
    }

    /**
     * Renders an Exception and returns the Response content.
     *
     * @param \Throwable|FlattenException $exception A \Throwable or FlattenException instance
     * @param string                      $format    The request format (html, json, xml, etc.)
     *
     * @return string The Response content as a string
     */
    public function render($exception, string $format = 'html'): string
    {
        if ($exception instanceof \Throwable) {
            $exception = FlattenException::createFromThrowable($exception);
        }

        if ('html' === $format || null === $this->serializer) {
            return $this->htmlErrorRenderer->render($exception);
        }

        try {
            $context = isset($exception->getHeaders()['X-Debug']) ? ['debug' => $exception->getHeaders()['X-Debug']] : [];

            return $this->serializer->serialize($exception, $format, $context);
        } catch (NotEncodableValueException $_) {
            return $this->htmlErrorRenderer->render($exception);
        }
    }
}
