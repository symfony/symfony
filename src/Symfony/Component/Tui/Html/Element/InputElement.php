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

use Symfony\Component\Tui\Event\ChangeEvent;
use Symfony\Component\Tui\Exception\InvalidArgumentException;
use Symfony\Component\Tui\Html\ElementFactoryInterface;
use Symfony\Component\Tui\Html\HtmlBuilder;
use Symfony\Component\Tui\Input\Key;
use Symfony\Component\Tui\Widget\AbstractWidget;
use Symfony\Component\Tui\Widget\ButtonWidget;
use Symfony\Component\Tui\Widget\ColorInputWidget;
use Symfony\Component\Tui\Widget\ComboboxWidget;
use Symfony\Component\Tui\Widget\ContainerWidget;
use Symfony\Component\Tui\Widget\InputWidget;
use Symfony\Component\Tui\Widget\SliderWidget;
use Symfony\Component\Tui\Widget\Text\InlineImages;

/**
 * Single-line text inputs, checkboxes and radios.
 *
 * @experimental
 */
final class InputElement implements ElementFactoryInterface
{
    private const TYPES = ['text', 'search', 'email', 'url', 'tel', 'number', 'password', 'date', 'time', 'datetime-local', 'month', 'week', 'color', 'file'];

    public function __construct(
        private readonly CheckboxElement $checkbox = new CheckboxElement(),
        private readonly RadioElement $radio = new RadioElement(),
    ) {
    }

    public function create(\Dom\Element $element, HtmlBuilder $builder): AbstractWidget
    {
        $type = strtolower($element->getAttribute('type') ?? 'text');
        if ('checkbox' === $type) {
            return $this->checkbox->create($element, $builder);
        }
        if ('radio' === $type) {
            return $this->radio->create($element, $builder);
        }
        if ('range' === $type) {
            return $this->range($element, $builder);
        }
        // Not drawn, its value submitted with its form, like browsers
        if ('hidden' === $type) {
            return new ContainerWidget();
        }
        if (\in_array($type, ['submit', 'button', 'reset', 'image'], true)) {
            // A button labeled by its value, like browsers; an image one by its alternative text
            $label = ('image' === $type ? $builder->getAttributeText($element, 'alt') : null) ?? $builder->getAttributeText($element, 'value') ?? match ($type) {
                'submit', 'image' => 'Submit',
                'reset' => 'Reset',
                default => '',
            };
            // An image one drawn as its image, a row tall, like the icon of a button; its text alternative its name
            $factory = $builder->getRegistry()->get('img');
            $image = 'image' === $type && $factory instanceof ImageElement ? $factory->inlineImage($element, $builder) : null;
            $button = new ButtonWidget(null !== $image ? InlineImages::text(0, $image[1]) : $label);
            if (null !== $image) {
                $button->setInlineImages([[...$image, $label]]);
            }
            if ('' !== $label) {
                $button->setLabel($label);
            }

            return $button;
        }
        if (!\in_array($type, self::TYPES, true)) {
            throw new InvalidArgumentException(\sprintf('<input type="%s"> is not supported; supported types are "%s".', $type, implode('", "', [...self::TYPES, 'checkbox', 'radio', 'range', 'submit', 'button', 'reset', 'image', 'hidden'])));
        }

        $input = new InputWidget();
        $input->setMasked('password' === $type);
        // Where its content sizes it, like in a row: as wide as size characters, 20 by default, like browsers
        $size = trim($element->getAttribute('size') ?? '');
        $input->setPreferredColumns(ctype_digit($size) && (int) $size > 0 ? (int) $size : 20);
        // A file field is a path, typed in: browsers ignore its value attribute; a color is #rrggbb, black by default
        if ('file' !== $type && null !== $value = $element->getAttribute('value')) {
            $input->setValue('color' === $type ? strtolower(trim($value)) : $value);
        } elseif ('color' === $type) {
            $input->setValue('#000000');
        }
        if ('file' === $type && null === $element->getAttribute('placeholder')) {
            $input->setPlaceholder($element->hasAttribute('multiple') ? 'Paths to files' : 'Path to a file');
        }
        // A path completed like in a shell: the entries of its directory, the files of the types accept names; Tab
        // takes the first, a directory going on in it
        if ('file' === $type) {
            $completer = new PathCompleter($element->getAttribute('accept'), $element->hasAttribute('multiple'));
            $input->onChange(static function (ChangeEvent $event) use ($input, $completer): void {
                $input->setSuggestions($completer->complete($event->getValue()));
            });
        }
        if (null !== $placeholder = $builder->getAttributeText($element, 'placeholder')) {
            $input->setPlaceholder($placeholder);
        }
        $input->setReadOnly($element->hasAttribute('readonly'));
        // <input list>: the options of the <datalist> are suggestions
        $suggestions = null;
        $list = $element->getAttribute('list');
        $datalist = null !== $list && '' !== $list ? $element->ownerDocument->getElementById($list) : null;
        if (null !== $datalist && 'datalist' === strtolower($datalist->localName)) {
            $suggestions = [];
            foreach ($datalist->querySelectorAll('option') as $option) {
                if (!$option->hasAttribute('disabled')) {
                    $suggestions[] = $builder->getAttributeText($option, 'value') ?? trim($builder->getText($option));
                }
            }
            $suggestions = array_values(array_unique(array_filter($suggestions, static fn (string $suggestion) => '' !== trim($suggestion))));
        }
        if (ctype_digit(trim($element->getAttribute('maxlength') ?? ''))) {
            $input->setMaxLength((int) trim($element->getAttribute('maxlength') ?? ''));
        }
        // A number field steps with Up and Down
        $stepper = 'number' === $type ? self::stepper($input, $element) : null;
        if (null !== $stepper) {
            $input->onInput($stepper);
        }
        FormControlStates::apply($input, $element, !$input->isReadOnly());
        ConstraintValidation::text($input, $element, $type, $input->getValue(...));

        if ('color' === $type) {
            return new ColorInputWidget($input);
        }

        // <input list>: the first suggestion extending the value is shown after the cursor, Tab takes it; and the
        // suggestions are listed below it as it is typed in; a number field still steps, Alt+Down opens its list
        if ($suggestions) {
            $input->setSuggestions($suggestions);

            return new ComboboxWidget($input, $suggestions, $stepper);
        }

        return $input;
    }

