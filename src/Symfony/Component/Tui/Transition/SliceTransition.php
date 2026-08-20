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
 * Alternating slice reveal transition.
 *
 * @experimental
 *
 * @author Simon André <smn.andre@gmail.com>
 */
final class SliceTransition extends AbstractTransition
{
    /**
     * @param HorizontalDirection|VerticalDirection $direction Entrance edge of the first slice; subsequent slices alternate
     * @param int                                   $size      Column band width in cells for vertical entrances
     */
    public function __construct(
        private readonly HorizontalDirection|VerticalDirection $direction = HorizontalDirection::Left,
        private readonly int $size = 4,
    ) {
        if ($size <= 0) {
            throw new InvalidArgumentException(\sprintf('Size must be greater than 0, got %d.', $size));
        }
    }

    protected function doBlend(array $fromLines, array $toLines, int $width, int $height, float $progress): array
    {
        if ($this->direction instanceof HorizontalDirection) {
            $revealWidth = (int) ($width * $progress);
            $result = [];

            for ($y = 0; $y < $height; ++$y) {
                $toLine = $toLines[$y] ?? '';
                $fromLine = $fromLines[$y] ?? '';

                $fromLeft = HorizontalDirection::Left === $this->direction;
                if (1 === $y % 2) {
                    $fromLeft = !$fromLeft;
                }

                if ($fromLeft) {
                    $result[] = AnsiUtils::sliceToWidth($toLine, 0, $revealWidth).AnsiUtils::sliceToWidth($fromLine, $revealWidth, $width - $revealWidth);
                } else {
                    $result[] = AnsiUtils::sliceToWidth($fromLine, 0, $width - $revealWidth).AnsiUtils::sliceToWidth($toLine, $width - $revealWidth, $revealWidth);
                }
            }

            return $result;
        }

        $revealHeight = (int) ($height * $progress);
        $result = [];

        for ($y = 0; $y < $height; ++$y) {
            $toLine = $toLines[$y] ?? '';
            $fromLine = $fromLines[$y] ?? '';

            $line = '';
            for ($x = 0; $x < $width; $x += $this->size) {
                $sliceWidth = min($this->size, $width - $x);
                $fromTop = VerticalDirection::Top === $this->direction;
                if (1 === intdiv($x, $this->size) % 2) {
                    $fromTop = !$fromTop;
                }

                $revealed = $fromTop ? $y < $revealHeight : $y >= $height - $revealHeight;
                $line .= AnsiUtils::sliceToWidth($revealed ? $toLine : $fromLine, $x, $sliceWidth);
            }

            $result[] = $line;
        }

        return $result;
    }
}
