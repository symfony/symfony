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
    /** @var list<\Closure(): void> */
    private array $initial = [];

    public function __construct(private readonly FormWidget $form, private readonly \Dom\Element $element)
    {
    }

    public function add(AbstractWidget $widget, \Dom\Element $control): void
    {
        // A dropdown's value is that of its list
        $widget = $widget instanceof DropdownWidget ? $widget->getList() : $widget;
        $this->controls[] = [$widget, $control];
        array_push($this->initial, ...FormElement::snapshot([[$widget, $control]]));
        // The default button, the one implicit submission presses, matches :default: the first submit button
        if (FormElement::isSubmitButton($control) && $widget === array_find($this->controls, static fn (array $c) => FormElement::isSubmitButton($c[1]))[0]) {
            $widget->setState('default');
        }
        FormElement::wire($this->form, $this->element, $widget, $control, fn (): array => $this->controls, fn (): array => $this->initial);
    }
}
