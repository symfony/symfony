<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Debug;

use Symfony\Component\Tui\Ansi\AnsiUtils;
use Symfony\Component\Tui\Event\InputEvent;
use Symfony\Component\Tui\Event\SelectionChangeEvent;
use Symfony\Component\Tui\Event\TickEvent;
use Symfony\Component\Tui\Input\Key;
use Symfony\Component\Tui\Input\Keybindings;
use Symfony\Component\Tui\Style\Border;
use Symfony\Component\Tui\Style\Direction;
use Symfony\Component\Tui\Style\Padding;
use Symfony\Component\Tui\Style\Style;
use Symfony\Component\Tui\Style\VerticalAlign;
use Symfony\Component\Tui\Tui;
use Symfony\Component\Tui\Widget\ContainerWidget;
use Symfony\Component\Tui\Widget\TextWidget;

/**
 * The full-screen Tui application backing the "debug" command.
 *
 * Interaction model:
 *   - typing anything updates the search and refreshes the list in real time;
 *   - left/right arrows switch the active tab (section);
 *   - up/down arrows move the selection in the list (the detail follows);
 *   - PgUp/PgDown paginates the detail panel contents
 *   - Esc clears the search query (or exits when it is already empty), Ctrl+C exits.
 *
 * Keystrokes are handled globally: text edits the search query, while arrows
 * drive tab/list navigation.
 *
 * @author Javier Eguiluz <javier.eguiluz@gmail.com>
 *
 * @experimental
 */
final class DebugApplication
{
    private const array LOADING_FRAMES = ['⠋', '⠙', '⠹', '⠸', '⠼', '⠴', '⠦', '⠧', '⠇', '⠏'];
    private const float LOADING_FRAME_INTERVAL = 0.08;
    private const string DEFAULT_SECTION_LABEL = 'Container';

    private Tui $tui;
    private Keybindings $keybindings;

    private TextWidget $topBar;
    private TextWidget $footer;
    private TextWidget $sidebarHeader;
    private DebugResultListWidget $list;
    private DebugDetailWidget $detail;
    private SpacerWidget $sidebarFiller;
    private int $panelInnerRows;
    private int $listMaxVisible;
    private int $terminalRows;
    private int $terminalColumns;
    private int $sidebarLineCount = 0;
    private bool $sidebarHasHeader = true;

    private int $activeIndex = 0;
    private string $query = '';
    private int $refreshGeneration = 0;
    private int $detailGeneration = 0;
    private int $loadingFrame = 0;
    private float $loadingFrameElapsed = 0.0;

    private ?DebugAsyncTask $listTask = null;
    private ?DebugAsyncTask $detailTask = null;

    /** @var array<int, list<DebugItem>> */
    private array $listCache = [];

    /** @var array<string, string> */
    private array $detailCache = [];

    /** @var array<string, DebugItem> */
    private array $itemsByKey = [];

    /**
     * @param list<DebugSectionInterface> $sections
     * @param \Closure(Keybindings): Tui  $tuiFactory Builds the Tui (lets the command inject a terminal in tests)
     */
    public function __construct(
        private readonly array $sections,
        \Closure $tuiFactory,
    ) {
        $this->keybindings = new Keybindings([
            'debug_exit' => ['ctrl+c'],
            'debug_tab_prev' => [Key::LEFT],
            'debug_tab_next' => [Key::RIGHT],
            'debug_list_up' => [Key::UP],
            'debug_list_down' => [Key::DOWN],
            'debug_detail_page_up' => [Key::PAGE_UP],
            'debug_detail_page_down' => [Key::PAGE_DOWN],
            'debug_detail_home' => [Key::HOME],
            'debug_detail_end' => [Key::END],
            'debug_search_backspace' => [Key::BACKSPACE],
            'debug_search_clear' => [Key::ESCAPE],
        ]);

        $this->tui = ($tuiFactory)($this->keybindings);
        $this->activeIndex = $this->getDefaultSectionIndex();

        $this->build();
    }

    public function run(): void
    {
        $terminal = $this->tui->getTerminal();

        // run this as a real full-screen UI. Rendering into the normal scrollback
        // can push the first line just above the viewport when the frame uses the
        // full terminal height
        $terminal->write("\x1b[?1049h\x1b[2J\x1b[H");

        try {
            $this->tui->requestRender(true);
            $this->tui->run();
        } finally {
            $this->cancelListTask();
            $this->cancelDetailTask();
            $terminal->write("\x1b[?1049l");
        }
    }

