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
use Symfony\Component\Tui\Input\Key;
use Symfony\Component\Tui\Input\Keybindings;
use Symfony\Component\TuiHtml\Event\PressEvent;
use Symfony\Component\TuiHtml\Internal\StyleBridge;
use Symfony\Component\TuiHtml\Style\PercentLength;
use Symfony\Component\TuiHtml\Style\Position;
use Symfony\Component\TuiHtml\Style\PositionMode;
use Symfony\Component\TuiHtml\Style\Style;
use Symfony\Component\TuiHtml\Style\TextAlign;
use Symfony\Component\TuiHtml\Widget\Text\ElementContentInterface;

/**
 * A closed list showing its choice on one line, like an HTML <select>.
 *
 * Enter or Space on the field opens the list below it, over what follows;
 * Enter in the list takes the selected item and closes it, Escape closes it
 * as it was, and so does the focus going elsewhere.
 *
 * @experimental
 */
class DropdownWidget extends ContainerWidget implements NativeAppearanceInterface, ElementContentInterface
{
    /** The marker after the choice, that says the field opens */
    public const MARKER = ' ▾';
    /** The icon of the marker, styled by the picker-icon sub-element, null for the default one */
    /** @var array{int, int, int} The left (or right) and bottom border of the select that the list is placed past, and whether on its right */
    private array $listBorder = [0, 0, 0];
    private ?string $pickerIcon = null;
    /** @var list<RichTextWidget> The content of the options, styled like <option>s, when they hold elements */
    private array $contents = [];
    private readonly ButtonWidget $field;
    private bool $open = false;
    private int $openedAt = 0;
    /** The item taken: the field shows it, not the one the open list is on */
    private int $chosen;
    private bool $native = true;

    public function __construct(private readonly SelectListWidget $list)
    {
        $this->chosen = $list->getSelectedIndex();
        $this->field = new ButtonWidget();
        $this->updateField();
        $this->field->on(PressEvent::class, function (): void {
            $this->setOpen(true);
        });
        // Down opens it too, like Alt+Down in a browser
        $opening = new Keybindings(['open' => [Key::DOWN, 'alt+down']]);
        $this->field->onInput(function (string $data) use ($opening): bool {
            if (!$opening->matches($data, 'open') || $this->field->hasState('disabled')) {
                return false;
            }
            $this->setOpen(true);

            return true;
        });
        // Listeners of the choice listen to the list, see getList()
        $this->list->onSelect(function (): void {
            $this->chosen = $this->list->getSelectedIndex();
            $this->setOpen(false);
        });
        $this->list->onCancel(function (): void {
            $this->list->setSelectedIndex($this->openedAt);
            $this->setOpen(false);
        });
        StyleBridge::set($this->list, new Style(null, null, null, null, null, null, null, null, null, null, null, null, true, null, null, null, $this->listColumns(2), null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, new Position(PositionMode::Absolute, null, null, null, 0, 100, ['top' => 100])));
        StyleBridge::set($this, new Style(null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, new Position(PositionMode::Relative)));
        $this->add($this->field);
        $this->add($this->list);
    }

    /**
     * Shows the options with styled content, like the elements of <option>s in a customizable select: each content,
     * in the order of the items, is styled like an <option> and drawn in the list, the chosen one in the field like
     * <selectedcontent>.
     *
     * @param list<RichTextWidget> $contents
     *
     * @return $this
     */
    public function setOptionContents(array $contents): static
    {
        foreach ($this->contents as $content) {
            $this->remove($content);
        }
        $this->contents = $contents;
        foreach ($contents as $content) {
            // Not drawn itself: its text is the label of its item
            StyleBridge::set($content, (StyleBridge::get($content) ?? new Style())->withHidden(true));
            $this->add($content);
        }
        $this->invalidate();

        return $this;
    }

    public function getField(): ButtonWidget
    {
        return $this->field;
    }

    public function getList(): SelectListWidget
    {
        return $this->list;
    }

    public function isOpen(): bool
    {
        return $this->open;
    }

    /**
     * Opens the list below the field, the focus in it, or closes it, the focus back on the field.
     *
     * @return $this
     */
    public function setOpen(bool $open): static
    {
        if ($open === $this->open) {
            return $this;
        }
        $this->open = $open;
        if ($open) {
            $this->openedAt = $this->list->getSelectedIndex();
        }
        StyleBridge::set($this->list, (StyleBridge::get($this->list) ?? new Style())->withHidden(!$open));
        $this->setState('open', $open);
        $this->updateField();
        $focus = $this->getContext()?->getFocusManager();
        if ($open) {
            $focus?->setFocus($this->list);
        } elseif ($this->list->isFocused()) {
            $focus?->setFocus($this->field);
        }

        return $this;
    }

