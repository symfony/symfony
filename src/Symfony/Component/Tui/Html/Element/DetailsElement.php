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

use Symfony\Component\Tui\Css\Selector;
use Symfony\Component\Tui\Event\ExpandEvent;
use Symfony\Component\Tui\Html\ElementFactoryInterface;
use Symfony\Component\Tui\Html\HtmlBuilder;
use Symfony\Component\Tui\Widget\AbstractWidget;
use Symfony\Component\Tui\Widget\CollapsibleWidget;
use Symfony\Component\Tui\Widget\ContainerWidget;

/**
 * <details> and its <summary>.
 *
 * @experimental
 */
final class DetailsElement implements ElementFactoryInterface
{
    /**
     * The open accordions of each document: the <details> sharing a name, of which one at most is open.
     *
     * @var \WeakMap<\Dom\Document, array<string, list<CollapsibleWidget>>>
     */
    private \WeakMap $groups;

    public function __construct()
    {
        $this->groups = new \WeakMap();
    }

    public function create(\Dom\Element $element, HtmlBuilder $builder): AbstractWidget
    {
        $summary = 'Details';
        $summaryEnd = '';
        $summaryAttributes = [];
        $content = new ContainerWidget();
        // What ::details-content selects
        $builder->getStyleSheet()->describe($content, 'div', [Selector::DETAILS_CONTENT_ATTRIBUTE => '']);

        foreach ($element->childNodes as $child) {
            if ($child instanceof \Dom\Element && 'summary' === strtolower($child->localName)) {
                [$summary, $summaryEnd] = self::summaryText($child, $builder);
                $summaryAttributes = $builder->attributesOf($child);
                continue;
            }
            if ($child instanceof \Dom\Element && null !== $widget = $builder->buildElement($child)) {
                $content->add($widget);
            }
        }

        $description = null;
        if (null !== $id = $element->getAttribute('aria-describedby')) {
            $described = $element->ownerDocument->getElementById($id);
            $description = null !== $described ? trim($builder->getText($described)) : null;
        }

        $name = $element->getAttribute('name');
        if (null === $name || '' === $name) {
            return (new CollapsibleWidget($summary, $content, $element->hasAttribute('open'), $description))->setSummaryAttributes($summaryAttributes)->setSummaryEnd($summaryEnd);
        }

        // An exclusive accordion, like <details name>: opening one closes the others, and only the first opened one stays open
        $document = $element->ownerDocument;
        $groups = $this->groups[$document] ?? [];
        $group = $groups[$name] ?? [];
        $open = $element->hasAttribute('open') && !array_any($group, static fn (CollapsibleWidget $other) => $other->isExpanded());
        $widget = (new CollapsibleWidget($summary, $content, $open, $description))->setSummaryAttributes($summaryAttributes)->setSummaryEnd($summaryEnd);
        $group[] = $widget;
        $groups[$name] = $group;
        $this->groups[$document] = $groups;

        $widget->on(ExpandEvent::class, function () use ($widget, $document, $name): void {
            foreach ($this->groups[$document][$name] ?? [] as $other) {
                if ($other !== $widget) {
                    $other->collapse();
                }
            }
        });

        return $widget;
    }

    /**
     * The text of a summary, and that of what floats at its right, drawn at the end of its line, like the version and
     * source link of the methods of rustdoc.
     *
     * @return array{string, string}
     */
    private static function summaryText(\Dom\Element $summary, HtmlBuilder $builder): array
    {
        $copy = clone $summary;
        $originals = iterator_to_array($summary->querySelectorAll('*'), false);
        $copies = iterator_to_array($copy->querySelectorAll('*'), false);
        $end = [];
        foreach ($originals as $i => $original) {
            if (!isset($copies[$i]) || null === $copies[$i]->parentNode || !$builder->getStyleSheet()->mayFloatRight($original)) {
                continue;
            }
            $end[] = trim($builder->getText($original));
            $copies[$i]->remove();
        }

        return [trim($builder->getText($copy)), implode(' ', array_filter($end, static fn (string $text) => '' !== $text))];
    }
}
