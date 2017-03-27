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
use Symfony\Component\Image\Exception\InvalidArgumentException;
use Symfony\Component\Image\Exception\RuntimeException;

/**
 * The loader interface
 */
interface LoaderInterface
{
    /**
     * Creates a new empty image with an optional background color
     *
     * @param BoxInterface   $size
     * @param ColorInterface $color
     *
     * @throws InvalidArgumentException
     * @throws RuntimeException
     *
     * @return ImageInterface
     */
    public function create(BoxInterface $size, ColorInterface $color = null);

    /**
     * Opens an existing image from $path
     *
     * @param string $path
     *
     * @throws RuntimeException
     *
     * @return ImageInterface
     */
    public function open($path);

    /**
     * Loads an image from a binary $string
     *
     * @param string $string
     *
     * @throws RuntimeException
     *
     * @return ImageInterface
     */
    public function load($string);

    /**
     * Loads an image from a resource $resource
     *
     * @param resource $resource
     *
     * @throws RuntimeException
     *
     * @return ImageInterface
     */
    public function read($resource);

    /**
     * Constructs a font with specified $file, $size and $color
     *
     * The font size is to be specified in points (e.g. 10pt means 10)
     *
     * @param string         $file
     * @param integer        $size
     * @param ColorInterface $color
     *
     * @return FontInterface
     */
    public function font($file, $size, ColorInterface $color);
}
