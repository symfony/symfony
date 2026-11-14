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
 * Makes a container a scroll viewport, like CSS overflow: auto and overflow: scroll.
 *
 * The content taller than the container's height, or wider than it, is
 * scrolled instead of clipped; the scroll position is kept by the container.
 *
 * @experimental
 */
final class Scroll
{
    /**
     * @param bool        $always            Whether the scrollbar is drawn even when the content fits (overflow: scroll), or only when it does not (overflow: auto)
     * @param bool        $bar               Whether a scrollbar is drawn at all (scrollbar-width: none hides it)
     * @param Color|null  $thumb             The color of the thumb (null = the text color)
     * @param Color|null  $track             The color of the track (null = the text color, dim)
     * @param int         $paddingTop        The rows of the viewport kept clear at its top when something is scrolled into view, like scroll-padding-top
     * @param int         $paddingBottom     The rows kept clear at its bottom
     * @param bool        $gutter            Whether the column of the scrollbar is kept even when the content fits, like scrollbar-gutter: stable
     * @param bool        $smooth            Whether the content glides to where it is scrolled to, like scroll-behavior: smooth
     * @param bool        $thin              Whether the scrollbar is drawn with light lines, like scrollbar-width: thin
     * @param bool        $contained         Whether scrolling past its ends stops at it instead of scrolling the containers around it, like overscroll-behavior: contain
     * @param bool        $vertical          Whether the content taller than the container is scrolled, like overflow-y: auto; else it is clipped
     * @param bool        $horizontal        Whether the content wider than the container is scrolled sideways, like overflow-x: auto; else it is clipped
     * @param bool        $alwaysSideways    Whether the horizontal scrollbar is drawn even when the content fits, like overflow-x: scroll
     * @param int         $paddingLeft       The columns of the viewport kept clear at its left when something is scrolled into view, like scroll-padding-left
     * @param int         $paddingRight      The columns kept clear at its right
     * @param bool        $containedSideways Whether scrolling past its left and right ends stops at it, like overscroll-behavior-x: contain
     * @param string|null $snapSideways      Whether it snaps sideways to its children that have a snap alignment, like scroll-snap-type: x mandatory
     * @param string|null $snap              Whether it snaps to its children that have a snap alignment, vertically: mandatory, always, or proximity, when one is near (null = none), like scroll-snap-type: y mandatory
     */
    public function __construct(public readonly bool $always = false, public readonly bool $bar = true, public readonly ?Color $thumb = null, public readonly ?Color $track = null, public readonly int $paddingTop = 0, public readonly int $paddingBottom = 0, public readonly bool $gutter = false, public readonly bool $smooth = false, public readonly bool $thin = false, public readonly bool $contained = false, public readonly ?string $snap = null, public readonly bool $vertical = true, public readonly bool $horizontal = false, public readonly bool $alwaysSideways = false, public readonly int $paddingLeft = 0, public readonly int $paddingRight = 0, public readonly bool $containedSideways = false, public readonly ?string $snapSideways = null)
    {
    }
}
