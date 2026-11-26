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

use Symfony\Bridge\TuiHtml\Html\ElementFactoryInterface;
use Symfony\Bridge\TuiHtml\Html\HtmlBuilder;
use Symfony\Bridge\TuiHtml\Widget\CollapsibleWidget;
use Symfony\Bridge\TuiHtml\Widget\ContainerWidget;
use Symfony\Component\HtmlRenderer\Css\Selector;
use Symfony\Component\Tui\Event\ExpandEvent;
use Symfony\Component\Tui\Widget\AbstractWidget;

/**
 * <details> and its <summary>.
 *
 * @experimental
 */
final class DetailsElement implements ElementFactoryInterface
{
    /**
     * The accordions of each document: the <details> sharing a name, of which one at most is open, each with the widget
     * built last for it, by the object id of its element, which they hold.
     *
     * @var \WeakMap<\Dom\Document, array<string, array<int, array{\Dom\Element, CollapsibleWidget}>>>
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
        // An exclusive accordion, like <details name>: opening one closes the others, and one added open while another
        // is stays closed
        $document = $element->ownerDocument;
        $open = $element->hasAttribute('open') && !array_any($this->group($document, $name, $element), static fn (CollapsibleWidget $other) => $other->isExpanded());
        $widget = (new CollapsibleWidget($summary, $content, $open, $description))->setSummaryAttributes($summaryAttributes)->setSummaryEnd($summaryEnd);
        // In the place of the one built before for the element, like when HtmlView::setInnerHtml() builds it again
        $groups = $this->groups[$document] ?? [];
        $groups[$name][spl_object_id($element)] = [$element, $widget];
        $this->groups[$document] = $groups;
        $widget->on(ExpandEvent::class, function () use ($widget, $element, $document, $name): void {
            // One built again since, or removed, closes none
            if (($this->groups[$document][$name][spl_object_id($element)][1] ?? null) !== $widget || !$element->isConnected) {
                return;
            }
            foreach ($this->group($document, $name, $element) as $other) {
                $other->collapse();
            }
        });

        return $widget;
    }

    /**
     * The widgets of the other <details> of an accordion in the document, those removed from it left out for good.
     *
     * @return list<CollapsibleWidget>
     */
    private function group(\Dom\Document $document, string $name, \Dom\Element $element): array
    {
        $group = $this->groups[$document][$name] ?? [];
        $others = [];
        foreach ($group as $key => [$other, $widget]) {
            if (!$other->isConnected) {
                unset($group[$key]);
            } elseif ($other !== $element) {
                $others[] = $widget;
            }
        }
        if (isset($this->groups[$document])) {
            $groups = $this->groups[$document];
            $groups[$name] = $group;
            $this->groups[$document] = $groups;
        }

        return $others;
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
