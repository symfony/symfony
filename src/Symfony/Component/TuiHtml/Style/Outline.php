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
 * A line drawn around a box, over what is next to it, like CSS outline.
 *
 * It takes no room: it is drawn over the frame once laid out, outside the
 * border, or further out or in by its offset, and cut by the screen.
 *
 * @experimental
 */
final class Outline
{
    public readonly int $offset;

    /**
     * @param string     $pattern The border pattern of the line, like BorderPattern::DASHED
     * @param Color|null $color   The color of the line (null = the text color)
     * @param int        $offset  The cells between the box and the line, like outline-offset: negative ones draw it inside
     */
    public function __construct(public readonly string $pattern = BorderPattern::NORMAL, public readonly ?Color $color = null, int $offset = 0)
    {
        $this->offset = max(-100, min(100, $offset));
    }
}
