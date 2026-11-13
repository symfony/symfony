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

use Symfony\Component\Tui\Event\PressEvent;
use Symfony\Component\Tui\Event\SubmitEvent;
use Symfony\Component\Tui\Html\ElementFactoryInterface;
use Symfony\Component\Tui\Html\HtmlBuilder;
use Symfony\Component\Tui\Widget\AbstractWidget;
use Symfony\Component\Tui\Widget\CheckboxWidget;
use Symfony\Component\Tui\Widget\EditorWidget;
use Symfony\Component\Tui\Widget\FormWidget;
use Symfony\Component\Tui\Widget\InputWidget;
use Symfony\Component\Tui\Widget\SelectListWidget;
use Symfony\Component\Tui\Widget\SliderWidget;

/**
 * <form>: its controls are submitted together, like in a browser.
 *
 * A submit button (a <button> is one unless its type says otherwise) or
 * Enter in a single-line field (implicit submission) submits the form:
 * when a control is invalid, the form is not submitted, its invalid controls
 * get :user-invalid and the first one the focus (unless novalidate or
 * formnovalidate); otherwise the form dispatches a FormSubmitEvent with the
 * values of its named, enabled controls, and its action and method (those
 * of the submit button first, like formaction and formmethod). A reset
 * button restores the initial values. Its controls are those it holds and
 * those whose form attribute names it, wherever they are.
 *
 * @experimental
 */
final class FormElement implements ElementFactoryInterface
{
    public function create(\Dom\Element $element, HtmlBuilder $builder): AbstractWidget
    {
        $form = new FormWidget();
        $binding = new FormBinding($form, $element);
        // The controls before it whose form attribute names it, then those it holds, then the ones after it
        $builder->bindForm($element, $binding);
        $builder->buildChildren($element, $form);

        return $form;
    }

    /**
     * Wires a control of a form: a reset button restores the initial values, a submit button submits the form, Enter
     * in a single-line field submits it through its first submit button.
     *
     * @param \Closure(): list<array{AbstractWidget, \Dom\Element}> $controls The controls of the form so far, this one included
     * @param \Closure(): list<\Closure(): void>                    $initial  How to restore them
     *
     * @internal
     */
    public static function wire(FormWidget $form, \Dom\Element $element, AbstractWidget $widget, \Dom\Element $control, \Closure $controls, \Closure $initial): void
    {
        if (self::isResetButton($control)) {
            $widget->on(PressEvent::class, static fn () => self::reset($initial()));

            return;
        }
        if (self::isSubmitButton($control)) {
            $widget->on(PressEvent::class, static fn () => self::submit($form, $element, $controls(), $widget, $control));
        } elseif ($widget instanceof InputWidget) {
            // Implicit submission, like browsers: through the first submit button, or when the field is the only one
            $widget->on(SubmitEvent::class, static function () use ($form, $element, $controls): void {
                $all = $controls();
                if (null !== $first = array_find($all, static fn (array $control) => self::isSubmitButton($control[1]))) {
                    [$button, $buttonElement] = $first;
                    if (!$button->hasState('disabled')) {
                        self::submit($form, $element, $all, $button, $buttonElement);
                    }

                    return;
                }
                if (1 === \count(array_filter($all, static fn (array $control) => $control[0] instanceof InputWidget))) {
                    self::submit($form, $element, $all, null, null);
                }
            });
        }
    }

    /**
     * @internal
     */
    public static function isResetButton(\Dom\Element $control): bool
    {
        return \in_array(strtolower($control->localName), ['button', 'input'], true) && 'reset' === strtolower($control->getAttribute('type') ?? '');
    }

