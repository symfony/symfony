<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\TuiHtml\Internal;

use Symfony\Component\Tui\Render\LineBufferInterface;
use Symfony\Component\Tui\Widget\AbstractWidget;
use Symfony\Component\TuiHtml\Widget\RichTextWidget;

/**
 * The render cache of a widget, on top of the one of Tui (AbstractWidget::getRenderCache()): what a render gives
 * besides its lines, like the floats going on past a container, and the renders in a few other sizes, for a layout that
 * measures a widget in a few sizes before drawing it.
 *
 * The render kept by Tui is the one of the widget: what is kept here goes with it, and is dropped when that render
 * is, like when the widget changes (AbstractWidget::invalidate()) or its render cache is cleared, by Tui or by the
 * layouts (AbstractWidget::clearRenderCache()).
 *
 * @internal
 */
final class RenderCache
{
    /** @var array{LineBufferInterface, int, int, string}|null The render last kept, as given to Tui: lines, columns, rows, key */
    private ?array $current = null;
    private int $revision = -1;
    /** @var array<mixed> */
    private array $extra = [];
    /** @var array<string, array{LineBufferInterface, array<mixed>}> The renders of the same revision in other sizes, by "columns:rows:key", the oldest first */
    private array $others = [];

    /**
     * @param bool $otherSizes Whether a render kept in another size than the last one may be taken
     *
     * @param-out bool  $otherSize  Whether the render taken was kept in another size than the last one: what the last
     *                              render left besides its lines, like where the descendants of the widget are or where
     *                              the lines of a text start, is not of it
     */
    public static function get(AbstractWidget $widget, int $columns, int $rows, string $key, bool $otherSizes = true, ?bool &$otherSize = null): ?LineBufferInterface
    {
        $otherSize = false;
        if (null !== $lines = $widget->getRenderCache($columns, $rows, $key)) {
            return $lines;
        }
        $state = $widget->getExtensionState(self::class);
        if (!$otherSizes || [] === $state->others || !$state->isCurrent($widget)) {
            return null;
        }
        $lines = $state->others[$columns.':'.$rows.':'.$key][0] ?? null;
        $otherSize = null !== $lines;

        return $lines;
    }

    /**
     * What the render cached in that size gives besides its lines, see set(): empty on a miss.
     *
     * @return array<mixed>
     */
    public static function getExtra(AbstractWidget $widget, int $columns, int $rows, string $key): array
    {
        $state = $widget->getExtensionState(self::class);
        if (!$state->isCurrent($widget)) {
            return [];
        }
        if ([$columns, $rows, $key] === [$state->current[1], $state->current[2], $state->current[3]]) {
            return $state->extra;
        }

        return $state->others[$columns.':'.$rows.':'.$key][1] ?? [];
    }

    /**
     * @param array<mixed> $extra      What the render gives besides its lines, kept and dropped with them
     * @param int          $otherSizes How many renders of the same revision in other sizes to keep too
     */
    public static function set(AbstractWidget $widget, LineBufferInterface $lines, int $columns, int $rows, string $key, array $extra = [], int $otherSizes = 0): void
    {
        $state = $widget->getExtensionState(self::class);
        $current = $state->isCurrent($widget);
        if ($otherSizes > 0 && $current && [$state->current[1], $state->current[2], $state->current[3]] !== [$columns, $rows, $key]) {
            $previous = $state->current[1].':'.$state->current[2].':'.$state->current[3];
            unset($state->others[$previous]);
            $state->others[$previous] = [$state->current[0], $state->extra];
            if (\count($state->others) > $otherSizes) {
                array_shift($state->others);
            }
        } elseif ($otherSizes <= 0 || !$current) {
            $state->others = [];
        }
        unset($state->others[$columns.':'.$rows.':'.$key]);
        $widget->setRenderCache($lines, $columns, $rows, $key);
        $state->current = [$lines, $columns, $rows, $key];
        $state->extra = $extra;
        $state->revision = $widget->getRenderRevision();
    }

    /**
     * Runs a render that is not the usual one of the widget, like a measure, without its render cache: what it renders
     * is not kept, and the renders kept before are kept after it, unless the widget changed meanwhile: with what they
     * left besides their lines, like where the lines of a text start, which that render changed.
     *
     * @template T
     *
     * @param \Closure(): T $render
     *
     * @return T
     */
    public static function without(AbstractWidget $widget, \Closure $render): mixed
    {
        $state = $widget->getExtensionState(self::class);
        $saved = $state->isCurrent($widget) ? [$state->current, $state->extra, $state->others, $state->revision, $widget instanceof RichTextWidget ? $widget->getRenderedState() : null] : null;
        $widget->clearRenderCache();
        $state->current = null;
        $state->others = [];
        try {
            return $render();
        } finally {
            if (null !== $saved && $saved[3] === $widget->getRenderRevision()) {
                [$state->current, $state->extra, $state->others, $state->revision] = $saved;
                $widget->setRenderCache($saved[0][0], $saved[0][1], $saved[0][2], $saved[0][3]);
                if (null !== $saved[4]) {
                    \assert($widget instanceof RichTextWidget);
                    $widget->setRenderedState($saved[4]);
                }
            } else {
                $widget->clearRenderCache();
                $state->current = null;
                $state->others = [];
            }
        }
    }

    /**
     * @phpstan-assert-if-true !null $this->current
     */
    private function isCurrent(AbstractWidget $widget): bool
    {
        // Gone when Tui no longer keeps the same render: cleared, or the widget changed
        return null !== $this->current && $this->revision === $widget->getRenderRevision() && $widget->getRenderCache($this->current[1], $this->current[2], $this->current[3]) === $this->current[0];
    }
}
