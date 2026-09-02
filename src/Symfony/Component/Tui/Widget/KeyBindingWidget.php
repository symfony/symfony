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
use Symfony\Component\Tui\Event\FocusEvent;
use Symfony\Component\Tui\Input\Key;
use Symfony\Component\Tui\Input\Keybindings;
use Symfony\Component\Tui\Render\RenderContext;

/**
 * Displays the keybindings of the currently focused widget.
 *
 * Renders a styled key badge followed by the action label per keybinding.
 * Updates automatically when focus changes or when the bindings or labels
 * of the focused widget change.
 *
 * Styleable sub-elements via stylesheet:
 * - ::key: the key badge for focused-widget bindings
 * - ::action: the action label for focused-widget bindings
 * - ::global-key: the key badge for global bindings
 * - ::global-action: the action label for global bindings
 *
 * @experimental
 *
 * @author Sylvain Blondeau <contact@sylvainblondeau.dev>
 */
class KeyBindingWidget extends AbstractWidget
{
    /** @var list<array{label: string, keys: string[]}> */
    private array $currentItems = [];

    /** @var list<array{label: string, keys: string[]}> */
    private array $globalItems = [];

    /** @var array<string, string> */
    private array $keyLabels = [];

    private ?\Closure $focusListener = null;
    private int $gap = 2;
    private string $separator = ' │ ';

    public function setGap(int $gap): static
    {
        if ($this->gap !== $gap) {
            $this->gap = max(0, $gap);
            $this->invalidate();
        }

        return $this;
    }

    public function setSeparator(string $separator): static
    {
        if ($this->separator !== $separator) {
            $this->separator = $separator;
            $this->invalidate();
        }

        return $this;
    }

    /**
     * Override the display label of individual keys, e.g. to use
     * platform-specific symbols or plain text.
     *
     * @param array<string, string>|null $labels map of key identifier => display string; null reverts to the defaults from Key::label()
     *
     * @return $this
     */
    public function setKeyLabels(?array $labels): static
    {
        $labels ??= [];

        if ($this->keyLabels !== $labels) {
            $this->keyLabels = $labels;
            $this->invalidate();
        }

        return $this;
    }

    /**
     * @param array<string, string>|null $labels map of action => display label; null falls back to humanized action names
     *
     * @return $this
     */
    public function setGlobalKeybindings(Keybindings $bindings, ?array $labels = null): static
    {
        $items = self::buildItems($bindings->all(), $labels);

        if ($this->globalItems !== $items) {
            $this->globalItems = $items;
            $this->invalidate();
        }

        return $this;
    }

    public function beforeRender(): void
    {
        $focused = $this->getContext()?->getFocusManager()->getFocus();

        if ($focused instanceof FocusableInterface) {
            $this->updateBindings($focused);
        }
    }

