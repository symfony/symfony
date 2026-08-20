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
use Symfony\Component\Tui\Transition\Direction\HorizontalDirection;
use Symfony\Component\Tui\Transition\Direction\VerticalDirection;

/**
 * Wiping transition.
 *
 * @experimental
 *
 * @author Simon André <smn.andre@gmail.com>
 */
final class WipeTransition extends AbstractTransition
{
    /**
     * @param HorizontalDirection|VerticalDirection $direction Edge the incoming screen is revealed from
     */
    public function __construct(
        private readonly HorizontalDirection|VerticalDirection $direction = HorizontalDirection::Left,
    ) {
    }

    protected function doBlend(array $fromLines, array $toLines, int $width, int $height, float $progress): array
    {
        if ($this->direction instanceof HorizontalDirection) {
            $result = [];
            $split = (int) ($width * $progress);

            if (HorizontalDirection::Right === $this->direction) {
                for ($i = 0; $i < $height; ++$i) {
                    $fromPart = AnsiUtils::sliceToWidth($fromLines[$i] ?? '', 0, $width - $split);
                    $toPart = AnsiUtils::sliceToWidth($toLines[$i] ?? '', $width - $split, $split);
                    $result[] = $fromPart.$toPart;
                }

                return $result;
            }

            // HorizontalDirection::Left
            for ($i = 0; $i < $height; ++$i) {
                $toPart = AnsiUtils::sliceToWidth($toLines[$i] ?? '', 0, $split);
                $fromPart = AnsiUtils::sliceToWidth($fromLines[$i] ?? '', $split, $width - $split);
                $result[] = $toPart.$fromPart;
            }

            return $result;
        }

        $split = (int) ($height * $progress);

        if (VerticalDirection::Bottom === $this->direction) {
            return array_merge(
                \array_slice($fromLines, 0, $height - $split),
                \array_slice($toLines, $height - $split)
            );
        }

        // VerticalDirection::Top
        return array_merge(
            \array_slice($toLines, 0, $split),
            \array_slice($fromLines, $split)
        );
    }
}
