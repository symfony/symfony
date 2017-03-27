<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Image\Draw;

use Symfony\Component\Image\Image\AbstractFont;
use Symfony\Component\Image\Image\BoxInterface;
use Symfony\Component\Image\Image\Palette\Color\ColorInterface;
use Symfony\Component\Image\Image\PointInterface;
use Symfony\Component\Image\Exception\RuntimeException;

interface DrawerInterface
{
    /**
     * Draws an arc on a starting at a given x, y coordinates under a given
     * start and end angles.
     *
     * @param PointInterface $center
     * @param BoxInterface   $size
     * @param int            $start
     * @param int            $end
     * @param ColorInterface $color
     * @param int            $thickness
     *
     * @throws RuntimeException
     *
     * @return DrawerInterface
     */
    public function arc(PointInterface $center, BoxInterface $size, $start, $end, ColorInterface $color, $thickness = 1);

    /**
     * Same as arc, but also connects end points with a straight line.
     *
     * @param PointInterface $center
     * @param BoxInterface   $size
     * @param int            $start
     * @param int            $end
     * @param ColorInterface $color
     * @param bool           $fill
     * @param int            $thickness
     *
     * @throws RuntimeException
     *
     * @return DrawerInterface
     */
    public function chord(PointInterface $center, BoxInterface $size, $start, $end, ColorInterface $color, $fill = false, $thickness = 1);

    /**
     * Draws and ellipse with center at the given x, y coordinates, and given
     * width and height.
     *
     * @param PointInterface $center
     * @param BoxInterface   $size
     * @param ColorInterface $color
     * @param bool           $fill
     * @param int            $thickness
     *
     * @throws RuntimeException
     *
     * @return DrawerInterface
     */
    public function ellipse(PointInterface $center, BoxInterface $size, ColorInterface $color, $fill = false, $thickness = 1);

    /**
     * Draws a line from start(x, y) to end(x, y) coordinates.
     *
     * @param PointInterface $start
     * @param PointInterface $end
     * @param ColorInterface $outline
     * @param int            $thickness
     *
     * @return DrawerInterface
     */
    public function line(PointInterface $start, PointInterface $end, ColorInterface $outline, $thickness = 1);

    /**
     * Same as arc, but connects end points and the center.
     *
     * @param PointInterface $center
     * @param BoxInterface   $size
     * @param int            $start
     * @param int            $end
     * @param ColorInterface $color
     * @param bool           $fill
     * @param int            $thickness
     *
     * @throws RuntimeException
     *
     * @return DrawerInterface
     */
    public function pieSlice(PointInterface $center, BoxInterface $size, $start, $end, ColorInterface $color, $fill = false, $thickness = 1);

    /**
     * Places a one pixel point at specific coordinates and fills it with
     * specified color.
     *
     * @param PointInterface $position
     * @param ColorInterface $color
     *
     * @throws RuntimeException
     *
     * @return DrawerInterface
     */
    public function dot(PointInterface $position, ColorInterface $color);

    /**
     * Draws a polygon using array of x, y coordinates. Must contain at least
     * three coordinates.
     *
     * @param array          $coordinates
     * @param ColorInterface $color
     * @param bool           $fill
     * @param int            $thickness
     *
     * @throws RuntimeException
     *
     * @return DrawerInterface
     */
    public function polygon(array $coordinates, ColorInterface $color, $fill = false, $thickness = 1);

    /**
     * Annotates image with specified text at a given position starting on the
     * top left of the final text box.
     *
     * The rotation is done CW
     *
     * @param string         $string
     * @param AbstractFont   $font
     * @param PointInterface $position
     * @param int            $angle
     * @param int            $width
     *
     * @throws RuntimeException
     *
     * @return DrawerInterface
     */
    public function text($string, AbstractFont $font, PointInterface $position, $angle = 0, $width = null);
}
