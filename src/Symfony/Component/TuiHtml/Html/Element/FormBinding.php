<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\TuiHtml\Html\Element;

use Symfony\Component\Tui\Widget\AbstractWidget;
use Symfony\Component\TuiHtml\Widget\DropdownWidget;
use Symfony\Component\TuiHtml\Widget\FormWidget;

/**
 * The controls of a <form>, added as they are built: those it holds, and those of its form attribute, before or after
 * it in the document.
 *
 * @internal
 */
final class FormBinding
{
    /** @var list<array{AbstractWidget, \Dom\Element}> */
    private array $controls = [];
    /** How many of the controls, the first ones, are known to be in the order of the document, see sort() */
    private int $sorted = 0;
    /** @var list<array{AbstractWidget, \Dom\Element}> The submit buttons among them, see sort() */
    private array $submits = [];
    /** How many of the submit buttons are known to be in the order of the document */
    private int $submitsSorted = 0;
    /** The default button, see sort() */
    private ?AbstractWidget $default = null;
    /** @var \WeakMap<AbstractWidget, list<\Closure(): void>> How each control is restored */
    private \WeakMap $restores;
    /** @var \WeakMap<AbstractWidget, list<array{class-string, \Closure}>> The listeners wiring each control to the form */
    private \WeakMap $listeners;
    /** @var \WeakMap<AbstractWidget, self>|null The form each control is bound to: one at most */
    private static ?\WeakMap $owners = null;

    public function __construct(private readonly FormWidget $form, private readonly \Dom\Element $element)
    {
        $this->restores = new \WeakMap();
        $this->listeners = new \WeakMap();
    }

    /**
     * The form a control is bound to.
     */
    public static function of(AbstractWidget $widget): ?self
    {
        return self::$owners[$widget instanceof DropdownWidget ? $widget->getList() : $widget] ?? null;
    }

    public function getForm(): FormWidget
    {
        return $this->form;
    }

    /**
     * Whether a control is one of the form's.
     */
    public function has(AbstractWidget $widget): bool
    {
        $widget = $widget instanceof DropdownWidget ? $widget->getList() : $widget;

        return isset($this->restores[$widget]);
    }

    /**
     * How a reset restores a control: to its state now, see FormElement::snapshot().
     *
     * @return list<\Closure(): void>
     */
    public static function defaults(AbstractWidget $widget, \Dom\Element $control): array
    {
        return FormElement::snapshot([[$widget instanceof DropdownWidget ? $widget->getList() : $widget, $control]]);
    }

    /**
     * @param list<\Closure(): void>|null $restores How a reset restores it, see defaults(): its state now when null
     */
    public function add(AbstractWidget $widget, \Dom\Element $control, ?array $restores = null): void
    {
        $restores ??= self::defaults($widget, $control);
        // A dropdown's value is that of its list
        $widget = $widget instanceof DropdownWidget ? $widget->getList() : $widget;
        $this->controls[] = [$widget, $control];
        $this->restores[$widget] = $restores;
        self::$owners ??= new \WeakMap();
        self::$owners[$widget] = $this;
        // The default button, the one implicit submission presses, matches :default: the first submit button
        if (FormElement::isSubmitButton($control)) {
            $this->submits[] = [$widget, $control];
            if (null === $this->default) {
                $this->default = $widget;
                $widget->setState('default');
            }
        }
        $this->listeners[$widget] = FormElement::wire($this->form, $this->element, $widget, $control, fn (): array => $this->controls, $this->initial(...));
    }

    /**
     * How to restore the controls, in their order.
     *
     * @return list<\Closure(): void>
     */
    private function initial(): array
    {
        $initial = [];
        foreach ($this->controls as [$widget]) {
            array_push($initial, ...$this->restores[$widget] ?? []);
        }

        return $initial;
    }

    /**
     * Takes the controls as they are to be in the order of the document, like those of a document built whole.
     */
    public function markSorted(): void
    {
        $this->sorted = \count($this->controls);
        $this->submitsSorted = \count($this->submits);
    }

    /**
     * Puts the controls in the order of the document, which values are submitted in: those added after the others,
     * like the content of an element replaced, are where they are in it.
     */
    public function sort(): void
    {
        if ($this->sorted === \count($this->controls) && $this->submitsSorted === \count($this->submits)) {
            return;
        }
        self::insertSorted($this->controls, $this->sorted);
        $this->sorted = \count($this->controls);
        self::insertSorted($this->submits, $this->submitsSorted);
        $this->submitsSorted = \count($this->submits);
        // The default button is the first submit button in that order
        $default = $this->submits[0][0] ?? null;
        if ($default !== $this->default && null !== $this->default && $this->default->hasState('default')) {
            $this->default->setState('default', false);
        }
        if (null !== $default && !$default->hasState('default')) {
            $default->setState('default');
        }
        $this->default = $default;
    }

    /**
     * Puts the items after the first $sorted ones, added since, each where it is among them, found by halves.
     *
     * @param list<array{AbstractWidget, \Dom\Element}> $items
     */
    private static function insertSorted(array &$items, int $sorted): void
    {
        $added = \array_slice($items, $sorted);
        $items = \array_slice($items, 0, $sorted);
        foreach ($added as $item) {
            [$low, $high] = [0, \count($items)];
            while ($low < $high) {
                $middle = ($low + $high) >> 1;
                if ($items[$middle][1]->compareDocumentPosition($item[1]) & \Dom\Node::DOCUMENT_POSITION_FOLLOWING) {
                    $low = $middle + 1;
                } else {
                    $high = $middle;
                }
            }
            array_splice($items, $low, 0, [$item]);
        }
    }

    /**
     * Removes a control from the form, like an element removed from the document.
     */
    public function remove(AbstractWidget $widget): void
    {
        $widget = $widget instanceof DropdownWidget ? $widget->getList() : $widget;
        if (!isset($this->restores[$widget])) {
            return;
        }
        $index = array_search($widget, array_column($this->controls, 0), true);
        \assert(false !== $index);
        array_splice($this->controls, $index, 1);
        if ($index < $this->sorted) {
            --$this->sorted;
        }
        unset($this->restores[$widget]);
        if ((self::$owners[$widget] ?? null) === $this) {
            unset(self::$owners[$widget]);
        }
        if (false !== $index = array_search($widget, array_column($this->submits, 0), true)) {
            array_splice($this->submits, $index, 1);
            if ($index < $this->submitsSorted) {
                --$this->submitsSorted;
            }
        }
        // The default button removed: the next one in the order of the document, those added since put there by sort()
        if ($widget === $this->default) {
            $this->default = $this->submitsSorted > 0 ? $this->submits[0][0] : null;
            $this->default?->setState('default');
        }
        // A control that stays in the page, like one whose form attribute named a form removed, no longer submits it
        foreach ($this->listeners[$widget] ?? [] as [$event, $listener]) {
            $widget->off($event, $listener);
        }
        unset($this->listeners[$widget]);
        if ($widget->hasState('default')) {
            $widget->setState('default', false);
        }
    }

    /**
     * Removes every control, like the <form> leaving the document: those outside it stay, bound to no form.
     */
    public function clear(): void
    {
        foreach ($this->controls as [$widget]) {
            $this->remove($widget);
        }
    }
}
