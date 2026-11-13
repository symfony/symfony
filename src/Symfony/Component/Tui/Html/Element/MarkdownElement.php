<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Tui\Html\Element;

use Symfony\Component\Tui\Html\ElementFactoryInterface;
use Symfony\Component\Tui\Html\HtmlBuilder;
use Symfony\Component\Tui\Widget\AbstractWidget;
use Symfony\Component\Tui\Widget\MarkdownWidget;

/**
 * <tui-markdown>: renders its text content as Markdown.
 *
 * @experimental
 */
final class MarkdownElement implements ElementFactoryInterface
{
    public function create(\Dom\Element $element, HtmlBuilder $builder): AbstractWidget
    {
        return new MarkdownWidget(self::dedent($element->textContent));
    }

    /**
     * Removes the indentation templates add to the whole block.
     */
    private static function dedent(string $text): string
    {
        $lines = explode("\n", trim($text, "\n"));
        $indent = null;
        foreach ($lines as $line) {
            if ('' !== trim($line)) {
                $indent = min($indent ?? \PHP_INT_MAX, \strlen($line) - \strlen(ltrim($line)));
            }
        }

        return implode("\n", array_map(static fn (string $line) => substr($line, $indent ?? 0), $lines));
    }
}
