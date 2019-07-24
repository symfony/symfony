<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\TwigBundle\ErrorRenderer;

use Symfony\Component\ErrorRenderer\ErrorRenderer\ErrorRendererInterface;
use Symfony\Component\ErrorRenderer\ErrorRenderer\HtmlErrorRenderer;
use Symfony\Component\ErrorRenderer\Exception\FlattenException;
use Twig\Environment;

/**
 * Provides the ability to render custom Twig-based HTML error pages
 * in non-debug mode, otherwise falls back to HtmlErrorRenderer.
 *
 * @author Yonel Ceruto <yonelceruto@gmail.com>
 */
class TwigHtmlErrorRenderer implements ErrorRendererInterface
{
    private $twig;
    private $htmlErrorRenderer;
    private $debug;

    public function __construct(Environment $twig, HtmlErrorRenderer $htmlErrorRenderer, bool $debug = false)
    {
        $this->twig = $twig;
        $this->htmlErrorRenderer = $htmlErrorRenderer;
        $this->debug = $debug;
    }

    /**
     * {@inheritdoc}
     */
    public static function getFormat(): string
    {
        return 'html';
    }

    /**
     * {@inheritdoc}
     */
    public function render(FlattenException $exception): string
    {
        $debug = $this->debug && ($exception->getHeaders()['X-Debug'] ?? true);

        if ($debug) {
            return $this->htmlErrorRenderer->render($exception);
        }

        $template = $this->findTemplate($exception->getStatusCode());

        if (null === $template) {
            return $this->htmlErrorRenderer->render($exception);
        }

        return $this->twig->render($template, [
            'exception' => $exception,
            'status_code' => $exception->getStatusCode(),
            'status_text' => $exception->getTitle(),
        ]);
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