    /**
     * Captures how to restore each control to its initial state.
     *
     * @param list<array{AbstractWidget, \Dom\Element}> $controls
     *
     * @return list<\Closure(): void>
     *
     * @internal
     */
    public static function snapshot(array $controls): array
    {
        $restores = [];
        foreach ($controls as [$widget]) {
            if ($widget instanceof CheckboxWidget) {
                $checked = $widget->isChecked();
                $restores[] = static function () use ($widget, $checked): void { $widget->setChecked($checked); };
            } elseif ($widget instanceof InputWidget) {
                $value = $widget->getValue();
                $restores[] = static function () use ($widget, $value): void { $widget->setValue($value, true); };
            } elseif ($widget instanceof EditorWidget) {
                $text = $widget->getText();
                $restores[] = static function () use ($widget, $text): void { $widget->setText($text); };
            } elseif ($widget instanceof SliderWidget) {
                $value = $widget->getValue();
                $restores[] = static function () use ($widget, $value): void { $widget->setValue($value, true); };
            } elseif ($widget instanceof SelectListWidget) {
                $items = $widget->getItems();
                $index = $widget->getSelectedIndex();
                $restores[] = static function () use ($widget, $items, $index): void {
                    $widget->setItems($items);
                    $widget->setSelectedIndex($index);
                };
            }
            // Like a reset: the user did not interact with the restored values
            $restores[] = static function () use ($widget): void { $widget->setState('user-valid', false)->setState('user-invalid', false); };
        }

        return $restores;
    }

    /**
     * @param list<\Closure(): void> $restores
     */
    private static function reset(array $restores): void
    {
        foreach ($restores as $restore) {
            $restore();
        }
    }

    /**
     * @internal
     */
    public static function isSubmitButton(\Dom\Element $control): bool
    {
        $type = strtolower($control->getAttribute('type') ?? '');

        return match (strtolower($control->localName)) {
            'button' => '' === $type || 'submit' === $type,
            'input' => 'submit' === $type || 'image' === $type,
            default => false,
        };
    }

    /**
     * @param list<array{AbstractWidget, \Dom\Element}> $controls
     */
    private static function submit(FormWidget $form, \Dom\Element $element, array $controls, ?AbstractWidget $submitter, ?\Dom\Element $submitterElement): void
    {
        $enabled = array_values(array_filter($controls, static fn (array $control) => !$control[0]->hasState('disabled')));

        if (!$element->hasAttribute('novalidate') && !($submitterElement?->hasAttribute('formnovalidate') ?? false)) {
            $invalid = array_values(array_filter($enabled, static fn (array $control) => $control[0]->hasState('invalid')));
            if ($invalid) {
                foreach ($invalid as [$widget]) {
                    $widget->setState('user-invalid')->setState('user-valid', false);
                }
                $invalid[0][0]->getContext()?->getFocusManager()->setFocus($invalid[0][0]);

                return;
            }
        }

        $values = [];
        foreach ($enabled as [$widget, $control]) {
            $name = $control->getAttribute('name');
            if (null === $name || '' === $name || (self::isSubmitButton($control) && $widget !== $submitter)) {
                continue;
            }
            // An image button sends where it was clicked: no pixel of it is
            if ('input' === strtolower($control->localName) && 'image' === strtolower($control->getAttribute('type') ?? '')) {
                $values[$name.'.x'] = '0';
                $values[$name.'.y'] = '0';
                continue;
            }
            foreach (self::valuesOf($widget, $control) as $value) {
                if (str_ends_with($name, '[]')) {
                    $list = $values[substr($name, 0, -2)] ?? [];
                    $values[substr($name, 0, -2)] = [...(\is_array($list) ? $list : [$list]), $value];
                } else {
                    // Like PHP: the last value of a name wins
                    $values[$name] = $value;
                }
            }
        }

        // Where and how, like the form submission of browsers: those of the button, else of the form
        $action = $submitterElement?->getAttribute('formaction') ?? $element->getAttribute('action') ?? '';
        $method = strtolower(trim($submitterElement?->getAttribute('formmethod') ?? $element->getAttribute('method') ?? ''));
        $form->submit($values, $submitter, $action, \in_array($method, ['post', 'dialog'], true) ? $method : 'get');
    }

    /**
     * @return list<string>
     */
    private static function valuesOf(AbstractWidget $widget, \Dom\Element $control): array
    {
        return match (true) {
            $widget instanceof CheckboxWidget => $widget->isChecked() ? [$widget->getValue()] : [],
            $widget instanceof InputWidget => [$widget->getValue()],
            $widget instanceof EditorWidget => [$widget->getText()],
            $widget instanceof SliderWidget => [$widget->getValueText()],
            $widget instanceof SelectListWidget => $control->hasAttribute('multiple')
                ? array_map(static fn (array $item) => $item['value'], $widget->getSelectedItems())
                : (null !== ($item = $widget->getSelectedItem()) ? [$item['value']] : []),
            default => [$control->getAttribute('value') ?? ''],
        };
    }
}