    /**
     * <input type="range">: a slider, between min (0) and max (100), by step (1), at value (the middle).
     */
    private function range(\Dom\Element $element, HtmlBuilder $builder): SliderWidget
    {
        $min = is_numeric($min = $element->getAttribute('min')) ? (float) $min : 0.0;
        $max = is_numeric($max = $element->getAttribute('max')) ? (float) $max : 100.0;
        $step = is_numeric($step = $element->getAttribute('step')) && (float) $step > 0 ? (float) $step : 1.0;
        $value = is_numeric($value = $element->getAttribute('value')) ? (float) $value : null;

        $slider = new SliderWidget($min, max($min, $max), $step, $value);
        FormControlStates::apply($slider, $element, false);

        return $slider;
    }

    /**
     * Up and down step a number field, like browsers: by step (1), within min and max.
     */
    /**
     * Up and Down for a number field: true when they stepped it.
     *
     * @return \Closure(string): bool
     */
    private static function stepper(InputWidget $input, \Dom\Element $element): \Closure
    {
        $step = is_numeric($step = $element->getAttribute('step')) && (float) $step > 0 ? (float) $step : 1.0;
        $min = is_numeric($min = $element->getAttribute('min')) ? (float) $min : null;
        $max = is_numeric($max = $element->getAttribute('max')) ? (float) $max : null;

        return static function (string $data) use ($input, $step, $min, $max): bool {
            $parser = $input->getKeybindings()->getParser();
            $direction = match (true) {
                $parser->matches($data, Key::UP) => 1,
                $parser->matches($data, Key::DOWN) => -1,
                default => 0,
            };
            if (0 === $direction || $input->isReadOnly() || $input->hasState('disabled')) {
                return false;
            }

            // Like HTML: an empty value is 0
            $current = is_numeric($input->getValue()) ? (float) $input->getValue() : 0.0;
            // Like HTML: a value off the steps goes to the nearest step in the direction, one on a step to the next one
            $base = $min ?? 0.0;
            $steps = ($current - $base) / $step;
            $steps = abs($steps - round($steps)) < 1e-9 ? round($steps) + $direction : (1 === $direction ? floor($steps) + 1 : ceil($steps) - 1);
            $next = $base + $steps * $step;
            if (null !== $min) {
                $next = max($min, $next);
            }
            if (null !== $max) {
                $next = min($max, $next);
            }
            // As many decimals as the step has, trailing zeros dropped
            $decimals = \strlen(substr(strrchr((string) $step, '.') ?: '.', 1));
            $formatted = number_format($next, $decimals, '.', '');
            if ($decimals > 0) {
                $formatted = rtrim(rtrim($formatted, '0'), '.');
            }
            if ('-0' === $formatted) {
                $formatted = '0';
            }
            $input->setValue($formatted, true);

            return true;
        };
    }
}
