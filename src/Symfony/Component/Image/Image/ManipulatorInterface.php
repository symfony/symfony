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

use Symfony\Component\Image\Exception\InvalidArgumentException;
use Symfony\Component\Image\Exception\OutOfBoundsException;
use Symfony\Component\Image\Exception\RuntimeException;
use Symfony\Component\Image\Image\Palette\Color\ColorInterface;
use Symfony\Component\Image\Image\Fill\FillInterface;

/**
 * The manipulator interface.
 */
interface ManipulatorInterface
{
    const THUMBNAIL_INSET = 'inset';
    const THUMBNAIL_OUTBOUND = 'outbound';

    /**
     * Copies current source image into a new ImageInterface instance.
     *
     * @throws RuntimeException
     *
     * @return static
     */
    public function copy();

    /**
     * Crops a specified box out of the source image (modifies the source image)
     * Returns cropped self.
     *
     * @param PointInterface $start
     * @param BoxInterface   $size
     *
     * @throws OutOfBoundsException
     * @throws RuntimeException
     *
     * @return static
     */
    public function crop(PointInterface $start, BoxInterface $size);

    /**
     * Resizes current image and returns self.
     *
     * @param BoxInterface $size
     * @param string       $filter
     *
     * @throws RuntimeException
     *
     * @return static
     */
    public function resize(BoxInterface $size, $filter = ImageInterface::FILTER_UNDEFINED);

    /**
     * Rotates an image at the given angle.
     * Optional $background can be used to specify the fill color of the empty
     * area of rotated image.
     *
     * @param int            $angle
     * @param ColorInterface $background
     *
     * @throws RuntimeException
     *
     * @return static
     */
    public function rotate($angle, ColorInterface $background = null);

    /**
     * Pastes an image into a parent image
     * Throws exceptions if image exceeds parent image borders or if paste
     * operation fails.
     *
     * Returns source image
     *
     * @param ImageInterface $image
     * @param PointInterface $start
     *
     * @throws InvalidArgumentException
     * @throws OutOfBoundsException
     * @throws RuntimeException
     *
     * @return static
     */
    public function paste(ImageInterface $image, PointInterface $start);

    /**
     * Saves the image at a specified path, the target file extension is used
     * to determine file format, only jpg, jpeg, gif, png, wbmp and xbm are
     * supported.
     *
     * @param string $path
     * @param array  $options
     *
     * @throws RuntimeException
     *
     * @return static
     */
    public function save($path = null, array $options = array());

    /**
     * Outputs the image content.
     *
     * @param string $format
     * @param array  $options
     *
     * @throws RuntimeException
     *
     * @return static
     */
    public function show($format, array $options = array());

    /**
     * Flips current image using horizontal axis.
     *
     * @throws RuntimeException
     *
     * @return static
     */
    public function flipHorizontally();

    /**
     * Flips current image using vertical axis.
     *
     * @throws RuntimeException
     *
     * @return static
     */
    public function flipVertically();

    /**
     * Remove all profiles and comments.
     *
     * @throws RuntimeException
     *
     * @return static
     */
    public function strip();

    /**
     * Generates a thumbnail from a current image
     * Returns it as a new image, doesn't modify the current image.
     *
     * @param BoxInterface $size
     * @param string       $mode
     * @param string       $filter The filter to use for resizing, one of ImageInterface::FILTER_*
     *
     * @throws RuntimeException
     *
     * @return static
     */
    public function thumbnail(BoxInterface $size, $mode = self::THUMBNAIL_INSET, $filter = ImageInterface::FILTER_UNDEFINED);

    /**
     * Applies a given mask to current image's alpha channel.
     *
     * @param ImageInterface $mask
     *
     * @return static
     */
    public function applyMask(ImageInterface $mask);

    /**
     * Fills image with provided filling, by replacing each pixel's color in
     * the current image with corresponding color from FillInterface, and
     * returns modified image.
     *
     * @param FillInterface $fill
     *
     * @return static
     */
    public function fill(FillInterface $fill);
}
