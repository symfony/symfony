<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\TuiHtml\Html;

use Symfony\Bridge\TuiHtml\Css\CssStyleSheet;
use Symfony\Bridge\TuiHtml\Internal\ContainerState;
use Symfony\Bridge\TuiHtml\Widget\CollapsibleWidget;
use Symfony\Bridge\TuiHtml\Widget\ContainerWidget;
use Symfony\Bridge\TuiHtml\Widget\RichTextWidget;
use Symfony\Bridge\TuiHtml\Widget\Text\InlineContentInterface;
use Symfony\Bridge\TuiHtml\Widget\Text\InlineNode;
use Symfony\Component\Tui\Ansi\AnsiUtils;
use Symfony\Component\Tui\Widget\AbstractWidget;
use Symfony\Component\Tui\Widget\ParentInterface;
use Symfony\Component\Tui\Widget\TabsWidget;
use Symfony\Component\Tui\Widget\Util\StringUtils;

/** @internal Experimental presentation projection, not a second document model. */
final class MirrorPresentation
{
    private \WeakMap $texts;
    private array $snapshot;
    private \SplObjectStorage $groups;
    private \SplObjectStorage $owners;
    private \SplObjectStorage $replacements;
    private \SplObjectStorage $inlineImages;
    private \SplObjectStorage $usedTexts;
    private ?\SplObjectStorage $usedStyles = null;

    public function __construct()
    {
        $this->texts = new \WeakMap();
    }

    public function project(array $snapshot, AbstractWidget $root, ?\SplObjectStorage $usedStyles = null): array
    {
        $this->snapshot = $snapshot;
        $this->usedStyles = $usedStyles;
        $this->groups = new \SplObjectStorage();
        $this->owners = new \SplObjectStorage();
        $this->replacements = new \SplObjectStorage();
        $this->inlineImages = new \SplObjectStorage();
        $this->usedTexts = new \SplObjectStorage();
        $node = $snapshot['nodes'][$root] ?? null;
        if (!$node instanceof \Dom\Element) {
            throw new \LogicException('The native root has no prepared semantic mirror.');
        }
        $this->widget($root, $node);

        foreach ($this->texts as $owner => $slots) {
            $this->texts[$owner] = array_intersect_key($slots, $this->usedTexts[$owner] ?? []);
        }

        return ['document' => $snapshot['document'], 'root' => $node, 'groups' => $this->groups, 'owners' => $this->owners, 'replacements' => $this->replacements, 'inline-images' => $this->inlineImages];
    }

    private function widget(AbstractWidget $widget, \Dom\Element $parent, bool $inInlineFlow = false): array
    {
        $node = $this->snapshot['nodes'][$widget] ?? null;
        $anonymous = isset($this->snapshot['anonymous'][$widget]);
        if (!$anonymous && !$node instanceof \Dom\Element) {
            throw new \LogicException('The native widget has no prepared semantic mirror.');
        }
        $owner = $anonymous ? $parent : $node;
        $style = $anonymous ? array_replace($this->snapshot['computed'][$widget] ?? [], null === $node ? [] : (($this->usedStyles[$node] ?? [])[''] ?? [])) : [];
        $inlineFlow = $anonymous && $widget instanceof ContainerWidget && CssStyleSheet::LINE_TAG === ($this->snapshot['descriptions'][$widget][0] ?? null) && ContainerState::of($widget)->isInlineFlow() && \in_array($style['display'] ?? '', ['flex', 'inline-flex'], true);
        if (!$anonymous) {
            $this->owners[$node] = $widget;
        }
        if ($widget instanceof InlineContentInterface) {
            $index = [];
            $collect = static function (array $elements) use (&$collect, &$index): void {
                foreach ($elements as $element) {
                    if (null !== $id = $element->getAttribute('data-tui-inline')) {
                        $index[$id] = $element;
                    }
                    $direct = [];
                    for ($child = $element->firstElementChild; null !== $child; $child = $child->nextElementSibling) {
                        $direct[] = $child;
                    }
                    $collect($direct);
                }
            };
            $collect($this->snapshot['inline'][$widget] ?? []);
            $counter = 0;
            $links = $widget instanceof RichTextWidget ? $widget->getLinks() : [];
            $linkCounter = 0;
            $blockLink = $widget instanceof RichTextWidget && (null !== $widget->getLink() || 'a' === $owner->localName && '' !== trim($owner->getAttribute('href') ?? '')) ? 0 : null;
            $children = $this->inline($widget->getInlineNodes(), $owner, $widget, $index, $counter, '', $links, $linkCounter, $blockLink);
            if (!$anonymous) {
                $this->populate($owner, $children);

                return [$node];
            }
        } elseif ($widget instanceof CollapsibleWidget) {
            if ($widget->isExpanded()) {
                $owner->setAttribute('open', '');
            } else {
                $owner->removeAttribute('open');
            }
            $summary = \Symfony\Component\HtmlRenderer\Html\Details::summary($owner);
            if (null === $summary || $summary->getAttribute('data-tui-part') !== spl_object_id($widget).':summary') {
                throw new \LogicException('The native details summary has no prepared semantic part.');
            }
            $this->owners[$summary] = $widget;
            $this->replacements[$summary] = $widget;
            $text = StringUtils::stripControlBytes(AnsiUtils::stripAnsiCodes(StringUtils::sanitizeUtf8($widget->getSummary())));
            $this->populate($summary, '' === $text ? [] : [$this->text($summary, 'summary', $text)]);
            $children = [$summary, ...$this->widget($widget->getContent(), $owner)];
            $this->populate($owner, $children);
            $this->groups[$owner] = $children;

            return [$node];
        } elseif ($widget instanceof ParentInterface) {
            if ('iframe' === $node?->localName) {
                $this->replacements[$node] = $widget;
                $this->populate($owner, []);

                return [$node];
            }
            $children = [];
            $previous = null;
            foreach ($widget->all() as $child) {
                if (isset($this->snapshot['spaced'][$child]) && $children && (!$inlineFlow || !$this->hasGlueText($previous, false) && !$this->hasGlueText($child, true))) {
                    $children[] = $this->text($owner, 'space:'.spl_object_id($child), ' ');
                }
                array_push($children, ...$this->widget($child, $owner, $inlineFlow));
                $previous = $child;
            }
            if ($widget instanceof TabsWidget) {
                $this->replacements[$node] = $widget;
            }
            if (!$anonymous) {
                $this->populate($owner, $children);
                $this->groups[$owner] = $children;

                return [$node];
            }
        } else {
            if (!$anonymous) {
                $this->replacements[$node] = $widget;
            }

            return [$node];
        }
        if ($inlineFlow) {
            $style['display'] = 'block';
        } elseif ($inInlineFlow && $widget instanceof InlineContentInterface && 'none' !== ($style['display'] ?? 'block')) {
            $style['display'] = 'inline';
        }
        $style['display'] ??= 'block';

        return [['style' => $style, 'children' => $children]];
    }