    private function build(): void
    {
        $this->topBar = new TextWidget('', true);

        $this->sidebarHeader = new TextWidget('', true);

        $this->terminalRows = $this->tui->getTerminal()->getRows();
        $this->terminalColumns = $this->tui->getTerminal()->getColumns();
        $this->panelInnerRows = max(1, $this->terminalRows - 5);
        $this->listMaxVisible = max(1, $this->terminalRows - 8);

        $this->list = new DebugResultListWidget($this->listMaxVisible);
        $this->sidebarFiller = new SpacerWidget();

        $sidebar = new ContainerWidget();
        $sidebar->expandVertically(true);
        $sidebar->setStyle(new Style(border: Border::all(1, color: 'gray'), padding: Padding::from([0, 1]), verticalAlign: VerticalAlign::Top, flex: 1));
        $sidebar->add($this->sidebarHeader);
        $sidebar->add($this->list);
        $sidebar->add($this->sidebarFiller);

        $this->detail = new DebugDetailWidget();
        $this->detail->expandVertically(true);

        $detailBox = new ContainerWidget();
        $detailBox->expandVertically(true);
        $detailBox->setStyle(new Style(border: Border::all(1, color: 'gray'), padding: Padding::from([0, 1]), verticalAlign: VerticalAlign::Top, flex: 3));
        $detailBox->add($this->detail);

        $mainRow = new ContainerWidget();
        $mainRow->expandVertically(true);
        $mainRow->setStyle(new Style(direction: Direction::Horizontal, gap: 1, verticalAlign: VerticalAlign::Top));
        $mainRow->add($sidebar);
        $mainRow->add($detailBox);

        $this->footer = new TextWidget("\e[2mType to search | <Esc> reset | ←/→ sections | ↑/↓ results | PgUp/PgDn detail | Ctrl+C quit\e[22m", true);

        $this->tui->add($this->topBar);
        $this->tui->add($mainRow);
        $this->tui->add($this->footer);

        $this->registerListeners();

        $this->topBar->setText($this->renderTopBar());
        $this->refreshList();
    }

    private function registerListeners(): void
    {
        $this->tui->addListener(function (InputEvent $event): void {
            $data = $event->getData();
            $kb = $this->keybindings;

            if ($kb->matches($data, 'debug_exit')) {
                $event->stopPropagation();
                $this->tui->stop();

                return;
            }

            if ($kb->matches($data, 'debug_tab_prev')) {
                $event->stopPropagation();
                $this->switchTab(-1);

                return;
            }

            if ($kb->matches($data, 'debug_tab_next')) {
                $event->stopPropagation();
                $this->switchTab(1);

                return;
            }

            if ($kb->matches($data, 'debug_list_up') || $kb->matches($data, 'debug_list_down')) {
                $event->stopPropagation();
                $this->list->handleInput($data);
                $this->tui->requestRender(true);

                return;
            }

            if ($kb->matches($data, 'debug_detail_page_up')) {
                $event->stopPropagation();
                $this->detail->pageUp();
                $this->tui->requestRender(true);

                return;
            }

            if ($kb->matches($data, 'debug_detail_page_down')) {
                $event->stopPropagation();
                $this->detail->pageDown();
                $this->tui->requestRender(true);

                return;
            }

            if ($kb->matches($data, 'debug_detail_home')) {
                $event->stopPropagation();
                $this->detail->scrollToTop();
                $this->tui->requestRender(true);

                return;
            }

            if ($kb->matches($data, 'debug_detail_end')) {
                $event->stopPropagation();
                $this->detail->scrollToBottom();
                $this->tui->requestRender(true);

                return;
            }

            if ($kb->matches($data, 'debug_search_backspace')) {
                $event->stopPropagation();
                $this->updateQuery($this->removeLastGrapheme($this->query));

                return;
            }

            if ($kb->matches($data, 'debug_search_clear')) {
                $event->stopPropagation();
                if ('' === $this->query) {
                    $this->tui->stop();

                    return;
                }

                $this->updateQuery('');

                return;
            }

            if (!$this->hasControlChars($data)) {
                $event->stopPropagation();
                $this->updateQuery($this->query.$data);
            }
        });

        $this->list->onSelectionChange(function (SelectionChangeEvent $event): void {
            $this->refreshDetail($event->getValue());
            $this->tui->requestRender(true);
        });

        $this->tui->onTick(function (TickEvent $event): bool {
            $terminal = $this->tui->getTerminal();
            if ($terminal->getRows() !== $this->terminalRows || $terminal->getColumns() !== $this->terminalColumns) {
                $this->handleResize();

                return true;
            }

            if (null !== $this->listTask) {
                $this->advanceLoadingFrame($event->getDeltaTime());
                $this->showLoadingList($this->sections[$this->activeIndex]);

                return true;
            }

            if (null !== $this->detailTask) {
                $this->advanceLoadingFrame($event->getDeltaTime());
                $this->showLoadingDetail();

                return true;
            }

            return false;
        });
    }

