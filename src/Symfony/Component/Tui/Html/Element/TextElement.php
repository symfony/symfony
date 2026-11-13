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
use Symfony\Component\Tui\Widget\HorizontalRuleWidget;
use Symfony\Component\Tui\Widget\LinkedTextWidget;
use Symfony\Component\Tui\Widget\RichTextWidget;
use Symfony\Component\Tui\Widget\Text\InlineNode;

/**
 * Text blocks: <p>, <h1>-<h6>, <li>, <pre>, <label>, <hr>...
 *
 * Inline elements are kept, so that CSS styles them; list markers come from
 * list-style-type. A text element containing blocks, like a <label>
 * wrapping its <input>, is built as a container instead.
 *
 * @experimental
 */
final class TextElement implements ElementFactoryInterface
{
    /** Elements styled by the default stylesheet rules of the same class */
    private const CLASSES = ['h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'p'];

    public function create(\Dom\Element $element, HtmlBuilder $builder): AbstractWidget
    {
        $tagName = strtolower($element->localName);
        if ('hr' === $tagName) {
            return (new HorizontalRuleWidget())->addStyleClass('hr');
        }

        // Scrolled sideways, like pre { overflow-x: auto }: a container scrolling the text it holds
        if ($builder->getStyleSheet()->mayScrollSideways($element)) {
            return (new ContainerElement())->create($element, $builder);
        }
        // A flex or grid container, like <li class="d-flex justify-content-between">Inbox <span class="badge">: its
        // text and its elements are items, laid out by a container
        if ($builder->getStyleSheet()->mayLayOutItems($element)) {
            return (new ContainerElement())->create($element, $builder);
        }
        // Blocks inside, or an inline element placed out of the flow, like a positioned badge, or floated: a container
        for ($child = $element->firstElementChild; null !== $child; $child = $child->nextElementSibling) {
            if ($builder->isTextAlternative($child)) {
                continue;
            }
            if (!$builder->isInline($child) || $builder->getStyleSheet()->mayBeOutOfFlow($child) || $builder->getStyleSheet()->mayFloat($child) || $builder->getStyleSheet()->mayBeBlock($child) || $builder->getStyleSheet()->mayBeInlineBox($child) || $builder->isLonePaddedInlineBlock($child)) {
                return (new ContainerElement())->create($element, $builder);
            }
        }

        $nodes = $builder->getInlineNodes($element, true);
        // Like browsers, ignore the newline that ends the text of a block, like the one before </pre>, the last of its
        // text, like that of <pre><code>…\n</code></pre>: it ends its last line, opening no other
        $nodes = self::withoutLastNewline($nodes);

        $truncate = 'tui-text' === $tagName && $element->hasAttribute('truncate');
        // Text with links takes the focus, to follow them with the keyboard
        // In a link laid out as a block, like a link around a card: the link of its text blocks
        $link = 'a' === $tagName ? null : $builder->getEnclosingLink($element);
        $href = 'a' === $tagName ? trim($element->getAttribute('href') ?? '') : '';
        $widget = '' !== $href || null !== $link || RichTextWidget::linksOf($nodes)
            ? new LinkedTextWidget($nodes, $truncate, $builder->getLiveRegion($element))
            : new RichTextWidget($nodes, $truncate, $builder->getLiveRegion($element));
        $widget->setInlineImages($builder->takeInlineImages());

        // A link laid out as a block, like a flex item, or a block in one: the whole block is the hyperlink
        if ('' !== $href) {
            $widget->setLink($href);
        } elseif (null !== $link && $widget instanceof LinkedTextWidget) {
            $builder->linkBlock($widget, $link);
        }

        if (\in_array($tagName, self::CLASSES, true)) {
            $widget->addStyleClass($tagName);
        }

        return $widget;
    }

    /**
     * The nodes without the newline that ends their text, in the last of them or the last of what it holds.
     *
     * @param list<InlineNode> $nodes
     *
     * @return list<InlineNode>
     */
    public static function withoutLastNewline(array $nodes): array
    {
        $last = array_key_last($nodes);
        if (null === $last) {
            return $nodes;
        }
        $node = $nodes[$last];
        // A <br> ends the last line too, like the one ending each line of a block of code of Prism: none after it,
        // whitespace after it collapsing
        $br = $last;
        if ($node->isText() && '' === trim($node->text, " \t\n\r\f") && $last > 0) {
            $br = $last - 1;
        }
        if (!$nodes[$br]->isText() && 'br' === $nodes[$br]->tagName) {
            unset($nodes[$br]);

            return array_values($nodes);
        }
        if ($node->isText()) {
            if (str_ends_with($node->text, "\n")) {
                $nodes[$last] = InlineNode::text(substr($node->text, 0, -1));
            }
        } elseif ([] !== $node->children) {
            $nodes[$last] = InlineNode::element($node->tagName, $node->attributes, self::withoutLastNewline($node->children));
        }

        return $nodes;
    }
}
