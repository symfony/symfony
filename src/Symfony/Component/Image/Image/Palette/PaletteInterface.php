<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Image\Image\Palette;

use Symfony\Component\Image\Image\ProfileInterface;
use Symfony\Component\Image\Image\Palette\Color\ColorInterface;

interface PaletteInterface
{
    const PALETTE_GRAYSCALE = 'gray';
    const PALETTE_RGB = 'rgb';
    const PALETTE_CMYK = 'cmyk';

    /**
     * Returns a color given some values
     *
     * @param string|array|integer $color A color
     * @param integer|null         $alpha Set alpha to null to disable it
     *
     * @return ColorInterface
     *
     * @throws InvalidArgumentException In case you pass an alpha value to a
     *                                  Palette that does not support alpha
     */
    public function color($color, $alpha = null);

    /**
     * Blend two colors given an amount
     *
     * @param ColorInterface $color1
     * @param ColorInterface $color2
     * @param float          $amount The amount of color2 in color1
     *
     * @return ColorInterface
     */
    public function blend(ColorInterface $color1, ColorInterface $color2, $amount);

    /**
     * Attachs an ICC profile to this Palette.
     *
     * (A default profile is provided by default)
     *
     * @param ProfileInterface $profile
     *
     * @return PaletteInterface
     */
    public function useProfile(ProfileInterface $profile);

    /**
     * Returns the ICC profile attached to this Palette.
     *
     * @return ProfileInterface
     */
    public function profile();

    /**
     * Returns the name of this Palette, one of PaletteInterface::PALETTE_*
     * constants
     *
     * @return String
     */
    public function name();

    /**
     * Returns an array containing ColorInterface::COLOR_* constants that
     * define the structure of colors for a pixel.
     *
     * @return array
     */
    public function pixelDefinition();

    /**
     * Tells if alpha channel is supported in this palette
     *
     * @return Boolean
     */
    public function supportsAlpha();
}
