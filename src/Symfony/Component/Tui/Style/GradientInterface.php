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
 * No method accepts this interface: Style::withLinearGradient() and
 * Style::withRadialGradient() are typed against the concrete classes, so a
 * third-party implementation has nowhere to go. It stays internal until the
 * component settles on the shape a public extension point should have.
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
     * @return array<int, array<int, string>> ANSI background codes indexed by row, then by column
     */
    public function resolve(int $width, int $height): array;
}