    public function beforeRender(): void
    {
        // The labels of the options with content: their text, styled, over the colors and text attributes of the option
        if ($this->contents) {
            $context = $this->getContext();
            $this->list->setLabels(array_map(static fn (RichTextWidget $content) => trim(null !== $context ? $content->getTextOver(StyleBridge::resolve($context, $content)) : $content->getText()), $this->contents));
        }
        // As wide as its items with the border and padding its style gives it, like ::picker(select) { padding: 0 2ch }
        if (null !== $resolved = StyleBridge::resolve($this->getContext(), $this->list)) {
            $chrome = ($resolved->getBorder()->left ?? 0) + ($resolved->getBorder()->right ?? 0) + ($resolved->getPadding()->left ?? 0) + ($resolved->getPadding()->right ?? 0);
            if (($style = StyleBridge::get($this->list) ?? new Style())->getMaxColumns() !== $columns = $this->listColumns($chrome)) {
                StyleBridge::set($this->list, $style->withMaxColumns($columns));
            }
        }
        // Below the box of the select and flush with it, past the border the page gives it, like the picker of browsers
        // On its right when its text is, like a select of a right-to-left page
        if (null !== $own = StyleBridge::resolve($this->getContext(), $this)) {
            $right = TextAlign::Right === (StyleBridge::resolve($this->field->getContext(), $this->field)?->getTextAlign() ?? $own->getTextAlign());
            $border = [$right ? $own->getBorder()->right ?? 0 : $own->getBorder()->left ?? 0, $own->getBorder()->bottom ?? 0, $right ? 1 : 0];
            if ($border !== $this->listBorder) {
                $this->listBorder = $border;
                StyleBridge::set($this->list, (StyleBridge::get($this->list) ?? new Style())->withPosition(new Position(PositionMode::Absolute, null, $right ? -$border[0] : null, null, $right ? null : -$border[0], 100, ['top' => new PercentLength(true, [[100, $border[1]]])])));
            }
        }
        // The focus went elsewhere: it closes, like a select losing the focus
        if ($this->open && !$this->list->isFocused() && null !== $this->getContext()) {
            $this->open = false;
            StyleBridge::set($this->list, (StyleBridge::get($this->list) ?? new Style())->withHidden(true));
            $this->setState('open', false);
        }
        $this->updateField();
        parent::beforeRender();
    }

    /**
     * Draws the marker that says the field opens, or not, like CSS appearance: none.
     */
    public function setNativeAppearance(bool $native): void
    {
        if ($native !== $this->native) {
            $this->native = $native;
            $this->updateField();
        }
    }

    public function getContentElements(): array
    {
        return ['picker-icon', 'checkmark'];
    }

    public function setElementContent(string $element, ?string $content): void
    {
        if ('picker-icon' === $element && $content !== $this->pickerIcon) {
            $this->pickerIcon = $content;
            $this->updateField();
        }
        // The checkmark of its list, like select::checkmark on the select
        if ('checkmark' === $element) {
            $style = null !== $this->getContext() ? StyleBridge::element($this, 'checkmark') : null;
            $this->list->setOuterCheckmark($content, null !== $style && 'x' !== $style->apply('x') ? $style : null);
        }
    }

    /**
     * Shows the choice in the field, and the marker that says it opens: the picker-icon sub-element, its content and
     * its style, like select::picker-icon.
     */
    private function updateField(): void
    {
        $this->field->setText($this->list->getDisplayLabel($this->open ? $this->chosen : $this->list->getSelectedIndex()) ?? '', [] !== $this->contents);
        $icon = $this->native ? $this->pickerIcon ?? substr(self::MARKER, 1) : '';
        $this->field->setIcon($icon, '' !== $icon && null !== $this->getContext() ? StyleBridge::element($this, 'picker-icon') : null);
    }

    /**
     * The columns of the list: its longest item, with the marker of the selected one and the room the list keeps after
     * it, and its chrome.
     */
    private function listColumns(int $chrome): int
    {
        $widest = max(1, ...array_map(fn (int $index) => AnsiUtils::visibleWidth($this->list->getDisplayLabel($index) ?? ''), array_keys($this->list->getItems() ?: [0 => []])));

        return $widest + 4 + $chrome;
    }
}
