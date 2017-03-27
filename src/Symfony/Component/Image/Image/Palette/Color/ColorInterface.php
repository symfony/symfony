<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Image\Image\Palette\Color;

use Symfony\Component\Image\Image\Palette\PaletteInterface;

interface ColorInterface
{
    const COLOR_RED = 'red';
    const COLOR_GREEN = 'green';
    const COLOR_BLUE = 'blue';

    const COLOR_CYAN = 'cyan';
    const COLOR_MAGENTA = 'magenta';
    const COLOR_YELLOW = 'yellow';
    const COLOR_KEYLINE = 'keyline';

    const COLOR_GRAY = 'gray';

    /**
     * Return the value of one of the component.
     *
     * @param string $component One of the ColorInterface::COLOR_* component
     *
     * @return int
     */
    public function getValue($component);

    /**
     * Returns percentage of transparency of the color.
     *
     * @return int
     */
    public function getAlpha();

    /**
     * Returns the palette attached to the current color.
     *
     * @return PaletteInterface
     */
    public function getPalette();

    /**
     * Returns a copy of current color, incrementing the alpha channel by the
     * given amount.
     *
     * @param int $alpha
     *
     * @return ColorInterface
     */
    public function dissolve($alpha);

    /**
     * Returns a copy of the current color, lightened by the specified number
     * of shades.
     *
     * @param int $shade
     *
     * @return ColorInterface
     */
    public function lighten($shade);

    /**
     * Returns a copy of the current color, darkened by the specified number of
     * shades.
     *
     * @param int $shade
     *
     * @return ColorInterface
     */
    public function darken($shade);

    /**
     * Returns a gray related to the current color.
     *
     * @return ColorInterface
     */
    public function grayscale();

    /**
     * Checks if the current color is opaque.
     *
     * @return bool
     */
    public function isOpaque();
}
