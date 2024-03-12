<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Twig\ErrorRenderer;

use Symfony\Component\ErrorHandler\ErrorRenderer\ErrorRendererInterface;
use Symfony\Component\ErrorHandler\ErrorRenderer\HtmlErrorRenderer;
use Symfony\Component\ErrorHandler\Exception\FlattenException;
use Symfony\Component\HttpFoundation\RequestStack;
use Twig\Environment;

/**
 * Provides the ability to render custom Twig-based HTML error pages
 * in non-debug mode, otherwise falls back to HtmlErrorRenderer.
 *
 * @author Yonel Ceruto <yonelceruto@gmail.com>
 */
class TwigErrorRenderer implements ErrorRendererInterface
{
    private HtmlErrorRenderer $fallbackErrorRenderer;
    private \Closure|bool $debug;

    /**
     * @param bool|callable $debug The debugging mode as a boolean or a callable that should return it
     */
    public function __construct(
        private Environment $twig,
        ?HtmlErrorRenderer $fallbackErrorRenderer = null,
        bool|callable $debug = false,
    ) {
        $this->fallbackErrorRenderer = $fallbackErrorRenderer ?? new HtmlErrorRenderer();
        $this->debug = \is_bool($debug) ? $debug : $debug(...);
    }

    public function render(\Throwable $exception): FlattenException
    {
        $flattenException = FlattenException::createFromThrowable($exception);
        $debug = \is_bool($this->debug) ? $this->debug : ($this->debug)($flattenException);

        if ($debug || !$template = $this->findTemplate($flattenException->getStatusCode())) {
            return $this->fallbackErrorRenderer->render($exception);
        }

        return $flattenException->setAsString($this->twig->render($template, [
            'exception' => $flattenException,
            'status_code' => $flattenException->getStatusCode(),
            'status_text' => $flattenException->getStatusText(),
        ]));
    }

    public static function isDebug(RequestStack $requestStack, bool $debug): \Closure
    {
        return static function () use ($requestStack, $debug): bool {
            if (!$request = $requestStack->getCurrentRequest()) {
                return $debug;
            }

            return $debug && $request->attributes->getBoolean('showException', true);
        };
    }

    private function findTemplate(int $statusCode): ?string
    {
        $template = sprintf('@Twig/Exception/error%s.html.twig', $statusCode);
        if ($this->twig->getLoader()->exists($template)) {
            return $template;
        }

        $template = '@Twig/Exception/error.html.twig';
        if ($this->twig->getLoader()->exists($template)) {
            return $template;
        }

        return null;
    }
}
