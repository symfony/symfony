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
use Symfony\Component\Tui\Render\RenderContext;
use Symfony\Component\Tui\Widget\AbstractWidget;
use Symfony\Component\Tui\Widget\VerticallyExpandableInterface;

/**
 * @internal
 */
final class DebugDetailWidget extends AbstractWidget implements VerticallyExpandableInterface
{
    /** @var list<string> */
    private array $lines = [];
    private int $scrollOffset = 0;
    private int $lastPageSize = 1;
    private bool $verticallyExpanded = false;

    public function setText(string $text): void
    {
        $lines = '' === $text ? [] : preg_split('/\r\n|\n|\r/', $text);
        $lines = false === $lines ? [] : $lines;

        while ($lines && '' === trim($lines[0])) {
            array_shift($lines);
        }

        if ($lines === $this->lines) {
            return;
        }

        $this->lines = array_values($lines);
        $this->scrollOffset = 0;
        $this->invalidate();
    }

    public function pageUp(): void
    {
        $this->scrollBy(-$this->lastPageSize);
    }

    public function pageDown(): void
    {
        $this->scrollBy($this->lastPageSize);
    }

    public function scrollToTop(): void
    {
        $this->setScrollOffset(0);
    }

    public function scrollToBottom(): void
    {
        $this->setScrollOffset(max(0, \count($this->lines) - $this->lastPageSize));
    }

    public function expandVertically(bool $expand): static
    {
        if ($this->verticallyExpanded !== $expand) {
            $this->verticallyExpanded = $expand;
            $this->invalidate();
        }

        return $this;
    }

    public function isVerticallyExpanded(): bool
    {
        return $this->verticallyExpanded;
    }

    /**
     * @return string[]
     */
    public function render(RenderContext $context): array
    {
        $columns = $context->getColumns();
        $rows = max(1, $context->getRows());
        $lineCount = \count($this->lines);

        if (0 === $lineCount) {
            $this->lastPageSize = $rows;

            return array_fill(0, $rows, '');
        }

        $hasOverflow = $lineCount > $rows;
        $contentRows = $hasOverflow ? max(1, $rows - 1) : $rows;
        $maxOffset = max(0, $lineCount - $contentRows);
        $this->scrollOffset = min($this->scrollOffset, $maxOffset);
        $this->lastPageSize = $contentRows;

        $lines = [];
        if ($hasOverflow) {
            $lines[] = $this->renderScrollIndicator($columns, $this->scrollOffset, $lineCount);
        }

        foreach (\array_slice($this->lines, $this->scrollOffset, $contentRows) as $line) {
            $lines[] = AnsiUtils::truncateToWidth($line, $columns);
        }

        while (\count($lines) < $rows) {
            $lines[] = '';
        }

        return $lines;
    }

    private function scrollBy(int $delta): void
    {
        $this->setScrollOffset($this->scrollOffset + $delta);
    }

    private function setScrollOffset(int $offset): void
    {
        $offset = max(0, min($offset, max(0, \count($this->lines) - 1)));

        if ($offset === $this->scrollOffset) {
            return;
        }

        $this->scrollOffset = $offset;
        $this->invalidate();
    }

    private function renderScrollIndicator(int $columns, int $offset, int $lineCount): string
    {
        $current = min($lineCount, $offset + 1);
        $indicator = \sprintf('(%d/%d) PgUp/PgDn', $current, $lineCount);
        $indicator = AnsiUtils::truncateToWidth($indicator, $columns, '');

        return "\e[2m".str_repeat(' ', max(0, $columns - AnsiUtils::visibleWidth($indicator))).$indicator."\e[22m";
    }
}