    /**
     * Adapts the layout to the new terminal dimensions (the panel sizing is
     * computed manually, so it is not refreshed by the Tui layout engine).
     */
    private function handleResize(): void
    {
        $terminal = $this->tui->getTerminal();
        $this->terminalRows = $terminal->getRows();
        $this->terminalColumns = $terminal->getColumns();
        $this->panelInnerRows = max(1, $this->terminalRows - 5);
        $this->listMaxVisible = max(1, $this->terminalRows - 8);

        $this->list->setMaxVisible($this->listMaxVisible);
        $this->topBar->setText($this->renderTopBar());
        $this->setSidebarFiller($this->sidebarLineCount, $this->sidebarHasHeader);
        // the detail is rendered for the panel width, so it must be recomputed
        // (the detail cache is keyed by width, making this correct and cheap)
        $this->refreshDetail($this->list->getSelectedItem()['value'] ?? null);
        $this->tui->requestRender(true);
    }

    private function switchTab(int $delta): void
    {
        $count = \count($this->sections);
        $this->activeIndex = (($this->activeIndex + $delta) % $count + $count) % $count;

        $this->topBar->setText($this->renderTopBar());
        $this->refreshList();
        $this->tui->requestRender(true);
    }

    private function getDefaultSectionIndex(): int
    {
        foreach ($this->sections as $i => $section) {
            if (self::DEFAULT_SECTION_LABEL === $section->getLabel()) {
                return $i;
            }
        }

        return 0;
    }

    private function updateQuery(string $query): void
    {
        $query = $this->stripControlBytes($this->sanitizeUtf8($query));

        if ($query === $this->query) {
            return;
        }

        $this->query = $query;
        $this->topBar->setText($this->renderTopBar());
        $this->refreshList();
    }

    private function refreshList(): void
    {
        $this->cancelListTask();
        $this->cancelDetailTask();

        $section = $this->sections[$this->activeIndex];
        $generation = ++$this->refreshGeneration;
        ++$this->detailGeneration;
        $query = $this->query;
        $sectionIndex = $this->activeIndex;

        // once the full item list of a section is cached, every keystroke filters
        // it synchronously in this process: no fork, no index rebuild
        if (isset($this->listCache[$sectionIndex])) {
            $this->renderList($section, '' === $query ? $this->listCache[$sectionIndex] : $section->filter($this->listCache[$sectionIndex], $query));

            return;
        }

        $this->showLoadingList($section);

        $this->listTask = DebugAsyncTask::run(
            static fn (): array => $section->search($query),
            function (mixed $result, ?string $error) use ($section, $generation, $query, $sectionIndex): void {
                $this->listTask = null;

                if ($generation !== $this->refreshGeneration) {
                    return;
                }

                if (null !== $error) {
                    $this->renderListError($error);

                    return;
                }

                if (!\is_array($result)) {
                    $this->renderListError('The debug task did not return a result list.');

                    return;
                }

                if ('' === $query) {
                    $this->listCache[$sectionIndex] = $result;
                }

                $this->renderList($section, $result);
            },
        );
    }

    /**
     * @param list<DebugItem> $items
     */
    private function renderList(DebugSectionInterface $section, array $items): void
    {
        $this->itemsByKey = [];
        $listItems = [];
        $groupCounts = [];

        foreach ($items as $item) {
            if (null !== $item->group) {
                $groupCounts[$item->group] = ($groupCounts[$item->group] ?? 0) + 1;
            }
        }
        $hasGroupHeaders = [] !== $groupCounts;

        $itemCount = 0;
        $previousGroup = null;
        foreach ($items as $i => $item) {
            $key = (string) $i;
            $this->itemsByKey[$key] = $item;
            ++$itemCount;

            if (null !== $item->group && $item->group !== $previousGroup) {
                $listItems[] = [
                    'header' => true,
                    'label' => \sprintf('%s (%d)', $this->stripControlBytes($item->group), $groupCounts[$item->group]),
                ];
                $previousGroup = $item->group;
            }

            $listItems[] = ['value' => $key, 'label' => $this->stripControlBytes($item->label)];
        }

        $this->list->setItems($listItems);
        $this->sidebarHeader->setText($hasGroupHeaders ? '' : \sprintf("\e[2m%s (%d)\e[22m", $section->getLabel(), $itemCount));
        $this->setSidebarFiller(\count($listItems), !$hasGroupHeaders);

        $this->refreshDetail($this->list->getSelectedItem()['value'] ?? null);
        $this->tui->requestRender(true);
    }

