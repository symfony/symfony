<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Tui\Style;

/**
 * Contract shared by the gradient backgrounds a Style can carry.
 *
 * This is not a third-party extension point: Style::withLinearGradient() is
 * typed against the concrete class, and the interface stays internal. It is
 * what lets Style hold any gradient implementation without knowing its
 * geometry, a radial gradient being the natural second one.
 *
 * @experimental
 *
 * @internal
 */
interface GradientInterface
{
    /**
     * Resolve the background of every cell of a $width x $height area.
     *
     * @return array<int, array<int, Color>> colors indexed by row, then by column
     */
    public function resolve(int $width, int $height): array;
}
