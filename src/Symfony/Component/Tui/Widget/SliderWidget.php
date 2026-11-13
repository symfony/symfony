<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Tui\Widget;

use Symfony\Component\Tui\Ansi\AnsiUtils;
use Symfony\Component\Tui\Event\ChangeEvent;
use Symfony\Component\Tui\Input\Key;
use Symfony\Component\Tui\Render\RenderContext;

/**
 * A value picked on a track, like <input type="range">.
 *
 * Left and right (or down and up) move it by a step, page up and page down
 * by a tenth of the range, home and end to the bounds. The "track-fill",
 * "thumb" and "track" sub-elements style the parts of the track.
 *
 * @experimental
 */
class SliderWidget extends AbstractWidget implements FocusableInterface
{
    use FocusableTrait;
    use KeybindingsTrait;

    private float $value;

    public function __construct(
        private readonly float $min = 0.0,
        private readonly float $max = 100.0,
        private readonly float $step = 1.0,
        ?float $value = null,
    ) {
        $this->value = $this->constrain($value ?? ($min + ($max - $min) / 2));
    }

    public function getValue(): float
    {
        return $this->value;
    }

    /**
     * The value as a form submits it: without trailing zeros.
     */
    public function getValueText(): string
    {
        return self::format($this->value, $this->step);
    }

    /**
     * @param bool $dispatch Whether to dispatch a ChangeEvent when the value changes, like a move by the user
     *
     * @return $this
     */
    public function setValue(float $value, bool $dispatch = false): static
    {
        $value = $this->constrain($value);
        if ($value !== $this->value) {
            $this->value = $value;
            $this->invalidate();
            if ($dispatch) {
                $this->dispatch(new ChangeEvent($this, $this->getValueText()));
            }
        }

        return $this;
    }

    /**
     * @param callable(ChangeEvent): void $callback
     *
     * @return $this
     */
    public function onChange(callable $callback): static
    {
        return $this->on(ChangeEvent::class, $callback);
    }

    public function handleInput(string $data): void
    {
        if (null !== $this->onInput && ($this->onInput)($data)) {
            return;
        }
        if ($this->hasState('disabled')) {
            return;
        }

        $kb = $this->getKeybindings();
        $page = max($this->step, ($this->max - $this->min) / 10);
        $value = match (true) {
            $kb->matches($data, 'increase') => $this->value + $this->step,
            $kb->matches($data, 'decrease') => $this->value - $this->step,
            $kb->matches($data, 'page_increase') => $this->value + $page,
            $kb->matches($data, 'page_decrease') => $this->value - $page,
            $kb->matches($data, 'minimum') => $this->min,
            $kb->matches($data, 'maximum') => $this->max,
            default => null,
        };
        if (null !== $value) {
            $this->setValue($value, true);
        }
    }

    public function render(RenderContext $context): array
    {
        $columns = $context->getColumns();
        $label = ' '.$this->getValueText();
        $track = max(1, $columns - AnsiUtils::visibleWidth($label));
        if ($columns <= AnsiUtils::visibleWidth($label)) {
            $track = $columns;
            $label = '';
        }

        // The thumb takes one cell of the track, at the position of the value
        $range = $this->max - $this->min;
        $position = $range > 0 ? (int) round(($this->value - $this->min) / $range * ($track - 1)) : 0;
        $line = $this->applyElement('track-fill', str_repeat('━', $position))
            .$this->applyElement('thumb', '●')
            .$this->applyElement('track', str_repeat('─', max(0, $track - 1 - $position)))
            .$label;

        return [AnsiUtils::truncateToWidth($line, $columns, '')];
    }

    /**
     * @return array<string, string[]>
     */
    protected static function getDefaultKeybindings(): array
    {
        return [
            'increase' => [Key::RIGHT, Key::UP],
            'decrease' => [Key::LEFT, Key::DOWN],
            'page_increase' => [Key::PAGE_UP],
            'page_decrease' => [Key::PAGE_DOWN],
            'minimum' => [Key::HOME],
            'maximum' => [Key::END],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected static function getDefaultKeybindingLabels(): array
    {
        return ['increase' => 'Increase', 'decrease' => 'Decrease'];
    }

    /**
     * Clamps to the bounds and snaps to the step from the minimum, like HTML.
     */
    private function constrain(float $value): float
    {
        $max = max($this->min, $this->max);
        $value = max($this->min, min($max, $value));
        if ($this->step > 0) {
            $value = $this->min + round(($value - $this->min) / $this->step) * $this->step;
            // The step may not reach the maximum: the highest step below it
            while ($value > $max + 1e-9) {
                $value -= $this->step;
            }
        }

        return round($value, 10);
    }

    private static function format(float $value, float $step): string
    {
        $decimals = \strlen(substr(strrchr((string) $step, '.') ?: '.', 1));
        $text = number_format($value, $decimals, '.', '');
        if ($decimals > 0) {
            $text = rtrim(rtrim($text, '0'), '.');
        }

        return '-0' === $text ? '0' : $text;
    }
}
