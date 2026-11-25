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

use Symfony\Bridge\TuiHtml\Event\PressEvent;
use Symfony\Bridge\TuiHtml\Html\ElementFactoryInterface;
use Symfony\Bridge\TuiHtml\Html\HtmlBuilder;
use Symfony\Bridge\TuiHtml\Widget\CheckboxWidget;
use Symfony\Bridge\TuiHtml\Widget\EditorWidget;
use Symfony\Bridge\TuiHtml\Widget\FormWidget;
use Symfony\Bridge\TuiHtml\Widget\InputWidget;
use Symfony\Bridge\TuiHtml\Widget\SelectListWidget;
use Symfony\Bridge\TuiHtml\Widget\SliderWidget;
use Symfony\Component\HtmlRenderer\Form\Submission;
use Symfony\Component\Tui\Event\SubmitEvent;
use Symfony\Component\Tui\Widget\AbstractWidget;

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
     * @return list<array{class-string, \Closure}> The listeners added, for the control to leave the form, see FormBinding::remove()
     *
     * @internal
     */
    public static function wire(FormWidget $form, \Dom\Element $element, AbstractWidget $widget, \Dom\Element $control, \Closure $controls, \Closure $initial): array
    {
        if (self::isResetButton($control)) {
            $widget->on(PressEvent::class, $listener = static fn () => self::reset($initial()));

            return [[PressEvent::class, $listener]];
        }
        if (self::isSubmitButton($control)) {
            $widget->on(PressEvent::class, $listener = static fn () => self::submit($form, $element, $controls(), $widget, $control));

            return [[PressEvent::class, $listener]];
        }
        if ($widget instanceof InputWidget) {
            // Implicit submission, like browsers: through the first submit button, or when the field is the only one
            $widget->on(SubmitEvent::class, $listener = static function () use ($form, $element, $controls): void {
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

            return [[SubmitEvent::class, $listener]];
        }

        return [];
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
                $restores[] = static function () use ($widget, $checked): void {
                    $widget->setChecked($checked);
                };
            } elseif ($widget instanceof InputWidget) {
                $value = $widget->getValue();
                $restores[] = static function () use ($widget, $value): void {
                    $widget->setValue($value, true);
                };
            } elseif ($widget instanceof EditorWidget) {
                $text = $widget->getText();
                $restores[] = static function () use ($widget, $text): void {
                    $widget->setText($text);
                };
            } elseif ($widget instanceof SliderWidget) {
                $value = $widget->getValue();
                $restores[] = static function () use ($widget, $value): void {
                    $widget->setValue($value, true);
                };
            } elseif ($widget instanceof SelectListWidget) {
                $items = $widget->getItems();
                $index = $widget->getSelectedIndex();
                $restores[] = static function () use ($widget, $items, $index): void {
                    $widget->setItems($items);
                    $widget->setSelectedIndex($index);
                };
            }
            // Like a reset: the user did not interact with the restored values
            $restores[] = static function () use ($widget): void {
                $widget->setState('user-valid', false)->setState('user-invalid', false);
            };
        }

        return $restores;
    }

    /**
     * @internal
     */
    public static function isSubmitButton(\Dom\Element $control): bool
    {
        return Submission::isSubmitButton($control);
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
            foreach (Submission::values($control, self::valuesOf($widget, $control), $submitterElement) as $name => $submitted) {
                foreach ((array) $submitted as $value) {
                    if (str_ends_with((string) $name, '[]')) {
                        $list = $values[substr($name, 0, -2)] ?? [];
                        $values[substr($name, 0, -2)] = [...\is_array($list) ? $list : [$list], $value];
                    } else {
                        // The retained PHP-friendly API keeps its last-value-wins mapping.
                        $values[$name] = $value;
                    }
                }
            }
        }
        [$action, $method] = Submission::settings($element, $submitterElement, true);
        $form->submit($values, $submitter, $action, $method);
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
            $widget instanceof SelectListWidget => $control->hasAttribute('multiple') ? array_map(static fn (array $item) => $item['value'], $widget->getSelectedItems()) : (null !== ($item = $widget->getSelectedItem()) ? [$item['value']] : []),
            default => [$control->getAttribute('value') ?? ''],
        };
    }
}
