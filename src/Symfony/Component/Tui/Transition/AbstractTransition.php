<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Tui\Transition;

/**
 * Base class for string-based cinematic transitions with easing support.
 *
 * @experimental
 *
 * @author Simon André <smn.andre@gmail.com>
 */
abstract class AbstractTransition implements TransitionInterface
{
    /**
     * @var \Closure(float): float|null
     */
    private ?\Closure $easing = null;

    public function setEasing(?callable $easing): static
    {
        $this->easing = null === $easing ? null : $easing(...);

        return $this;
    }

    public function blend(array $fromLines, array $toLines, int $width, int $height, float $progress): array
    {
        // Checked on the raw progress, before easing: a bouncy or elastic easing is expected to
        // overshoot past 0.0/1.0 mid-transition (that's what makes it look bouncy), and a widget
        // only ever reports those exact values at its true start and end. Checking post-easing
        // would latch the render onto a flat "from"/"to" screen for the rest of the transition
        // the moment the eased curve first touches a bound, hiding any settle/bounce that follows.
        if (0.0 === $progress) {
            return $fromLines;
        }

        if (1.0 === $progress) {
            return $toLines;
        }

        if (null !== $this->easing) {
            $progress = ($this->easing)($progress);
        }

        $progress = max(0.0, min(1.0, $progress));

        return $this->doBlend($fromLines, $toLines, $width, $height, $progress);
    }

    /**
     * Blends the rendered lines of the old and new screens at the current transition progress.
     *
     * @param list<string> $fromLines Lines rendered by the outgoing screen
     * @param list<string> $toLines   Lines rendered by the incoming screen
     * @param int          $width     Available width, in terminal columns
     * @param int          $height    Available height, in terminal rows
     * @param float        $progress  Eased and clamped progress from 0.0 (outgoing) to 1.0 (incoming)
     *
     * @return list<string> The blended screen lines
     */
    abstract protected function doBlend(array $fromLines, array $toLines, int $width, int $height, float $progress): array;
}
