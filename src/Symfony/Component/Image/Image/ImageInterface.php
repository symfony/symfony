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

use Symfony\Component\Image\Draw\DrawerInterface;
use Symfony\Component\Image\Effects\EffectsInterface;
use Symfony\Component\Image\Image\Palette\PaletteInterface;
use Symfony\Component\Image\Image\Palette\Color\ColorInterface;
use Symfony\Component\Image\Exception\RuntimeException;
use Symfony\Component\Image\Exception\OutOfBoundsException;

/**
 * The image interface
 */
interface ImageInterface extends ManipulatorInterface
{
    const RESOLUTION_PIXELSPERINCH = 'ppi';
    const RESOLUTION_PIXELSPERCENTIMETER = 'ppc';

    const INTERLACE_NONE = 'none';
    const INTERLACE_LINE = 'line';
    const INTERLACE_PLANE = 'plane';
    const INTERLACE_PARTITION = 'partition';

    const FILTER_UNDEFINED = 'undefined';
    const FILTER_POINT = 'point';
    const FILTER_BOX = 'box';
    const FILTER_TRIANGLE = 'triangle';
    const FILTER_HERMITE = 'hermite';
    const FILTER_HANNING = 'hanning';
    const FILTER_HAMMING = 'hamming';
    const FILTER_BLACKMAN = 'blackman';
    const FILTER_GAUSSIAN = 'gaussian';
    const FILTER_QUADRATIC = 'quadratic';
    const FILTER_CUBIC = 'cubic';
    const FILTER_CATROM = 'catrom';
    const FILTER_MITCHELL = 'mitchell';
    const FILTER_LANCZOS = 'lanczos';
    const FILTER_BESSEL = 'bessel';
    const FILTER_SINC = 'sinc';

    /**
     * Returns the image content as a binary string
     *
     * @param string $format
     * @param array  $options
     *
     * @throws RuntimeException
     *
     * @return string binary
     */
    public function get($format, array $options = array());

    /**
     * Returns the image content as a PNG binary string
     *
     * @throws RuntimeException
     *
     * @return string binary
     */
    public function __toString();

    /**
     * Instantiates and returns a DrawerInterface instance for image drawing
     *
     * @return DrawerInterface
     */
    public function draw();

    /**
     * @return EffectsInterface
     */
    public function effects();

    /**
     * Returns current image size
     *
     * @return BoxInterface
     */
    public function getSize();

    /**
     * Transforms creates a grayscale mask from current image, returns a new
     * image, while keeping the existing image unmodified
     *
     * @return ImageInterface
     */
    public function mask();

    /**
     * Returns array of image colors as Symfony\Component\Image\Image\Palette\Color\ColorInterface instances
     *
     * @return array
     */
    public function histogram();

    /**
     * Returns color at specified positions of current image
     *
     * @param PointInterface $point
     *
     * @throws RuntimeException
     *
     * @return ColorInterface
     */
    public function getColorAt(PointInterface $point);

    /**
     * Returns the image layers when applicable.
     *
     * @throws RuntimeException     In case the layer can not be returned
     * @throws OutOfBoundsException In case the index is not a valid value
     *
     * @return LayersInterface
     */
    public function layers();

    /**
     * Enables or disables interlacing
     *
     * @param string $scheme
     *
     * @throws InvalidArgumentException When an unsupported Interface type is supplied
     *
     * @return ImageInterface
     */
    public function interlace($scheme);

    /**
     * Return the current color palette
     *
     * @return PaletteInterface
     */
    public function palette();

    /**
     * Set a palette for the image. Useful to change colorspace.
     *
     * @param PaletteInterface $palette
     *
     * @return ImageInterface
     *
     * @throws RuntimeException
     */
    public function usePalette(PaletteInterface $palette);

    /**
     * Applies a color profile on the Image
     *
     * @param ProfileInterface $profile
     *
     * @return ImageInterface
     *
     * @throws RuntimeException
     */
    public function profile(ProfileInterface $profile);

    /**
     * Returns the Image's meta data
     *
     * @return Metadata\MetadataBag
     */
    public function metadata();
}
