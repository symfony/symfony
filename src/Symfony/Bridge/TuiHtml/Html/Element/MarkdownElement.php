<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\TuiHtml\Html\Element;

use League\CommonMark\Parser\MarkdownParser;
use Symfony\Bridge\TuiHtml\Html\ElementFactoryInterface;
use Symfony\Bridge\TuiHtml\Html\HtmlBuilder;
use Symfony\Bridge\TuiHtml\Widget\TextWidget;
use Symfony\Component\Tui\Widget\AbstractWidget;
use Symfony\Component\Tui\Widget\MarkdownWidget;
use Tempest\Highlight\Highlighter;

/**
 * <tui-markdown>: renders its text content as Markdown, with league/commonmark
 * and tempest/highlight, or as it is written without them.
 *
 * @experimental
 */
final class MarkdownElement implements ElementFactoryInterface
{
    public function create(\Dom\Element $element, HtmlBuilder $builder): AbstractWidget
    {
        $text = self::dedent($element->textContent);
        if (!class_exists(MarkdownParser::class) || !class_exists(Highlighter::class)) {
            $builder->report('markdown', '<tui-markdown> is drawn as written: install league/commonmark and tempest/highlight to render Markdown.');

            return new TextWidget($text);
        }

        return new MarkdownWidget($text);
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
