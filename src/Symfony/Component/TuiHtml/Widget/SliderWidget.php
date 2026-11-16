<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\TuiHtml\Widget;

use Symfony\Component\Tui\Ansi\AnsiUtils;
use Symfony\Component\Tui\Event\ChangeEvent;
use Symfony\Component\Tui\Input\Key;
use Symfony\Component\Tui\Render\RenderContext as NativeContext;
use Symfony\Component\Tui\Widget\AbstractWidget;
use Symfony\Component\Tui\Widget\FocusableInterface;
use Symfony\Component\Tui\Widget\FocusableTrait;
use Symfony\Component\Tui\Widget\KeybindingsTrait;
use Symfony\Component\TuiHtml\Internal\ContextBridge;
use Symfony\Component\TuiHtml\Internal\StyleBridge;
use Symfony\Component\TuiHtml\Render\RenderContext;

/**
 * A value picked on a track, like <input type="range">.
 *
 * Left and right (or down and up) move it by a step, page up and page down
 * by a tenth of the range, home and end to the bounds. The "track-fill",
 * "thumb" and "track" sub-elements style the parts of the track.
 *
 * @experimental
 */
class SliderWidget extends AbstractWidget implements FocusableInterface, NativeAppearanceInterface
{
    use FocusableTrait;
    use KeybindingsTrait;
    private float $value;
    private bool $native = true;

    public function __construct(private readonly float $min = 0.0, private readonly float $max = 100.0, private readonly float $step = 1.0, ?float $value = null)
    {
        $this->value = $this->constrain($value ?? $min + ($max - $min) / 2);
    }

    public function getValue(): float
    {
        return $this->value;
    }

    public function getMin(): float
    {
        return $this->min;
    }

    public function getMax(): float
    {
        return $this->max;
    }

    public function getStep(): float
    {
        return $this->step;
    }

    public function setNativeAppearance(bool $native): void
    {
        $this->native = $native;
    }

    /**
     * Whether it has its own look, not one of CSS, like appearance: none: it draws the same, its parts styled.
     */
    public function hasNativeAppearance(): bool
    {
        return $this->native;
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

    /**
     * The value at a column of the track drawn on some columns, like where a pointer presses or drags the thumb.
     *
     * @param bool $clamp Whether a column before or past the track is its first or last one, like a drag going past
     *                    its ends; otherwise, null for one past it, like on the label of the value
     */
    public function valueAt(int $column, int $columns, bool $clamp = true): ?float
    {
        [$track] = $this->track($columns);
        if (!$clamp && ($column < 0 || $column >= $track)) {
            return null;
        }
        $range = $this->max - $this->min;
        if ($track <= 1 || $range <= 0) {
            return $this->value;
        }

        return $this->constrain($this->min + max(0, min($track - 1, $column)) / ($track - 1) * $range);
    }

    public function render(NativeContext|RenderContext $context): array
    {
        $context = ContextBridge::computed($context);
        $columns = $context->getColumns();
        [$track, $label] = $this->track($columns);
        // The thumb takes one cell of the track, at the position of the value
        $range = $this->max - $this->min;
        $position = $range > 0 ? (int) round(($this->value - $this->min) / $range * ($track - 1)) : 0;
        $line = StyleBridge::applyElement($this, 'track-fill', str_repeat('━', $position)).StyleBridge::applyElement($this, 'thumb', '●').StyleBridge::applyElement($this, 'track', str_repeat('─', max(0, $track - 1 - $position))).$label;

        return [AnsiUtils::truncateToWidth($line, $columns, '')];
    }

    /**
     * @return array<string, string[]>
     */
    protected static function getDefaultKeybindings(): array
    {
        return ['increase' => [Key::RIGHT, Key::UP], 'decrease' => [Key::LEFT, Key::DOWN], 'page_increase' => [Key::PAGE_UP], 'page_decrease' => [Key::PAGE_DOWN], 'minimum' => [Key::HOME], 'maximum' => [Key::END]];
    }

    /**
     * @return array<string, string>
     */
    protected static function getDefaultKeybindingLabels(): array
    {
        return ['increase' => 'Increase', 'decrease' => 'Decrease'];
    }

    /**
     * The cells of the track on some columns, and the label of the value after it, when it fits.
     *
     * @return array{int, string}
     */
    private function track(int $columns): array
    {
        // As wide as the widest value, its bounds, right-aligned: the track keeps its width as the value changes
        $label = ' '.$this->getValueText();
        $width = max(AnsiUtils::visibleWidth($label), $this->widestLabel());
        if ($columns <= $width) {
            return [$columns, ''];
        }

        return [max(1, $columns - $width), str_repeat(' ', $width - AnsiUtils::visibleWidth($label)).$label];
    }

    /**
     * The width of the widest label of a value, a space before it: the digits of the widest bound, its sign, and the
     * decimals of the step, like 0.5 between 0 and 1.
     */
    private function widestLabel(): int
    {
        $decimals = self::decimals($this->step);
        $fraction = $decimals > 0 ? 1 + $decimals : 0;
        $digits = static fn (float $bound): int => \strlen((string) (int) floor(abs($bound)));

        return max(1 + \strlen(self::format($this->min, $this->step)), 1 + \strlen(self::format($this->constrain($this->max), $this->step)),
            1 + max(($this->min < 0 ? 1 : 0) + $digits($this->min), ($this->max < 0 ? 1 : 0) + $digits($this->max)) + $fraction);
    }

    /**
     * The decimals of a step, also one PHP writes with an exponent, like 1.0E-5.
     */
    private static function decimals(float $step): int
    {
        $text = rtrim(\sprintf('%.15F', $step), '0');

        return \strlen(substr($text, strpos($text, '.') + 1));
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
            while ($value > $max + 1.0E-9) {
                $value -= $this->step;
            }
        }

        return round($value, 10);
    }

    private static function format(float $value, float $step): string
    {
        $decimals = self::decimals($step);
        $text = number_format($value, $decimals, '.', '');
        if ($decimals > 0) {
            $text = rtrim(rtrim($text, '0'), '.');
        }

        return '-0' === $text ? '0' : $text;
    }
}
