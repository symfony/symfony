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
use Symfony\Component\Tui\Widget\AbstractWidget;
use Symfony\Component\Tui\Widget\FocusableInterface;
use Symfony\Component\Tui\Widget\FocusableTrait;
use Symfony\Component\Tui\Widget\KeybindingsTrait;
use Symfony\Component\Tui\Widget\Util\StringUtils;
use Symfony\Component\TuiHtml\Internal\ContextBridge;
use Symfony\Component\TuiHtml\Internal\StyleBridge;
use Symfony\Component\TuiHtml\Render\RenderContext;
use Symfony\Component\TuiHtml\Style\Color;
use Symfony\Component\TuiHtml\Style\Style;
use Symfony\Component\TuiHtml\Widget\Text\GeneratedContentInterface;

/**
 * A box that is checked or not, toggled with Space.
 *
 * Like an HTML checkbox, it only draws the box: "[x]" or "[ ]", styled by
 * the "box" sub-element. Its label is the text next to it. The "checked"
 * state follows the value, so :checked selectors match it.
 *
 * A ChangeEvent with "1" or "0" is dispatched on each change.
 *
 * @experimental
 */
class CheckboxWidget extends AbstractWidget implements FocusableInterface, ActivatableInterface, NativeAppearanceInterface, GeneratedContentInterface
{
    use FocusableTrait;
    use KeybindingsTrait;
    private bool $checked = false;
    private bool $native = true;
    /** @var array{?Color, ?Color}|null The colors of its mark, unchecked and checked, when CSS draws it too small for its border, see setCompactMark() */
    private ?array $compact = null;
    /** @var array{string, string} What CSS ::before and ::after give */
    private array $generated = ['', ''];

    public function __construct(bool $checked = false, private readonly string $value = 'on')
    {
        $this->setChecked($checked, false);
    }

    public function isChecked(): bool
    {
        return $this->checked;
    }

    public function getValue(): string
    {
        return $this->value;
    }

    /**
     * @return $this
     */
    public function setChecked(bool $checked, bool $dispatch = true): static
    {
        if ($checked === $this->checked && $checked === $this->hasState('checked')) {
            return $this;
        }
        $this->checked = $checked;
        $this->setState('checked', $checked);
        if ($dispatch) {
            $this->dispatch(new ChangeEvent($this, $checked ? '1' : '0'));
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

    /**
     * Toggles the box, unless the widget is disabled.
     */
    public function toggle(): bool
    {
        if ($this->hasState('disabled')) {
            return false;
        }
        $this->setChecked(!$this->checked);

        return true;
    }

    public function handleInput(string $data): void
    {
        if (null !== $this->onInput && ($this->onInput)($data)) {
            return;
        }
        if ($this->getKeybindings()->matches($data, 'toggle')) {
            $this->toggle();
        }
    }

    public function render(\Symfony\Component\Tui\Render\RenderContext|RenderContext $context): array
    {
        $context = ContextBridge::computed($context);
        // Drawn by CSS as a box too small for its border, like the form-check-input of Bootstrap: a mark, in its colors
        if (!$this->native && null !== $this->compact && ['', ''] === $this->generated) {
            $color = $this->checked ? $this->compact[1] ?? $this->compact[0] : $this->compact[0];
            $mark = $this->getMark();

            return [AnsiUtils::truncateToWidth(null !== $color ? (new Style(null, null, null, $color))->apply($mark) : $mark, max(0, $context->getColumns()), '')];
        }
        // Sized narrower than its box, like the h-4 w-4 of Tailwind: its mark, as browsers draw it at that size
        if ($this->native && ['', ''] === $this->generated && $context->getColumns() < AnsiUtils::visibleWidth($this->getGlyph())) {
            return [AnsiUtils::truncateToWidth(StyleBridge::applyElement($this, 'box', $this->getMark()), max(0, $context->getColumns()), '')];
        }
        // Without its look (appearance: none), only what CSS gives, like a ✓ from input:checked::before
        $box = $this->native ? StyleBridge::applyElement($this, 'box', $this->getGlyph()) : '';

        return [AnsiUtils::truncateToWidth($this->generated[0].$box.$this->generated[1], max(0, $context->getColumns()), '')];
    }

    /**
     * Draws a mark, ☐ or ☑, in place of a box CSS draws too small for its border, like a box of 1em with one: in the
     * color of its border, or of its background once checked; null for none.
     */
    public function setCompactMark(?Color $unchecked, ?Color $checked = null, bool $compact = true): void
    {
        $value = $compact ? [$unchecked, $checked] : null;
        if ($value != $this->compact) {
            $this->compact = $value;
            $this->invalidate();
        }
    }

    /**
     * Whether CSS draws it, without its own look nor content of its own, like appearance: none without ::before.
     */
    public function isDrawnByCss(): bool
    {
        return !$this->native && ['', ''] === $this->generated;
    }

    protected function getMark(): string
    {
        return $this->checked ? '☑' : '☐';
    }

    /**
     * Draws the box, or not, like CSS appearance: none.
     */
    public function setNativeAppearance(bool $native): void
    {
        if ($native !== $this->native) {
            $this->native = $native;
            $this->invalidate();
        }
    }

    /**
     * Draws text before and after the box, like CSS ::before and ::after.
     */
    public function setGeneratedContent(?string $before, ?string $after): void
    {
        $generated = [StringUtils::stripControlBytes(StringUtils::sanitizeUtf8($before ?? '')), StringUtils::stripControlBytes(StringUtils::sanitizeUtf8($after ?? ''))];
        if ($generated !== $this->generated) {
            $this->generated = $generated;
            $this->invalidate();
        }
    }

    protected function getGlyph(): string
    {
        return $this->checked ? '[x]' : '[ ]';
    }

    /**
     * @return array<string, string[]>
     */
    protected static function getDefaultKeybindings(): array
    {
        return ['toggle' => [Key::SPACE]];
    }

    /**
     * @return array<string, string>
     */
    protected static function getDefaultKeybindingLabels(): array
    {
        return ['toggle' => 'Toggle'];
    }

    public function activate(): bool
    {
        return $this->toggle();
    }
}
