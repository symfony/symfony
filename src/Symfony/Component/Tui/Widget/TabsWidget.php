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
use Symfony\Component\Tui\Event\TabChangeEvent;
use Symfony\Component\Tui\Focus\FocusManager;
use Symfony\Component\Tui\Input\Key;
use Symfony\Component\Tui\Input\Keybindings;
use Symfony\Component\Tui\Render\RenderContext;
use Symfony\Component\Tui\Style\Direction;

/**
 * Tab container widget with horizontal/vertical headers.
 *
 * Only the active tab content is attached to the widget tree; inactive tabs
 * are detached so they are fully disabled (focus, input hit-testing).
 *
 * @experimental
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class TabsWidget extends AbstractWidget implements FocusableInterface, WidgetContainerInterface
{
    use FocusableTrait;
    use KeybindingsTrait;

    /** @var list<TabItem> */
    private array $tabs;

    private int $activeIndex = 0;

    private (FocusableInterface&AbstractWidget)|null $activeFocusableContent = null;

    private bool $focusContentOnSwitch = false;

    /**
     * @param list<TabItem> $tabs
     */
    public function __construct(
        array $tabs = [],
        private Direction $headerDirection = Direction::Horizontal,
        ?Keybindings $keybindings = null,
    ) {
        $this->tabs = $tabs;

        if ($this->tabs) {
            $this->attachChild($this->tabs[0]->getContent());
        }

        if (null !== $keybindings) {
            $this->setKeybindings($keybindings);
        }
    }

    /**
     * Add a child widget as a new tab.
     *
     * The tab ID is taken from the widget's ID and the tab label from the
     * widget's label; both fall back to a generated "tab_N" identifier.
     *
     * @return $this
     */
    public function add(AbstractWidget $widget): static
    {
        $id = $widget->getId();
        if (null === $id) {
            $existingIds = array_map(static fn (TabItem $tab) => $tab->getId(), $this->tabs);
            $n = \count($this->tabs);
            while (\in_array($id = 'tab_'.$n, $existingIds, true)) {
                ++$n;
            }
        }
        $label = $widget->getLabel() ?? $id;

        $isFirst = !$this->tabs;
        $this->tabs[] = new TabItem($id, $label, $widget);

        if ($isFirst) {
            $this->attachChild($widget);
            $this->activeFocusableContent = $this->findFirstFocusable($widget);
        }

        $this->invalidate();

        return $this;
    }

    /**
     * @return $this
     */
    public function remove(AbstractWidget $widget): static
    {
        foreach ($this->tabs as $i => $tab) {
            if ($tab->getContent() !== $widget) {
                continue;
            }

            $wasActive = $i === $this->activeIndex;
            $focusManager = $this->getContext()?->getFocusManager();
            $focusWasInside = $wasActive && null !== $focusManager && $this->isWidgetContainedIn($focusManager->getFocus(), $widget);

            if ($wasActive) {
                $this->detachChild($widget);
            }

            array_splice($this->tabs, $i, 1);

            if (!$wasActive && $i < $this->activeIndex) {
                --$this->activeIndex;
            } elseif ($this->activeIndex >= \count($this->tabs)) {
                $this->activeIndex = max(0, \count($this->tabs) - 1);
            }

            if ($wasActive && $this->tabs) {
                $newActive = $this->tabs[$this->activeIndex]->getContent();
                $this->attachChild($newActive);
                $this->activeFocusableContent = $this->findFirstFocusable($newActive);
            } elseif (!$this->tabs) {
                $this->activeFocusableContent = null;
            }

            if ($focusWasInside) {
                $this->refocusAfterSwitch($focusManager);
            }

            if ($wasActive && $this->tabs) {
                $this->dispatch(new TabChangeEvent(
                    $this,
                    $i,
                    $this->activeIndex,
                    $tab->getId(),
                    $this->tabs[$this->activeIndex]->getId(),
                ));
            }

            $this->invalidate();
            break;
        }

        return $this;
    }

    /**
     * @return $this
     */
    public function clear(): static
    {
        $focusManager = null;
        $focusWasInside = false;

        if ($this->tabs) {
            $activeContent = $this->tabs[$this->activeIndex]->getContent();
            $focusManager = $this->getContext()?->getFocusManager();
            $focusWasInside = null !== $focusManager && $this->isWidgetContainedIn($focusManager->getFocus(), $activeContent);

            $this->detachChild($activeContent);
        }

        $this->tabs = [];
        $this->activeIndex = 0;
        $this->activeFocusableContent = null;

        if ($focusWasInside) {
            $this->refocusAfterSwitch($focusManager);
        }

        $this->invalidate();

        return $this;
    }

    /**
     * @return $this
     */
    public function setHeaderDirection(Direction $headerDirection): static
    {
        if ($this->headerDirection !== $headerDirection) {
            $this->headerDirection = $headerDirection;
            $this->invalidate();
        }

        return $this;
    }

    /**
     * @return $this
     */
    public function setFocusContentOnSwitch(bool $focusContentOnSwitch): static
    {
        if ($this->focusContentOnSwitch !== $focusContentOnSwitch) {
            $this->focusContentOnSwitch = $focusContentOnSwitch;
            $this->invalidate();
        }

        return $this;
    }

    public function getActiveTabIndex(): int
    {
        return $this->activeIndex;
    }

    public function getActiveTabId(): ?string
    {
        return ($this->tabs[$this->activeIndex] ?? null)?->getId();
    }

    /**
     * @return list<TabItem>
     */
    public function getTabs(): array
    {
        return $this->tabs;
    }

    /**
     * @return $this
     */
    public function setActiveTab(int $index): static
    {
        if (!$this->tabs) {
            return $this;
        }

        $index = max(0, min($index, \count($this->tabs) - 1));

        if ($index === $this->activeIndex) {
            return $this;
        }

        $previousIndex = $this->activeIndex;
        $previousTab = $this->tabs[$previousIndex];
        $previousContent = $previousTab->getContent();

        $context = $this->getContext();
        $focusManager = $context?->getFocusManager();
        $focusedBefore = $focusManager?->getFocus();

        $this->detachChild($previousContent);

        $this->activeIndex = $index;
        $activeContent = $this->tabs[$this->activeIndex]->getContent();

        $this->attachChild($activeContent);

        $this->activeFocusableContent = $this->findFirstFocusable($activeContent);

        if (null !== $focusManager && $this->isWidgetContainedIn($focusedBefore, $previousContent)) {
            $this->refocusAfterSwitch($focusManager);
        }

        $this->dispatch(new TabChangeEvent(
            $this,
            $previousIndex,
            $this->activeIndex,
            $previousTab->getId(),
            $this->tabs[$this->activeIndex]->getId(),
        ));

        $this->invalidate();

        return $this;
    }

    /**
     * @param callable(TabChangeEvent): void $callback
     *
     * @return $this
     */
    public function onTabChange(callable $callback): static
    {
        return $this->on(TabChangeEvent::class, $callback);
    }

    public function all(): array
    {
        if (!$this->tabs) {
            return [];
        }

        return [$this->tabs[$this->activeIndex]->getContent()];
    }

    public function handleInput(string $data): void
    {
        if (null !== $this->onInput && ($this->onInput)($data)) {
            return;
        }

        if (!$count = \count($this->tabs)) {
            return;
        }

        $kb = $this->getKeybindings();

        if ($kb->matches($data, 'tab_next')) {
            $this->setActiveTab(($this->activeIndex + 1) % $count);

            return;
        }

        if ($kb->matches($data, 'tab_previous')) {
            $this->setActiveTab(($this->activeIndex - 1 + $count) % $count);

            return;
        }

        if (Direction::Horizontal === $this->headerDirection) {
            if ($kb->matches($data, 'cursor_left')) {
                $this->setActiveTab(($this->activeIndex - 1 + $count) % $count);

                return;
            }

            if ($kb->matches($data, 'cursor_right')) {
                $this->setActiveTab(($this->activeIndex + 1) % $count);

                return;
            }
        } else {
            if ($kb->matches($data, 'select_up')) {
                $this->setActiveTab(($this->activeIndex - 1 + $count) % $count);

                return;
            }

            if ($kb->matches($data, 'select_down')) {
                $this->setActiveTab(($this->activeIndex + 1) % $count);

                return;
            }
        }

        if ($kb->matches($data, 'select_confirm') && null !== $this->activeFocusableContent) {
            $this->getContext()?->getFocusManager()->setFocus($this->activeFocusableContent);
        }
    }

    /**
     * @return string[]
     */
    public function render(RenderContext $context): array
    {
        $columns = $context->getColumns();
        $rows = $context->getRows();

        if ($columns <= 0 || $rows <= 0 || !$this->tabs) {
            return [];
        }

        if (Direction::Horizontal === $this->headerDirection) {
            return $this->renderHorizontal($columns, $rows, $context);
        }

        return $this->renderVertical($columns, $rows, $context);
    }

    /**
     * @return array<string, string[]>
     */
    protected static function getDefaultKeybindings(): array
    {
        return [
            'tab_next' => [Key::TAB],
            'tab_previous' => ['shift+tab'],
            'select_up' => [Key::UP],
            'select_down' => [Key::DOWN],
            'cursor_left' => [Key::LEFT],
            'cursor_right' => [Key::RIGHT],
            'select_confirm' => [Key::ENTER],
        ];
    }

    protected function onAttach(WidgetContext $context): void
    {
        if (!$this->tabs) {
            return;
        }

        $activeContent = $this->tabs[$this->activeIndex]->getContent();
        $this->activeFocusableContent = $this->findFirstFocusable($activeContent);
    }

    protected function onDetach(): void
    {
        $this->activeFocusableContent = null;
    }

    /**
     * @return string[]
     */
    private function renderHorizontal(int $columns, int $rows, RenderContext $context): array
    {
        $segments = [];
        foreach ($this->tabs as $i => $tab) {
            $segments[] = $this->buildHorizontalTabSegment($tab, $i === $this->activeIndex);
        }

        $start = $end = $this->activeIndex;
        $width = AnsiUtils::visibleWidth($segments[$this->activeIndex]['middle']);

        while ($start > 0) {
            $candidateWidth = AnsiUtils::visibleWidth($segments[$start - 1]['middle']);
            if ($width + $candidateWidth > $columns) {
                break;
            }
            --$start;
            $width += $candidateWidth;
        }

        while ($end + 1 < \count($segments)) {
            $candidateWidth = AnsiUtils::visibleWidth($segments[$end + 1]['middle']);
            if ($width + $candidateWidth > $columns) {
                break;
            }
            ++$end;
            $width += $candidateWidth;
        }

        $top = '';
        $middle = '';
        $bottom = '';
        for ($i = $start; $i <= $end; ++$i) {
            $top .= $segments[$i]['top'];
            $middle .= $segments[$i]['middle'];
            $bottom .= $segments[$i]['bottom'];
        }

        $top = $this->padLine(AnsiUtils::truncateToWidth($top, $columns, ''), $columns);
        $middle = $this->padLine(AnsiUtils::truncateToWidth($middle, $columns, ''), $columns);
        $bottom = $this->padLineWithFill(AnsiUtils::truncateToWidth($bottom, $columns, ''), $columns, '─');

        $contentRows = max(0, $rows - 3);
        $contentLines = $contentRows > 0 ? $this->renderActiveContent($context->withRows($contentRows)) : [];

        $lines = [$top, $middle, $bottom];
        foreach (\array_slice($contentLines, 0, $contentRows) as $line) {
            $lines[] = $this->padLine(AnsiUtils::truncateToWidth($line, $columns, ''), $columns);
        }

        return $lines;
    }

    /**
     * @return string[]
     */
    private function renderVertical(int $columns, int $rows, RenderContext $context): array
    {
        $headerWidth = $this->computeVerticalHeaderWidth($columns);
        $contentColumns = max(0, $columns - $headerWidth);
        $tabCount = \count($this->tabs);

        $visibleTabCount = min($tabCount, max(1, intdiv($rows, 3)));
        $startTab = max(0, min($this->activeIndex - $visibleTabCount + 1, $tabCount - $visibleTabCount));
        $visibleHeaderCount = $rows < 3 ? $rows : 3 * $visibleTabCount;

        // Compute the right-side character for each visible header row.
        // A continuous │ line runs on the right, broken only at the active label row.
        // Tab box borders (top/bottom) connect to this line with junction characters,
        // just like horizontal tabs connect their side borders to the bottom ─ line.
        $activeLabelRow = 3 * $this->activeIndex + 1;
        $firstRow = 3 * $startTab;
        $lastRow = $firstRow + $visibleHeaderCount - 1;
        $rightChars = $this->computeVerticalRightChars($activeLabelRow, $firstRow, $lastRow);

        // Each tab is a 3-row box: top border, label, bottom border.
        $headerLines = [];

        for ($r = $firstRow; $r <= $lastRow; ++$r) {
            $i = intdiv($r, 3);
            $headerLines[] = match ($r % 3) {
                0 => $this->buildVerticalHeaderBorderLine($headerWidth, '╭', $rightChars[$r]),
                1 => $this->buildVerticalHeaderLabelLine($this->tabs[$i], $i === $this->activeIndex, $headerWidth),
                2 => $this->buildVerticalHeaderBorderLine($headerWidth, '╰', $rightChars[$r]),
            };
        }
        $headerCount = \count($headerLines);
        $lastHeaderLine = array_pop($headerLines);
        if (null === $lastHeaderLine) {
            return [];
        }
        $firstHeaderLine = $headerLines[0] ?? $lastHeaderLine;

        // Reserve 2 rows for first/last full-width separators
        $innerRows = max(0, $rows - 2);
        $contentLines = $contentColumns > 0 && $innerRows > 0 ? $this->renderActiveContent($context->withSize($contentColumns, $innerRows)) : [];

        $firstLine = $this->padLineWithFill($firstHeaderLine, $columns, '─');
        $lastLine = $this->padLineWithFill($lastHeaderLine, $columns, '─');

        $innerCount = min($innerRows, max($headerCount - 2, \count($contentLines)));

        $lines = [$firstLine];
        for ($i = 0; $i < $innerCount; ++$i) {
            $left = $headerLines[$i + 1] ?? str_repeat(' ', $headerWidth);
            $right = $contentLines[$i] ?? '';
            $right = $this->padLine(AnsiUtils::truncateToWidth($right, $contentColumns, ''), $contentColumns);
            $lines[] = $left.$right;
        }

        if (\count($lines) < $rows) {
            $lines[] = $lastLine;
        }

        return $lines;
    }

    /**
     * Compute the right-side character for each visible header row,
     * keyed by absolute row number.
     *
     * A continuous │ line runs on the right edge of the tab header, broken
     * at the active tab's label row. Border rows (top/bottom of each tab box)
     * connect to this line with junction characters:
     *  - ┬ top separator with line going down
     *  - ┴ bottom separator with line going up
     *  - ┤ border crossing a continuous line
     *  - ┘ line ending (above active opening)
     *  - ┐ line starting (below active opening)
     *  - ─ separator continuation when the active tab sits at the edge
     *
     * @return array<int, string>
     */
    private function computeVerticalRightChars(int $activeLabelRow, int $firstRow, int $lastRow): array
    {
        $chars = [];

        for ($r = $firstRow; $r <= $lastRow; ++$r) {
            if ($r === $activeLabelRow) {
                $chars[$r] = ' ';

                continue;
            }

            if (1 === $r % 3) {
                // Inactive label row
                $chars[$r] = '│';

                continue;
            }

            // Border row: determine vertical line connections
            $connectUp = $r > $firstRow && ($r - 1) !== $activeLabelRow;
            $connectDown = $r < $lastRow && ($r + 1) !== $activeLabelRow;
            $isSeparator = $r === $firstRow || $r === $lastRow;

            if ($isSeparator) {
                // First/last visible row: also connects right (─ fill)
                if ($connectDown) {
                    $chars[$r] = '┬';
                } elseif ($connectUp) {
                    $chars[$r] = '┴';
                } else {
                    // Active tab at edge: no vertical connection, line just continues
                    $chars[$r] = '─';
                }
            } else {
                // Inner border row: the rows around the active label row can
                // never both miss, so one of the three junctions always applies
                if ($connectUp && $connectDown) {
                    $chars[$r] = '┤';
                } elseif ($connectUp) {
                    $chars[$r] = '┘';
                } else {
                    $chars[$r] = '┐';
                }
            }
        }

        return $chars;
    }

    /**
     * @return string[]
     */
    private function renderActiveContent(RenderContext $context): array
    {
        $content = $this->tabs[$this->activeIndex]->getContent();

        return $this->getContext()?->renderWidget($content, $context) ?? [];
    }

    /**
     * @return array{top: string, middle: string, bottom: string}
     */
    private function buildHorizontalTabSegment(TabItem $tab, bool $active): array
    {
        $label = ' '.$tab->getLabel().' ';
        $contentWidth = max(1, AnsiUtils::visibleWidth($label));

        $top = $this->applyElement('separator', '╭').$this->applyElement('separator', str_repeat('─', $contentWidth)).$this->applyElement('separator', '╮');
        $middleLabel = $this->padLine(AnsiUtils::truncateToWidth($active ? $this->applyElement('tab-active', $label) : $this->applyElement('tab', $label), $contentWidth, ''), $contentWidth);
        $middle = $this->applyElement('separator', '│').$middleLabel.$this->applyElement('separator', '│');

        if ($active) {
            $bottom = $this->applyElement('separator', '┘').$this->applyElement('separator', str_repeat(' ', $contentWidth)).$this->applyElement('separator', '└');
        } else {
            $bottom = $this->applyElement('separator', '┴').$this->applyElement('separator', str_repeat('─', $contentWidth)).$this->applyElement('separator', '┴');
        }

        return [
            'top' => $top,
            'middle' => $middle,
            'bottom' => $bottom,
        ];
    }

    private function buildVerticalHeaderBorderLine(int $width, string $leftChar, string $rightChar): string
    {
        if ($width <= 0) {
            return '';
        }

        if (1 === $width) {
            return $this->applyElement('separator', $leftChar);
        }

        return $this->applyElement('separator', $leftChar).$this->applyElement('separator', str_repeat('─', $width - 2)).$this->applyElement('separator', $rightChar);
    }

    private function buildVerticalHeaderLabelLine(TabItem $tab, bool $active, int $width): string
    {
        if ($width <= 0) {
            return '';
        }

        if (1 === $width) {
            return $this->applyElement('separator', '│');
        }

        $innerWidth = $width - 2;
        $left = $this->applyElement('separator', '│');
        $right = $active ? ' ' : $this->applyElement('separator', '│');
        if (0 === $innerWidth) {
            return $left.$right;
        }

        $label = ' '.$tab->getLabel().' ';
        $styled = $active ? $this->applyElement('tab-active', $label) : $this->applyElement('tab', $label);
        $inner = $this->padLine(AnsiUtils::truncateToWidth($styled, $innerWidth, ''), $innerWidth);

        return $left.$inner.$right;
    }

    private function computeVerticalHeaderWidth(int $columns): int
    {
        $maxInner = 1;
        foreach ($this->tabs as $tab) {
            $maxInner = max($maxInner, AnsiUtils::visibleWidth(' '.$tab->getLabel().' '));
        }

        $maxAllowed = max(1, min($columns, max(3, (int) floor($columns * 0.4))));

        return min($maxInner + 2, $maxAllowed);
    }

    private function refocusAfterSwitch(FocusManager $focusManager): void
    {
        if ($this->focusContentOnSwitch && null !== $this->activeFocusableContent) {
            $focusManager->setFocus($this->activeFocusableContent);
        } else {
            $focusManager->setFocus($this);
        }
    }

    private function findFirstFocusable(AbstractWidget $widget): (FocusableInterface&AbstractWidget)|null
    {
        if ($widget instanceof FocusableInterface) {
            return $widget;
        }

        if ($widget instanceof ParentInterface) {
            foreach ($widget->all() as $child) {
                $found = $this->findFirstFocusable($child);
                if (null !== $found) {
                    return $found;
                }
            }
        }

        return null;
    }

    private function isWidgetContainedIn(?AbstractWidget $needle, AbstractWidget $haystack): bool
    {
        if (null === $needle) {
            return false;
        }

        if ($needle === $haystack) {
            return true;
        }

        if ($haystack instanceof ParentInterface) {
            foreach ($haystack->all() as $child) {
                if ($this->isWidgetContainedIn($needle, $child)) {
                    return true;
                }
            }
        }

        return false;
    }

    private function padLine(string $line, int $width): string
    {
        return $line.str_repeat(' ', max(0, $width - AnsiUtils::visibleWidth($line)));
    }

    private function padLineWithFill(string $line, int $width, string $fill): string
    {
        $fillCount = max(0, $width - AnsiUtils::visibleWidth($line));
        if (0 === $fillCount) {
            return $line;
        }

        return $line.$this->applyElement('separator', str_repeat($fill, $fillCount));
    }
}