    /**
     * @return string[]
     */
    public function render(RenderContext $context): array
    {
        $columns = $context->getColumns();
        $gapStr = str_repeat(' ', $this->gap);

        // Right group (global), anchored to the end of the last line
        $rightParts = [];
        $rightWidth = 0;
        $separatorWidth = AnsiUtils::visibleWidth($this->separator);
        foreach ($this->globalItems as ['label' => $label, 'keys' => $keys]) {
            $badge = ' '.$this->keyLabel($keys[0]).' ';
            $width = AnsiUtils::visibleWidth($badge) + 1 + AnsiUtils::visibleWidth($label) + ($rightParts ? $this->gap : 0);
            $rightParts[] = $this->applyElement('global-key', $badge).' '.$this->applyElement('global-action', $label);
            $rightWidth += $width;
        }
        $right = $rightParts ? implode($gapStr, $rightParts) : '';
        if ($right) {
            $rightWidth += $separatorWidth;
        }

        // Left group (focused widget) wraps onto multiple lines;
        // the last line must leave room for globals
        $lines = [];
        $lineParts = [];
        $lineWidth = 0;

        foreach ($this->currentItems as ['label' => $label, 'keys' => $keys]) {
            $badge = ' '.$this->keyLabel($keys[0]).' ';
            $itemWidth = AnsiUtils::visibleWidth($badge) + 1 + AnsiUtils::visibleWidth($label);
            $width = $itemWidth + ($lineParts ? $this->gap : 0);

            if ($lineParts && $lineWidth + $width > $columns) {
                $lines[] = ['content' => implode($gapStr, $lineParts), 'width' => $lineWidth];
                $lineParts = [];
                $lineWidth = 0;
                $width = $itemWidth;
            }

            $lineParts[] = $this->applyElement('key', $badge).' '.$this->applyElement('action', $label);
            $lineWidth += $width;
        }

        if ($lineParts) {
            $lines[] = ['content' => implode($gapStr, $lineParts), 'width' => $lineWidth];
        }

        if (!$lines && !$right) {
            return [];
        }

        if (!$lines) {
            $rightWidth -= $separatorWidth;

            return [self::clamp(str_repeat(' ', max(0, $columns - $rightWidth)).$right, max($rightWidth, $columns), $columns)];
        }

        // Build result: all lines except the last are full-width left content
        $result = [];
        foreach (\array_slice($lines, 0, -1) as ['content' => $content, 'width' => $width]) {
            $result[] = self::clamp($content, $width, $columns);
        }

        // Last line: combine left remainder with globals (flex space between)
        ['content' => $lastContent, 'width' => $lastWidth] = end($lines);

        if (!$right) {
            $result[] = self::clamp($lastContent, $lastWidth, $columns);
        } elseif ($lastWidth + $rightWidth <= $columns) {
            $result[] = $lastContent.str_repeat(' ', $columns - $lastWidth - $rightWidth).$this->separator.$right;
        } else {
            $result[] = self::clamp($lastContent, $lastWidth, $columns);
            $result[] = self::clamp(str_repeat(' ', max(0, $columns - $rightWidth)).$this->separator.$right, max($rightWidth, $columns), $columns);
        }

        return $result;
    }

    protected function onAttach(WidgetContext $context): void
    {
        $this->focusListener = function (FocusEvent $event): void {
            $this->updateBindings($event->getTarget());
        };
        $context->getEventDispatcher()->addListener(FocusEvent::class, $this->focusListener);

        $focused = $context->getFocusManager()->getFocus();
        if ($focused instanceof FocusableInterface) {
            $this->updateBindings($focused);
        }
    }

    protected function onDetach(): void
    {
        if (null !== $this->focusListener) {
            $this->getContext()?->getEventDispatcher()
                ->removeListener(FocusEvent::class, $this->focusListener);
            $this->focusListener = null;
        }
    }

    /**
     * @param array<string, string[]>    $bindings
     * @param array<string, string>|null $labels
     *
     * @return list<array{label: string, keys: string[]}>
     */
    private static function buildItems(array $bindings, ?array $labels): array
    {
        $items = [];

        if (null !== $labels) {
            foreach ($labels as $action => $label) {
                if ($bindings[$action] ?? false) {
                    $items[] = ['label' => $label, 'keys' => $bindings[$action]];
                }
            }
        } else {
            foreach ($bindings as $action => $keys) {
                if ($keys) {
                    $items[] = ['label' => ucwords(str_replace('_', ' ', $action)), 'keys' => $keys];
                }
            }
        }

        return $items;
    }

    private static function clamp(string $line, int $width, int $columns): string
    {
        return $width > $columns ? AnsiUtils::truncateToWidth($line, $columns) : $line;
    }

    private function keyLabel(string $key): string
    {
        return $this->keyLabels[$key] ?? Key::label($key);
    }

    private function updateBindings(AbstractWidget&FocusableInterface $target): void
    {
        $items = self::buildItems($target->getKeybindings()->all(), $target->getKeybindingLabels() ?: null);

        if ($this->currentItems !== $items) {
            $this->currentItems = $items;
            $this->invalidate();
        }
    }
}
