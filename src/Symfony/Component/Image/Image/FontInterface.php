<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Image\Image;

use Symfony\Component\Image\Image\Palette\Color\ColorInterface;

/**
 * The font interface
 */
interface FontInterface
{
    /**
     * Gets the fontfile for current font
     *
     * @return string
     */
    public function getFile();

    /**
     * Gets font's integer point size
     *
     * @return integer
     */
    public function getSize();

    /**
     * Gets font's color
     *
     * @return ColorInterface
     */
    public function getColor();

    /**
     * Gets BoxInterface of font size on the image based on string and angle
     *
     * @param string  $string
     * @param integer $angle
     *
     * @return BoxInterface
     */
    public function box($string, $angle = 0);
}
