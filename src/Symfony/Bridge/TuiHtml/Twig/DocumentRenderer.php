<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\TuiHtml\Twig;

use Symfony\Component\HtmlRenderer\HtmlRenderer;
use Symfony\Component\HtmlRenderer\RenderingContext;
use Symfony\Component\HtmlRenderer\RenderSession;
use Twig\Environment;

/**
 * Evaluates Twig templates before creating independently rendered document sessions.
 *
 * @experimental
 */
final class DocumentRenderer
{
    public function __construct(private readonly Environment $twig, private readonly HtmlRenderer $renderer = new HtmlRenderer())
    {
    }

    /** @param array<string, mixed> $context */
    public function render(string $name, RenderingContext $renderingContext, array $context = []): RenderSession
    {
        return $this->renderer->createSession($this->twig->render($name, $context), $renderingContext);
    }

    /** @param array<string, mixed> $context */
    public function renderBlock(string $name, string $block, RenderingContext $renderingContext, array $context = []): RenderSession
    {
        return $this->renderer->createSession($this->twig->load($name)->renderBlock($block, $context), $renderingContext);
    }
}
