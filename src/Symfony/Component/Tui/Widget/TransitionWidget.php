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
use Symfony\Component\Tui\Exception\InvalidArgumentException;
use Symfony\Component\Tui\Loop\LoopClock;
use Symfony\Component\Tui\Render\RenderContext;
use Symfony\Component\Tui\Transition\TransitionInterface;

/**
 * A widget that orchestrates cinematic transitions natively via AnsiUtils (No CellBuffer hacks).
 *
 * @experimental
 *
 * @author Simon André <smn.andre@gmail.com>
 */
final class TransitionWidget extends AbstractWidget implements ParentInterface, VerticallyExpandableInterface
{
    use ScheduledTickTrait;

    private const TICK_INTERVAL = 0.016;

    private ?AbstractWidget $from = null;
    private ?AbstractWidget $to = null;
    private ?TransitionInterface $transition = null;
    private float $progress = 0.0;
    private float $duration = 1.0;
    private bool $active = false;
    private bool $verticallyExpanded = false;
    private ?string $fromBackground = null;
    private ?string $toBackground = null;
    private LoopClock $clock;

    public function __construct()
    {
        $this->clock = new LoopClock();
    }

    public function expandVertically(bool $expand): static
    {
        $this->verticallyExpanded = $expand;
        $this->invalidate();

        return $this;
    }

    public function isVerticallyExpanded(): bool
    {
        return $this->verticallyExpanded;
    }

    public function start(AbstractWidget $from, AbstractWidget $to, TransitionInterface $transition, float $duration = 1.0): void
    {
        if ($duration <= 0.0) {
            throw new InvalidArgumentException(\sprintf('Duration must be greater than 0, got "%s".', $duration));
        }

        $this->stopScheduledTick();

        foreach ($this->all() as $child) {
            $this->detachChild($child);
        }

        $this->from = $from;
        $this->to = $to;

        $this->attachChild($from);
        $this->attachChild($to);

        $this->transition = $transition;
        $this->duration = $duration;
        $this->progress = 0.0;
        $this->active = true;
        $this->fromBackground = null;
        $this->toBackground = null;
        $this->clock->reset();

        $this->invalidate();

        $this->startScheduledTick(self::TICK_INTERVAL);
    }

    protected function resolveScheduledTickContext(): ?WidgetContext
    {
        return $this->getContext();
    }

    /**
     * Advances the transition by the elapsed wall-clock time, or by $deltaTime when given.
     */
    public function tick(?float $deltaTime = null): bool
    {
        if (!$this->active) {
            $this->stopScheduledTick();

            return false;
        }

        $this->progress += $this->clock->advance($deltaTime) / $this->duration;

        if ($this->progress >= 1.0) {
            $this->progress = 1.0;
            $this->active = false;
            $this->stopScheduledTick();
        }

        $this->invalidate();

        return true;
    }

    protected function onAttach(WidgetContext $context): void
    {
        if ($this->active) {
            $this->clock->reset();
            $this->resumeScheduledTick();
        }
    }

    protected function onDetach(): void
    {
        $this->stopScheduledTick();
    }

    protected function onScheduledTick(): void
    {
        $this->tick();
    }

    public function getTo(): ?AbstractWidget
    {
        return $this->to;
    }

    /**
     * Whether a transition is running, i.e. started and not yet at its end.
     */
    public function isActive(): bool
    {
        return $this->active;
    }

    /**
     * How far the running transition has gone, from 0.0 to 1.0.
     */
    public function getProgress(): float
    {
        return $this->progress;
    }

    public function render(RenderContext $context): array
    {
        if (null === $this->getContext()) {
            return [];
        }

        if (!$this->active || !$this->from || !$this->to || !$this->transition) {
            return $this->to ? $this->getContext()->renderWidget($this->to, $context) : [];
        }

        $width = $context->getColumns();
        $height = $context->getRows();

        $fromLines = $this->getContext()->renderWidget($this->from, $context);
        $toLines = $this->getContext()->renderWidget($this->to, $context);

        // The background can't change for the lifetime of a start() call, but resolving it
        // walks the full style cascade (class hierarchy + style classes + state selectors);
        // resolve it once lazily and reuse it on every later render() until the next start().
        $this->fromBackground ??= $this->getBackgroundColor($this->from);
        $this->toBackground ??= $this->getBackgroundColor($this->to);

        $fromLines = $this->padLines($fromLines, $width, $height, $this->fromBackground);
        $toLines = $this->padLines($toLines, $width, $height, $this->toBackground);

        $resultLines = $this->transition->blend($fromLines, $toLines, $width, $height, $this->progress);

        return array_map($this->closeStyles(...), $resultLines);
    }

    /**
     * Closes any style a blended line ends on.
     *
     * blend() concatenates slices of both screens, so a line routinely ends while one of their
     * colors is still active. Left open, that color runs past the widget: the parent container
     * paints its own padding with it instead of its background.
     */
    private function closeStyles(string $line): string
    {
        if (!str_contains($line, "\x1b") || str_ends_with($line, "\x1b[0m")) {
            return $line;
        }

        return $line."\x1b[0m";
    }

    /**
     * @param array<string> $lines
     *
     * @return list<string>
     */
    private function padLines(array $lines, int $width, int $height, string $bgCode): array
    {
        $padded = [];
        $fullPadding = null;

        for ($i = 0; $i < $height; ++$i) {
            $line = $lines[$i] ?? '';
            $lineWidth = AnsiUtils::visibleWidth($line);

            // renderWidget() already guarantees non-image lines never exceed $width, and image
            // lines have no meaningful column width, so a full-or-wider line is passed through
            // untouched; only shorter lines are padded to keep every buffer $width wide.
            if ($lineWidth >= $width) {
                $padded[] = $line;

                continue;
            }

            if (null === $fullPadding) {
                $fullPadding = $bgCode.str_repeat(' ', $width)."\x1b[0m";
            }

            $padded[] = $line.AnsiUtils::sliceByColumn($fullPadding, 0, $width - $lineWidth);
        }

        return $padded;
    }

    private function getBackgroundColor(AbstractWidget $widget): string
    {
        $style = $this->getContext()->resolveElement($widget, '');
        $bgColor = $style->getBackground();

        return $bgColor ? $bgColor->toBackgroundCode() : '';
    }

    public function all(): array
    {
        return array_filter([$this->from, $this->to]);
    }
}
