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
use Symfony\Component\Tui\Render\RenderContext;
use Symfony\Component\Tui\Widget\Text\InlineNode;

/**
 * Text that moves sideways, like <marquee>: on one line, a few columns each tick.
 *
 * It scrolls across the box and starts again (scroll), comes in and stays
 * (slide), or goes from one side to the other and back (alternate). With
 * prefers-reduced-motion, it stays at its start.
 *
 * @experimental
 */
class MarqueeWidget extends RichTextWidget
{
    use ScheduledTickTrait;

    private int $step = 0;

    /**
     * @param list<InlineNode>             $nodes
     * @param 'left'|'right'               $direction Where the text goes
     * @param 'scroll'|'slide'|'alternate' $behavior
     * @param int                          $amount    The columns it moves each tick
     * @param float                        $delay     The seconds between the ticks
     * @param int                          $loop      How many times it goes, -1 for ever
     */
    public function __construct(
        array $nodes = [],
        private readonly string $direction = 'left',
        private readonly string $behavior = 'scroll',
        private readonly int $amount = 1,
        private readonly float $delay = 0.085,
        private readonly int $loop = -1,
    ) {
        parent::__construct($nodes);
    }

    /**
     * Moves the text by its amount, until its loops are done.
     */
    public function tick(): void
    {
        ++$this->step;
        $this->invalidate();
    }

    /**
     * @return string[]
     */
    public function render(RenderContext $context): array
    {
        $columns = max(1, $context->getColumns());
        // The text on lines as long as they are, not wrapped
        $lines = parent::render($context->withColumns(10_000));
        $width = max(0, ...array_map(AnsiUtils::visibleWidth(...), $lines));
        $offset = $this->offset($columns, $width);

        return array_map(static function (string $line) use ($columns, $offset): string {
            $before = max(0, $offset);

            return str_repeat(' ', min($columns, $before)).AnsiUtils::sliceToWidth($line, max(0, -$offset), max(0, $columns - $before))."\e[0m";
        }, $lines);
    }

    /**
     * The column the text starts at, before the box when negative.
     */
    private function offset(int $columns, int $width): int
    {
        $right = 'right' === $this->direction;
        $moved = $this->step * max(1, $this->amount);
        if (true === $this->getContext()?->getMediaContext()->reducedMotion) {
            $moved = 0;
        }

        switch ($this->behavior) {
            case 'slide':
                // In from the side it comes from, then still against the other
                return $right ? min($columns - $width, -$width + $moved) : max(0, $columns - $moved);
            case 'alternate':
                // From one side to the other and back, between the edges of the box (of the text, when wider)
                $span = abs($columns - $width);
                if (0 === $span) {
                    return 0;
                }
                $low = min(0, $columns - $width);
                $position = $moved % (2 * $span);
                $position = $position > $span ? 2 * $span - $position : $position;

                return $right ? $low + $position : $low + $span - $position;
            default:
                // Across the box from the side it comes from, out at the other, again; still at its start once its loops
                // are done
                $travel = $columns + $width;
                if ($this->loop > 0 && $moved >= $this->loop * $travel) {
                    return $right ? $columns - $width : 0;
                }
                $position = $moved % max(1, $travel);

                return $right ? -$width + $position : $columns - $position;
        }
    }

    protected function onAttach(WidgetContext $context): void
    {
        parent::onAttach($context);
        $this->startScheduledTick(max(0.01, $this->delay));
    }

    protected function onDetach(): void
    {
        $this->stopScheduledTick();
        parent::onDetach();
    }

    protected function onScheduledTick(): void
    {
        $this->tick();
    }

    protected function resolveScheduledTickContext(): ?WidgetContext
    {
        return $this->getContext();
    }
}
