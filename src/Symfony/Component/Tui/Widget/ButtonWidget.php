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
use Symfony\Component\Tui\Ansi\Bidi;
use Symfony\Component\Tui\Event\PressEvent;
use Symfony\Component\Tui\Input\Key;
use Symfony\Component\Tui\Render\RenderContext;
use Symfony\Component\Tui\Style\RasterImage;
use Symfony\Component\Tui\Style\Style;
use Symfony\Component\Tui\Style\VectorImage;
use Symfony\Component\Tui\Widget\Text\GeneratedContentInterface;
use Symfony\Component\Tui\Widget\Text\InlineImages;
use Symfony\Component\Tui\Widget\Util\StringUtils;

/**
 * A button, pressed with Enter or Space.
 *
 * It renders as "[ Label ]"; the "label" sub-element styles the text.
 * A widget in the "disabled" state cannot be pressed.
 *
 * @experimental
 */
class ButtonWidget extends AbstractWidget implements FocusableInterface, ActivatableInterface, GeneratedContentInterface, NativeAppearanceInterface
{
    use FocusableTrait;
    use KeybindingsTrait;

    private const BEFORE = '[ ';
    private const AFTER = ' ]';

    private string $text;
    private string $before = self::BEFORE;
    private string $after = self::AFTER;
    /** @var array{?string, ?string} What CSS ::before and ::after give, null for the brackets */
    private array $generated = [null, null];
    private bool $native = true;
    private string $icon = '';
    private ?Style $iconStyle = null;
    private ?InlineImages $images = null;

    public function __construct(string $text = '')
    {
        $this->text = StringUtils::stripControlBytes(StringUtils::sanitizeUtf8($text));
    }

    public function getText(): string
    {
        return $this->text;
    }

    /**
     * @param bool $styled Whether the text keeps its styles (SGR) and hyperlinks (OSC 8), like the composed text of a
     *                     RichTextWidget; the other control bytes are dropped either way
     *
     * @return $this
     */
    public function setText(string $text, bool $styled = false): static
    {
        $text = $styled ? self::keepStyles($text) : StringUtils::stripControlBytes(StringUtils::sanitizeUtf8($text));
        if ($text !== $this->text) {
            $this->text = $text;
            $this->invalidate();
        }

        return $this;
    }

    /**
     * Sets the images drawn in the text, like the <svg> icon of a <button>, each standing for characters of it, see
     * RichTextWidget::inlineImageText().
     *
     * @param list<array{0: RasterImage|VectorImage, 1: int, 2?: string}> $images
     *
     * @return $this
     */
    public function setInlineImages(array $images): static
    {
        $this->images = $images ? new InlineImages(\array_slice(array_values($images), 0, InlineImages::MAX)) : null;
        $this->invalidate();

        return $this;
    }

    /**
     * Sets an icon drawn after the label, in a style of its own, like the ▾ of a select; '' for none.
     *
     * @return $this
     */
    public function setIcon(string $icon, ?Style $style = null): static
    {
        $icon = StringUtils::stripControlBytes(StringUtils::sanitizeUtf8($icon));
        if ($icon !== $this->icon || $style != $this->iconStyle) {
            $this->icon = $icon;
            $this->iconStyle = $style;
            $this->invalidate();
        }

        return $this;
    }

    public function getIcon(): string
    {
        return $this->icon;
    }

    /**
     * Replaces the brackets around the label, like CSS button::before and button::after.
     */
    public function setGeneratedContent(?string $before, ?string $after): void
    {
        $this->generated = [$before, $after];
        $this->decorate();
    }

    /**
     * Draws the brackets around the label, or not, like CSS appearance: none; generated content still is.
     */
    public function setNativeAppearance(bool $native): void
    {
        $this->native = $native;
        $this->decorate();
    }

    private function decorate(): void
    {
        $before = StringUtils::stripControlBytes(StringUtils::sanitizeUtf8($this->generated[0] ?? ($this->native ? self::BEFORE : '')));
        $after = StringUtils::stripControlBytes(StringUtils::sanitizeUtf8($this->generated[1] ?? ($this->native ? self::AFTER : '')));
        if ($before !== $this->before || $after !== $this->after) {
            $this->before = $before;
            $this->after = $after;
            $this->invalidate();
        }
    }

    /**
     * @param callable(PressEvent): void $callback
     *
     * @return $this
     */
    public function onPress(callable $callback): static
    {
        return $this->on(PressEvent::class, $callback);
    }

    /**
     * Presses the button, unless it is disabled.
     */
    public function press(): bool
    {
        if ($this->hasState('disabled')) {
            return false;
        }

        $this->dispatch(new PressEvent($this));

        return true;
    }

    public function handleInput(string $data): void
    {
        if (null !== $this->onInput && ($this->onInput)($data)) {
            return;
        }

        if ($this->getKeybindings()->matches($data, 'press')) {
            $this->press();
        }
    }

    public function render(RenderContext $context): array
    {
        $icon = '';
        if ('' !== $this->icon) {
            $styled = $this->iconStyle?->apply($this->icon) ?? $this->icon;
            // The rest of the button keeps its own style after the icon's
            $icon = ('' !== $this->text ? ' ' : '').($styled === $this->icon ? $styled : $styled."\x1b[0m".$this->getContext()?->resolveStyle($this)->getAnsiRestore());
        }
        // A label of right-to-left text in visual order, its letters joined, like the text of a page
        $label = $this->text;
        if (Bidi::isAvailable() && Bidi::hasRightToLeft($label)) {
            $label = Bidi::reorderLine($label, Bidi::direction($label) ?? 0);
        }
        $line = $this->before.$this->applyElement('label', $label).$icon.$this->after;

        $lines = [AnsiUtils::truncateToWidth($line, max(0, $context->getColumns()))];

        if (null === $this->images) {
            return $lines;
        }
        // In the style of the button, which is put around its line: its icons in its color, the style going on after them
        $base = $this->getContext()?->resolveStyle($this)->getAnsiRestore() ?? '';

        return $this->images->draw([$base.$lines[0]], $this->getContext()?->getMediaContext()->colorScheme);
    }

    /**
     * @return array<string, string[]>
     */
    protected static function getDefaultKeybindings(): array
    {
        return [
            'press' => [Key::ENTER, Key::SPACE],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected static function getDefaultKeybindingLabels(): array
    {
        return ['press' => 'Press'];
    }

    public function activate(): bool
    {
        return $this->press();
    }

    /**
     * Drops the control bytes of a text but those of its styles and hyperlinks.
     */
    private static function keepStyles(string $text): string
    {
        $parts = preg_split('/(\x1b\[[0-9;:]*m|\x1b\]8;[^\x07\x1b]*(?:\x07|\x1b\\\\))/', StringUtils::sanitizeUtf8($text), -1, \PREG_SPLIT_DELIM_CAPTURE) ?: [];

        return implode('', array_map(static fn (string $part, int $i): string => 1 === $i % 2 ? $part : StringUtils::stripControlBytes($part), $parts, array_keys($parts)));
    }
}