    private function inline(array $nodes, \Dom\Element $owner, AbstractWidget $widget, array $elements, int &$counter, string $path, array $links, int &$linkCounter, ?int $activeLink): array
    {
        $children = [];
        foreach ($nodes as $offset => $node) {
            if (!$node instanceof InlineNode) {
                throw new \InvalidArgumentException('Inline presentation requires native inline nodes.');
            }
            if ($node->isText()) {
                if ('' === $node->text) {
                    continue;
                }
                $text = $this->text($owner, spl_object_id($widget).':'.$path.$offset, $node->text);
                $this->owners[$text] = $widget;
                $children[] = $text;
                continue;
            }
            $id = spl_object_id($widget).':'.$counter++;
            $element = $elements[$id] ?? null;
            if (!$element instanceof \Dom\Element) {
                throw new \LogicException('The inline element is absent from the prepared mirror.');
            }
            $this->owners[$element] = $widget;
            $link = $activeLink;
            if ('a' === $node->tagName && '' !== trim($node->attributes['href'] ?? '')) {
                $link = $linkCounter++;
            }
            if ($widget instanceof RichTextWidget && \in_array($element->localName, ['img', 'svg'], true) && 1 === \count($node->children) && null !== $tokens = $node->children[0]->text) {
                if (preg_match('/^[\x{100000}-\x{101FFF}]+$/u', $tokens)) {
                    $this->inlineImages[$element] = ['inline-index' => $counter - 1, 'tokens' => $tokens, 'href' => null === $link ? null : ($links[$link] ?? null), 'link-index' => $link];
                    $this->replacements[$element] = $widget;
                    $this->populate($element, []);
                    $this->groups[$element] = [];
                    $children[] = $element;
                    continue;
                }
            }
            $contents = $this->inline($node->children, $element, $widget, $elements, $counter, $path.$offset.'.', $links, $linkCounter, $link);
            $this->populate($element, $contents);
            if (\in_array($element->localName, ['img', 'svg'], true)) {
                $this->groups[$element] = $contents;
                if (null !== $this->usedStyles) {
                    $used = $this->usedStyles[$element] ?? [];
                    $used['']['display'] = 'inline';
                    $this->usedStyles[$element] = $used;
                }
            }
            $children[] = $element;
        }

        return $children;
    }

    private function text(\Dom\Element $owner, string $slot, string $text): \Dom\Text
    {
        $used = $this->usedTexts[$owner] ?? [];
        $used[$slot] = true;
        $this->usedTexts[$owner] = $used;
        $slots = $this->texts[$owner] ?? [];
        $node = $slots[$slot] ??= $owner->ownerDocument->createTextNode($text);
        $node->data = $text;
        $this->texts[$owner] = $slots;

        return $node;
    }

    private function populate(\Dom\Element $owner, array $items): void
    {
        $flat = [];
        $flatten = static function (array $items) use (&$flatten, &$flat): void {
            foreach ($items as $item) {
                if (\is_array($item)) {
                    $flatten($item['children']);
                } else {
                    $flat[] = $item;
                }
            }
        };
        $flatten($items);
        $before = array_values(array_filter(iterator_to_array($owner->childNodes, false), static fn ($node) => $node instanceof \Dom\Element));
        $after = array_values(array_filter($flat, static fn ($node) => $node instanceof \Dom\Element));
        if ($before !== $after) {
            throw new \LogicException('Presentation must preserve the selector mirror element topology.');
        }
        foreach (iterator_to_array($owner->childNodes, false) as $child) {
            if ($child instanceof \Dom\Text) {
                $child->remove();
            }
        }
        foreach ($flat as $child) {
            $owner->appendChild($child);
        }
    }

    private function hasGlueText(?AbstractWidget $widget, bool $start): bool
    {
        if (null === $widget || !$widget instanceof InlineContentInterface || !isset($this->snapshot['anonymous'][$widget])) {
            return false;
        }
        if (\in_array(\Symfony\Component\HtmlRenderer\Css\WhiteSpace::resolve($this->snapshot['computed'][$widget] ?? []), ['pre', 'pre-wrap'], true)) {
            return false;
        }
        $text = implode('', array_map(static fn (InlineNode $node): string => $node->getPlainText(), $widget->getInlineNodes()));

        return (bool) preg_match($start ? '/^[ \t\r\n\f]/' : '/[ \t\r\n\f]$/', $text);
    }
}
