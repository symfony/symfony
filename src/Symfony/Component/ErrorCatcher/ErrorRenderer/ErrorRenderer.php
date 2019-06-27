<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\ErrorCatcher\ErrorRenderer;

use Symfony\Component\ErrorCatcher\Exception\ErrorRendererNotFoundException;
use Symfony\Component\ErrorCatcher\Exception\FlattenException;

/**
 * Renders an Exception that represents a Response content.
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
    public function __construct(array $renderers)
    {
        foreach ($renderers as $renderer) {
            if (!$renderer instanceof ErrorRendererInterface) {
                throw new \InvalidArgumentException(sprintf('Error renderer "%s" must implement "%s".', \get_class($renderer), ErrorRendererInterface::class));
            }

            $this->addRenderer($renderer, $renderer::getFormat());
        }
    }

    public function addRenderer(ErrorRendererInterface $renderer, string $format): self
    {
        $this->renderers[$format] = $renderer;

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
