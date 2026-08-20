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
 * Interface for cinematic screen transitions using raw ANSI strings.
 *
 * @experimental
 *
 * @author Simon André <smn.andre@gmail.com>
 */
interface TransitionInterface
{
    /**
     * Blend two screens represented as arrays of ANSI-formatted strings.
     *
     * @param list<string> $fromLines ANSI strings of the starting screen
     * @param list<string> $toLines   ANSI strings of the destination screen
     * @param int          $width     The width of the screen
     * @param int          $height    The height of the screen
     * @param float        $progress  Normalized progress from 0.0 to 1.0
     *
     * @return list<string> The blended ANSI strings
     */
    public function blend(array $fromLines, array $toLines, int $width, int $height, float $progress): array;
}
