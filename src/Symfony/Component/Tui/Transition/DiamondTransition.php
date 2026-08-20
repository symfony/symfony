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
use Symfony\Component\Tui\Transition\Direction\RadialDirection;

/**
 * Expanding diamond-shape reveal transition.
 *
 * @experimental
 *
 * @author Simon André <smn.andre@gmail.com>
 */
final class DiamondTransition extends AbstractTransition
{
    /**
     * @param RadialDirection $direction Inward (old screen shrinks to center) or Outward (new screen grows from center)
     */
    public function __construct(
        private readonly RadialDirection $direction = RadialDirection::Outward,
    ) {
    }

    protected function doBlend(array $fromLines, array $toLines, int $width, int $height, float $progress): array
    {
        $outward = RadialDirection::Outward === $this->direction;
        $effectiveProgress = $outward ? $progress : (1.0 - $progress);

        // Outward: the growing diamond holds "to", everywhere else holds "from". Inward is the
        // mirror of that, not a variant of it: the shrinking diamond holds "from" and everywhere
        // else holds "to". Both the row-range fallback below and the in-row segment need this
        // swap, not just the fallback: swapping only the fallback still paints the diamond's own
        // interior with "to" for Inward, which is the same shape Outward draws, not its mirror.
        $insideLines = $outward ? $toLines : $fromLines;
        $outsideLines = $outward ? $fromLines : $toLines;

        $centerX = (int) ($width / 2);
        $centerY = (int) ($height / 2);
        $maxDist = $centerX + $centerY;
        $threshold = $maxDist * $effectiveProgress;

        $result = [];
        for ($y = 0; $y < $height; ++$y) {
            $dy = abs($y - $centerY);
            $d = $threshold - $dy;

            if ($d < 0) {
                $result[] = $outsideLines[$y] ?? '';
                continue;
            }

            $start = max(0, (int) ($centerX - $d));
            $end = min($width, (int) ($centerX + $d + 1));
            $len = $end - $start;

            $line = AnsiUtils::sliceToWidth($outsideLines[$y] ?? '', 0, $start);
            $line .= AnsiUtils::sliceToWidth($insideLines[$y] ?? '', $start, $len);
            $line .= AnsiUtils::sliceToWidth($outsideLines[$y] ?? '', $end, $width - $end);

            $result[] = $line;
        }

        return $result;
    }
}
