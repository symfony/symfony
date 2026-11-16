<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\TuiHtml\Style;

/**
 * The horizontal margins that take the free columns, like CSS "margin: auto".
 *
 * In a row, the free columns go to the auto margins of the children before
 * any justify-content distribution: "margin-left: auto" pushes a child and
 * the ones after it to the end. A narrower block (max-width, a percentage
 * width) gets the columns it does not use: both margins center it.
 *
 * @experimental
 */
enum AutoMargin: string
{
    case Left = 'left';
    case Right = 'right';
    case Both = 'both';

    public static function fromSides(bool $left, bool $right): ?self
    {
        return match (true) {
            $left && $right => self::Both,
            $left => self::Left,
            $right => self::Right,
            default => null,
        };
    }

    public function hasLeft(): bool
    {
        return self::Right !== $this;
    }

    public function hasRight(): bool
    {
        return self::Left !== $this;
    }
}
