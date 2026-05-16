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
 * Drop-shadow configuration for a widget.
 *
 * The shadow is rendered outside the widget's box using Unicode block
 * characters (░ ▒ ▓ █). It is always shifted 1 cell from the widget edge
 * in the orientation direction, giving a "lifted" depth effect.
 *
 * @experimental
 *
 * @author Sylvain Blondeau <contact@sylvainblondeau.dev>
 */
final class Shadow
{
    private const CHARS = ['░', '▒', '▓', '█'];

    public readonly Color $color;
    public readonly int $density;
    public readonly int $offset;

    /**
     * @param ShadowOrientation $orientation Corner the shadow extends toward
     * @param Color|null        $color       Foreground color of the shade characters (null = gray)
     * @param int               $density     Visual weight of the shadow: 1=░ 2=▒ 3=▓ 4=█ (clamped to 1-4)
     * @param int               $offset      Thickness of the shadow in cells (clamped to 1-3)
     * @param bool              $spread      Whether the shadow fades outward from density (default) or stays solid
     */
    public function __construct(
        public readonly ShadowOrientation $orientation = ShadowOrientation::BottomRight,
        ?Color $color = null,
        int $density = 2,
        int $offset = 1,
        public readonly bool $spread = true,
    ) {
        $this->color = $color ?? Color::named('gray');
        $this->density = max(1, min(4, $density));
        $this->offset = max(1, min(3, $offset));
    }

    /**
     * Return the shade character for a given distance from the widget edge.
     *
     * Distance 1 = adjacent to the widget (darkest), increasing distances
     * fade outward when spread=true.
     */
    public function getChar(int $distance): string
    {
        return self::CHARS[$this->spread ? max(0, $this->density - $distance) : $this->density - 1];
    }
}