    private function renderListError(string $message): void
    {
        ++$this->detailGeneration;
        $this->itemsByKey = [];
        $this->list->setItems([]);
        $this->sidebarHeader->setText("\e[2mError\e[22m");
        $this->setSidebarFiller(0, true);
        $this->detail->setText($this->stripControlBytes($message));
        $this->tui->requestRender(true);
    }

    private function showLoadingList(DebugSectionInterface $section): void
    {
        $frame = $this->getLoadingFrame();
        $this->itemsByKey = [];
        $this->list->setItems([
            ['header' => true, 'label' => \sprintf('%s Loading %s...', $frame, $this->stripControlBytes($section->getLabel()))],
        ]);
        $this->sidebarHeader->setText('');
        $this->setSidebarFiller(1, false);
        $this->detail->setText('');
        $this->tui->requestRender(true);
    }

    private function cancelListTask(): void
    {
        if (null !== $this->listTask) {
            $this->listTask->cancel();
            $this->listTask = null;
        }
    }

    private function cancelDetailTask(): void
    {
        if (null !== $this->detailTask) {
            $this->detailTask->cancel();
            $this->detailTask = null;
        }
    }

    private function refreshDetail(?string $key): void
    {
        $this->cancelDetailTask();

        if (null === $key || !isset($this->itemsByKey[$key])) {
            ++$this->detailGeneration;
            $this->detail->setText('');

            return;
        }

        $generation = ++$this->detailGeneration;
        $section = $this->sections[$this->activeIndex];
        $item = $this->itemsByKey[$key];
        $width = max(20, $this->tui->getTerminal()->getColumns() - 4);
        $cacheKey = $this->getDetailCacheKey($this->activeIndex, $item, $width);

        if (isset($this->detailCache[$cacheKey])) {
            $this->renderDetail($this->detailCache[$cacheKey]);

            return;
        }

        if (null !== $item->detail) {
            $this->detailCache[$cacheKey] = $item->detail;
            $this->renderDetail($item->detail);

            return;
        }

        $this->showLoadingDetail();

        $this->detailTask = DebugAsyncTask::run(
            static fn (): string => $section->describe($item, $width),
            function (mixed $result, ?string $error) use ($generation, $cacheKey): void {
                $this->detailTask = null;

                if ($generation !== $this->detailGeneration) {
                    return;
                }

                if (null !== $error) {
                    $this->renderDetailError($error);

                    return;
                }

                $detail = (string) $result;
                $this->detailCache[$cacheKey] = $detail;
                $this->renderDetail($detail);
            },
        );
    }

    private function renderDetail(string $detail): void
    {
        // The detail keeps the ANSI produced by the console descriptors on purpose.
        $this->detail->setText($detail);

        $this->tui->requestRender(true);
    }

    private function renderDetailError(string $message): void
    {
        $detail = $this->stripControlBytes($message);
        $this->detail->setText($detail);
        $this->tui->requestRender(true);
    }

    private function getDetailCacheKey(int $sectionIndex, DebugItem $item, int $width): string
    {
        return $sectionIndex."\0".$item->type."\0".$item->value."\0".$width;
    }

    private function showLoadingDetail(): void
    {
        $frame = $this->getLoadingFrame();
        $detail = "\e[2m".$frame." Loading details...\e[22m";

        $this->detail->setText($detail);
        $this->tui->requestRender(true);
    }

    private function getLoadingFrame(): string
    {
        $frameCount = \count(self::LOADING_FRAMES);
        $frame = $this->loadingFrame % $frameCount;

        if ($frame < 0) {
            $frame += $frameCount;
        }

        \assert(isset(self::LOADING_FRAMES[$frame]));

        return self::LOADING_FRAMES[$frame];
    }

    private function advanceLoadingFrame(float $deltaTime): void
    {
        $this->loadingFrameElapsed += $deltaTime;

        if ($this->loadingFrameElapsed < self::LOADING_FRAME_INTERVAL) {
            return;
        }

        $steps = (int) floor($this->loadingFrameElapsed / self::LOADING_FRAME_INTERVAL);
        $this->loadingFrame = ($this->loadingFrame + $steps) % \count(self::LOADING_FRAMES);
        $this->loadingFrameElapsed -= $steps * self::LOADING_FRAME_INTERVAL;
    }

