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

use Symfony\Component\Tui\Ansi\AnsiUtils;
use Symfony\Component\Tui\Exception\InvalidArgumentException;
use Symfony\Component\Tui\Transition\Direction\HorizontalDirection;
use Symfony\Component\Tui\Transition\Direction\VerticalDirection;

/**
 * Rhythmic shutter reveal transition.
 *
 * @experimental
 *
 * @author Simon André <smn.andre@gmail.com>
 */
final class ShuttersTransition extends AbstractTransition
{
    /**
     * @param HorizontalDirection|VerticalDirection $direction Entrance edge within each shutter
     * @param int                                   $size      Shutter thickness in cells
     */
    public function __construct(
        private readonly HorizontalDirection|VerticalDirection $direction = VerticalDirection::Top,
        private readonly int $size = 4,
    ) {
        if ($size <= 0) {
            throw new InvalidArgumentException(\sprintf('Size must be greater than 0, got %d.', $size));
        }
    }

    protected function doBlend(array $fromLines, array $toLines, int $width, int $height, float $progress): array
    {
        $result = [];

        if ($this->direction instanceof VerticalDirection) {
            // The row-level reveal is necessarily as coarse as $size (an integer count of rows),
            // but the one row currently mid-reveal doesn't have to jump in a single frame: wiping
            // it left-to-right by the fractional part of $exactReveal spreads that jump across as
            // many frames as the transition's own duration allows, instead of capping the whole
            // shutter at $size distinct visual steps.
            $fromTop = VerticalDirection::Top === $this->direction;

            for ($y = 0; $y < $height; ++$y) {
                $shutterStart = intdiv($y, $this->size) * $this->size;
                $shutterHeight = min($this->size, $height - $shutterStart);
                $exactReveal = $shutterHeight * $progress;
                $revealed = (int) $exactReveal;
                $boundary = $exactReveal - $revealed;
                $offset = $y - $shutterStart;
                $toLine = $toLines[$y] ?? '';
                $fromLine = $fromLines[$y] ?? '';
                $fullyRevealed = $fromTop ? $offset < $revealed : $offset >= $shutterHeight - $revealed;
                $boundaryOffset = $fromTop ? $revealed : $shutterHeight - $revealed - 1;

                if ($fullyRevealed) {
                    $result[] = $toLine;
                } elseif ($offset === $boundaryOffset && $boundary > 0.0) {
                    $wipe = (int) ($width * $boundary);
                    $result[] = AnsiUtils::sliceToWidth($toLine, 0, $wipe).AnsiUtils::sliceToWidth($fromLine, $wipe, $width - $wipe);
                } else {
                    $result[] = $fromLine;
                }
            }

            return $result;
        }

        $fromLeft = HorizontalDirection::Left === $this->direction;

        for ($y = 0; $y < $height; ++$y) {
            $toLine = $toLines[$y] ?? '';
            $fromLine = $fromLines[$y] ?? '';

            $line = '';
            for ($x = 0; $x < $width; $x += $this->size) {
                $shutterWidth = min($this->size, $width - $x);
                $revealWidth = (int) ($shutterWidth * $progress);

                if ($fromLeft) {
                    $line .= AnsiUtils::sliceToWidth($toLine, $x, $revealWidth);
                    $line .= AnsiUtils::sliceToWidth($fromLine, $x + $revealWidth, $shutterWidth - $revealWidth);
                } else {
                    $line .= AnsiUtils::sliceToWidth($fromLine, $x, $shutterWidth - $revealWidth);
                    $line .= AnsiUtils::sliceToWidth($toLine, $x + $shutterWidth - $revealWidth, $revealWidth);
                }
            }

            $result[] = $line;
        }

        return $result;
    }
}
