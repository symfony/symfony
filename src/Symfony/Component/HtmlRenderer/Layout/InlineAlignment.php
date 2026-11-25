<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HtmlRenderer\Layout;

/**
 * @internal
 */
final class InlineAlignment
{
    public static function offset(float $room, float $content, string $alignment, string $direction = 'ltr'): float
    {
        $space = max(0.0, $room - $content);
        $alignment = match ($alignment) {
            'start', 'match-parent' => 'rtl' === $direction ? 'right' : 'left',
            'end' => 'rtl' === $direction ? 'left' : 'right',
            default => $alignment,
        };

        return match ($alignment) {
            'center' => $space / 2,
            'right' => $space,
            default => 0.0,
        };
    }
}