    private function setSidebarFiller(int $itemCount, bool $hasHeader): void
    {
        $this->sidebarLineCount = $itemCount;
        $this->sidebarHasHeader = $hasHeader;
        $this->sidebarFiller->setRows($this->getSidebarFillerLines($itemCount, $hasHeader));
    }

    private function getSidebarFillerLines(int $itemCount, bool $hasHeader): int
    {
        if (0 === $itemCount) {
            $listLines = 1; // DebugResultListWidget renders "No matching items".
        } else {
            $listLines = min($itemCount, $this->listMaxVisible);
            if ($itemCount > $this->listMaxVisible) {
                ++$listLines; // scroll indicator
            }
        }

        $contentRows = ($hasHeader ? 1 : 0) + $listLines;
        if ($contentRows >= $this->panelInnerRows) {
            return 0;
        }

        return $this->panelInnerRows - $contentRows;
    }

    private function renderTopBar(): string
    {
        $columns = $this->tui->getTerminal()->getColumns();
        $searchColumns = max(16, intdiv(max(1, $columns - 1), 4));
        $menuColumns = max(1, $columns - $searchColumns - 1);
        $menu = $this->renderMenu($menuColumns);
        $searchMarginLeft = 2; // matches the left panel border + padding
        $searchInnerColumns = max(1, $searchColumns - $searchMarginLeft);

        $icon = '> ';
        $availableSearchColumns = max(1, $searchInnerColumns - AnsiUtils::visibleWidth($icon));
        $search = '';
        if ('' !== $this->query) {
            $search = $this->truncatePlainText($this->stripControlBytes($this->query), $availableSearchColumns);
        }

        $search = $icon.$search;
        $search .= str_repeat(' ', max(0, $searchInnerColumns - AnsiUtils::visibleWidth($search) + 1));
        $search = str_repeat(' ', $searchMarginLeft - 1).$search;
        $searchBorder = str_repeat(' ', $searchMarginLeft - 1).str_repeat('─', AnsiUtils::visibleWidth($search) - ($searchMarginLeft - 1));

        return $search.' '.$menu."\n".$searchBorder;
    }

    private function renderMenu(int $availableColumns): string
    {
        $menu = $this->doRenderMenu(false);

        if (AnsiUtils::visibleWidth($menu) <= $availableColumns) {
            return $menu;
        }

        return $this->doRenderMenu(true);
    }

    private function doRenderMenu(bool $short): string
    {
        $parts = [];
        foreach ($this->sections as $i => $section) {
            $label = $this->stripControlBytes($short ? $section->getShortLabel() : $section->getLabel());
            $parts[] = $i === $this->activeIndex
                ? "\e[7m {$label} \e[27m"
                : " {$label} ";
        }

        return implode(' ', $parts);
    }

    private function truncatePlainText(string $text, int $maxLength): string
    {
        $width = AnsiUtils::visibleWidth($text);
        if ($width <= $maxLength) {
            return $text;
        }

        return AnsiUtils::sliceByColumn($text, $width - $maxLength, $maxLength, true);
    }

    private function removeLastGrapheme(string $text): string
    {
        $length = $this->graphemeLength($text);

        if ($length <= 1) {
            return '';
        }

        $truncated = grapheme_substr($text, 0, $length - 1);

        return false === $truncated ? substr($text, 0, -1) : $truncated;
    }

    private function graphemeLength(string $text): int
    {
        $length = grapheme_strlen($text);

        return null === $length || false === $length ? \strlen($text) : $length;
    }

    private function hasControlChars(string $data): bool
    {
        for ($i = 0, $iMax = \strlen($data); $i < $iMax; ++$i) {
            $code = \ord($data[$i]);
            if ($code < 32 || 0x7F === $code) {
                return true;
            }
        }

        return false;
    }

    private function sanitizeUtf8(string $value): string
    {
        if ('' === $value || false !== preg_match('//u', $value)) {
            return $value;
        }

        $sanitized = @iconv('UTF-8', 'UTF-8//IGNORE', $value);

        return false === $sanitized ? '' : $sanitized;
    }

    private function stripControlBytes(string $value): string
    {
        return preg_replace("/[\x00-\x08\x0b-\x1f\x7f]|\xc2[\x80-\x9f]/", '', $value) ?? '';
    }
}
