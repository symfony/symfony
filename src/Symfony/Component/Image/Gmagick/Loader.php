<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Image\Gmagick;

use Symfony\Component\Image\Image\AbstractLoader;
use Symfony\Component\Image\Exception\NotSupportedException;
use Symfony\Component\Image\Image\BoxInterface;
use Symfony\Component\Image\Image\Metadata\MetadataBag;
use Symfony\Component\Image\Image\Palette\Color\ColorInterface;
use Symfony\Component\Image\Image\Palette\Grayscale;
use Symfony\Component\Image\Image\Palette\CMYK;
use Symfony\Component\Image\Image\Palette\RGB;
use Symfony\Component\Image\Image\Palette\Color\CMYK as CMYKColor;
use Symfony\Component\Image\Exception\InvalidArgumentException;
use Symfony\Component\Image\Exception\RuntimeException;

/**
 * Loader implementation using the Gmagick PHP extension.
 */
class Loader extends AbstractLoader
{
    /**
     * @throws RuntimeException
     */
    public function __construct()
    {
        if (!class_exists('Gmagick')) {
            throw new RuntimeException('Gmagick not installed');
        }
    }

    /**
     * {@inheritdoc}
     */
    public function open($path)
    {
        $path = $this->checkPath($path);

        try {
            $gmagick = new \Gmagick($path);
            $image = new Image($gmagick, $this->createPalette($gmagick), $this->getMetadataReader()->readFile($path));
        } catch (\GmagickException $e) {
            throw new RuntimeException(sprintf('Unable to open image %s', $path), $e->getCode(), $e);
        }

        return $image;
    }

    /**
     * {@inheritdoc}
     */
    public function create(BoxInterface $size, ColorInterface $color = null)
    {
        $width = $size->getWidth();
        $height = $size->getHeight();

        $palette = null !== $color ? $color->getPalette() : new RGB();
        $color = null !== $color ? $color : $palette->color('fff');

        try {
            $gmagick = new \Gmagick();
            // Gmagick does not support creation of CMYK GmagickPixel
            // see https://bugs.php.net/bug.php?id=64466
            if ($color instanceof CMYKColor) {
                $switchPalette = $palette;
                $palette = new RGB();
                $pixel = new \GmagickPixel($palette->color((string) $color));
            } else {
                $switchPalette = null;
                $pixel = new \GmagickPixel((string) $color);
            }

            if ($color->getPalette()->supportsAlpha() && $color->getAlpha() < 100) {
                throw new NotSupportedException('alpha transparency is not supported');
            }

            $gmagick->newimage($width, $height, $pixel->getcolor(false));
            $gmagick->setimagecolorspace(\Gmagick::COLORSPACE_TRANSPARENT);
            $gmagick->setimagebackgroundcolor($pixel);

            $image = new Image($gmagick, $palette, new MetadataBag());

            if ($switchPalette) {
                $image->usePalette($switchPalette);
            }

            return $image;
        } catch (\GmagickException $e) {
            throw new RuntimeException('Could not create empty image', $e->getCode(), $e);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function load($string)
    {
        return $this->doLoad($string, $this->getMetadataReader()->readData($string));
    }

    /**
     * {@inheritdoc}
     */
    public function read($resource)
    {
        if (!is_resource($resource)) {
            throw new InvalidArgumentException('Variable does not contain a stream resource');
        }

        $content = stream_get_contents($resource);

        if (false === $content) {
            throw new InvalidArgumentException('Couldn\'t read given resource');
        }

        return $this->doLoad($content, $this->getMetadataReader()->readData($content, $resource));
    }

    /**
     * {@inheritdoc}
     */
    public function font($file, $size, ColorInterface $color)
    {
        $gmagick = new \Gmagick();
        $gmagick->newimage(1, 1, 'transparent');

        return new Font($gmagick, $file, $size, $color);
    }

    private function createPalette(\Gmagick $gmagick)
    {
        switch ($gmagick->getimagecolorspace()) {
            case \Gmagick::COLORSPACE_SRGB:
            case \Gmagick::COLORSPACE_RGB:
                return new RGB();
            case \Gmagick::COLORSPACE_CMYK:
                return new CMYK();
            case \Gmagick::COLORSPACE_GRAY:
                return new Grayscale();
            default:
                throw new NotSupportedException('Only RGB and CMYK colorspace are currently supported');
        }
    }

    private function doLoad($content, MetadataBag $metadata)
    {
        try {
            $gmagick = new \Gmagick();
            $gmagick->readimageblob($content);
        } catch (\GmagickException $e) {
            throw new RuntimeException('Could not load image from string', $e->getCode(), $e);
        }

        return new Image($gmagick, $this->createPalette($gmagick), $metadata);
    }
}
