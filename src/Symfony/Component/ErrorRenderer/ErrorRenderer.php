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

use Symfony\Component\ErrorRenderer\ErrorRenderer\ErrorRendererInterface;
use Symfony\Component\ErrorRenderer\Exception\ErrorRendererNotFoundException;
use Symfony\Component\ErrorRenderer\Exception\FlattenException;

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
    private $renderers = [];

    /**
     * @param ErrorRendererInterface[] $renderers
     */
    public function __construct(iterable $renderers)
    {
        foreach ($renderers as $renderer) {
            $this->addRenderer($renderer);
        }
    }

    /**
     * Registers an error renderer that is format specific.
     *
     * By passing an explicit format you can register a renderer for a different format than what
     * ErrorRendererInterface::getFormat() would return in order to register the same renderer for
     * several format aliases.
     */
    public function addRenderer(ErrorRendererInterface $renderer, string $format = null): self
    {
        $this->renderers[$format ?? $renderer::getFormat()] = $renderer;

        return $this;
    }

    /**
     * Renders an Exception and returns the Response content.
     *
     * @param \Throwable|FlattenException $exception A \Throwable or FlattenException instance
     * @param string                      $format    The request format (html, json, xml, etc.)
     *
     * @return string The Response content as a string
     *
     * @throws ErrorRendererNotFoundException if no renderer is found
     */
    public function render($exception, string $format = 'html'): string
    {
        if (!isset($this->renderers[$format])) {
            throw new ErrorRendererNotFoundException(sprintf('No error renderer found for format "%s".', $format));
        }

        if ($exception instanceof \Throwable) {
            $exception = FlattenException::createFromThrowable($exception);
        }

        return $this->renderers[$format]->render($exception);
    }
}
