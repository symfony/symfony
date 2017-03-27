<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Image\Gd;

use Symfony\Component\Image\Image\AbstractImage;
use Symfony\Component\Image\Image\ImageInterface;
use Symfony\Component\Image\Image\Box;
use Symfony\Component\Image\Image\BoxInterface;
use Symfony\Component\Image\Image\Metadata\MetadataBag;
use Symfony\Component\Image\Image\Palette\Color\ColorInterface;
use Symfony\Component\Image\Image\Fill\FillInterface;
use Symfony\Component\Image\Image\Point;
use Symfony\Component\Image\Image\PointInterface;
use Symfony\Component\Image\Image\Palette\PaletteInterface;
use Symfony\Component\Image\Image\Palette\Color\RGB as RGBColor;
use Symfony\Component\Image\Image\ProfileInterface;
use Symfony\Component\Image\Image\Palette\RGB;
use Symfony\Component\Image\Exception\InvalidArgumentException;
use Symfony\Component\Image\Exception\OutOfBoundsException;
use Symfony\Component\Image\Exception\RuntimeException;

/**
 * Image implementation using the GD library.
 */
final class Image extends AbstractImage
{
    /**
     * @var resource
     */
    private $resource;

    /**
     * @var Layers|null
     */
    private $layers;

    /**
     * @var PaletteInterface
     */
    private $palette;

    /**
     * Constructs a new Image instance.
     *
     * @param resource         $resource
     * @param PaletteInterface $palette
     * @param MetadataBag      $metadata
     */
    public function __construct($resource, PaletteInterface $palette, MetadataBag $metadata)
    {
        $this->metadata = $metadata;
        $this->palette = $palette;
        $this->resource = $resource;
    }

    /**
     * Makes sure the current image resource is destroyed.
     */
    public function __destruct()
    {
        if (is_resource($this->resource) && 'gd' === get_resource_type($this->resource)) {
            imagedestroy($this->resource);
        }
    }

    /**
     * Returns Gd resource.
     *
     * @return resource
     */
    public function getGdResource()
    {
        return $this->resource;
    }

    /**
     * {@inheritdoc}
     *
     * @return ImageInterface
     */
    final public function copy()
    {
        $size = $this->getSize();
        $copy = $this->createImage($size, 'copy');

        if (false === imagecopy($copy, $this->resource, 0, 0, 0, 0, $size->getWidth(), $size->getHeight())) {
            throw new RuntimeException('Image copy operation failed');
        }

        return new self($copy, $this->palette, $this->metadata);
    }

    /**
     * {@inheritdoc}
     *
     * @return ImageInterface
     */
    final public function crop(PointInterface $start, BoxInterface $size)
    {
        if (!$start->in($this->getSize())) {
            throw new OutOfBoundsException('Crop coordinates must start at minimum 0, 0 position from top  left corner, crop height and width must be positive integers and must not exceed the current image borders');
        }

        $width = $size->getWidth();
        $height = $size->getHeight();

        $dest = $this->createImage($size, 'crop');

        if (false === imagecopy($dest, $this->resource, 0, 0, $start->getX(), $start->getY(), $width, $height)) {
            throw new RuntimeException('Image crop operation failed');
        }

        imagedestroy($this->resource);

        $this->resource = $dest;

        return $this;
    }

    /**
     * {@inheritdoc}
     *
     * @return ImageInterface
     */
    final public function paste(ImageInterface $image, PointInterface $start)
    {
        if (!$image instanceof self) {
            throw new InvalidArgumentException(sprintf('Gd\Image can only paste() Gd\Image instances, %s given', get_class($image)));
        }

        $size = $image->getSize();
        if (!$this->getSize()->contains($size, $start)) {
            throw new OutOfBoundsException('Cannot paste image of the given size at the specified position, as it moves outside of the current image\'s box');
        }

        imagealphablending($this->resource, true);
        imagealphablending($image->resource, true);

        if (false === imagecopy($this->resource, $image->resource, $start->getX(), $start->getY(), 0, 0, $size->getWidth(), $size->getHeight())) {
            throw new RuntimeException('Image paste operation failed');
        }

        imagealphablending($this->resource, false);
        imagealphablending($image->resource, false);

        return $this;
    }

    /**
     * {@inheritdoc}
     *
     * @return ImageInterface
     */
    final public function resize(BoxInterface $size, $filter = ImageInterface::FILTER_UNDEFINED)
    {
        if (ImageInterface::FILTER_UNDEFINED !== $filter) {
            throw new InvalidArgumentException('Unsupported filter type, GD only supports ImageInterface::FILTER_UNDEFINED filter');
        }

        $width = $size->getWidth();
        $height = $size->getHeight();

        $dest = $this->createImage($size, 'resize');

        imagealphablending($this->resource, true);
        imagealphablending($dest, true);

        if (false === imagecopyresampled($dest, $this->resource, 0, 0, 0, 0, $width, $height, imagesx($this->resource), imagesy($this->resource))) {
            throw new RuntimeException('Image resize operation failed');
        }

        imagealphablending($this->resource, false);
        imagealphablending($dest, false);

        imagedestroy($this->resource);

        $this->resource = $dest;

        return $this;
    }

    /**
     * {@inheritdoc}
     *
     * @return ImageInterface
     */
    final public function rotate($angle, ColorInterface $background = null)
    {
        $color = $background ? $background : $this->palette->color('fff');
        $resource = imagerotate($this->resource, -1 * $angle, $this->getColor($color));

        if (false === $resource) {
            throw new RuntimeException('Image rotate operation failed');
        }

        imagedestroy($this->resource);
        $this->resource = $resource;

        return $this;
    }

    /**
     * {@inheritdoc}
     *
     * @return ImageInterface
     */
    final public function save($path = null, array $options = array())
    {
        $path = null === $path ? (isset($this->metadata['filepath']) ? $this->metadata['filepath'] : $path) : $path;

        if (null === $path) {
            throw new RuntimeException('You can omit save path only if image has been open from a file');
        }

        if (isset($options['format'])) {
            $format = $options['format'];
        } elseif ('' !== $extension = pathinfo($path, \PATHINFO_EXTENSION)) {
            $format = $extension;
        } else {
            $originalPath = isset($this->metadata['filepath']) ? $this->metadata['filepath'] : null;
            $format = pathinfo($originalPath, \PATHINFO_EXTENSION);
        }

        $this->saveOrOutput($format, $options, $path);

        return $this;
    }

    /**
     * {@inheritdoc}
     *
     * @return ImageInterface
     */
    public function show($format, array $options = array())
    {
        header('Content-type: '.$this->getMimeType($format));

        $this->saveOrOutput($format, $options);

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function get($format, array $options = array())
    {
        ob_start();
        $this->saveOrOutput($format, $options);

        return ob_get_clean();
    }

    /**
     * {@inheritdoc}
     */
    public function __toString()
    {
        return $this->get('png');
    }

    /**
     * {@inheritdoc}
     *
     * @return ImageInterface
     */
    final public function flipHorizontally()
    {
        $size = $this->getSize();
        $width = $size->getWidth();
        $height = $size->getHeight();
        $dest = $this->createImage($size, 'flip');

        for ($i = 0; $i < $width; ++$i) {
            if (false === imagecopy($dest, $this->resource, $i, 0, ($width - 1) - $i, 0, 1, $height)) {
                throw new RuntimeException('Horizontal flip operation failed');
            }
        }

        imagedestroy($this->resource);

        $this->resource = $dest;

        return $this;
    }

    /**
     * {@inheritdoc}
     *
     * @return ImageInterface
     */
    final public function flipVertically()
    {
        $size = $this->getSize();
        $width = $size->getWidth();
        $height = $size->getHeight();
        $dest = $this->createImage($size, 'flip');

        for ($i = 0; $i < $height; ++$i) {
            if (false === imagecopy($dest, $this->resource, 0, $i, 0, ($height - 1) - $i, $width, 1)) {
                throw new RuntimeException('Vertical flip operation failed');
            }
        }

        imagedestroy($this->resource);

        $this->resource = $dest;

        return $this;
    }

    /**
     * {@inheritdoc}
     *
     * @return ImageInterface
     */
    final public function strip()
    {
        // GD strips profiles and comment, so there's nothing to do here
        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function draw()
    {
        return new Drawer($this->resource);
    }

    /**
     * {@inheritdoc}
     */
    public function effects()
    {
        return new Effects($this->resource);
    }

    /**
     * {@inheritdoc}
     */
    public function getSize()
    {
        return new Box(imagesx($this->resource), imagesy($this->resource));
    }

    /**
     * {@inheritdoc}
     *
     * @return ImageInterface
     */
    public function applyMask(ImageInterface $mask)
    {
        if (!$mask instanceof self) {
            throw new InvalidArgumentException('Cannot mask non-gd images');
        }

        $size = $this->getSize();
        $maskSize = $mask->getSize();

        if ($size != $maskSize) {
            throw new InvalidArgumentException(sprintf('The given mask doesn\'t match current image\'s size, Current mask\'s dimensions are %s, while image\'s dimensions are %s', $maskSize, $size));
        }

        for ($x = 0, $width = $size->getWidth(); $x < $width; ++$x) {
            for ($y = 0, $height = $size->getHeight(); $y < $height; ++$y) {
                $position = new Point($x, $y);
                $color = $this->getColorAt($position);
                $maskColor = $mask->getColorAt($position);
                $round = (int) round(max($color->getAlpha(), (100 - $color->getAlpha()) * $maskColor->getRed() / 255));

                if (false === imagesetpixel($this->resource, $x, $y, $this->getColor($color->dissolve($round - $color->getAlpha())))) {
                    throw new RuntimeException('Apply mask operation failed');
                }
            }
        }

        return $this;
    }

    /**
     * {@inheritdoc}
     *
     * @return ImageInterface
     */
    public function fill(FillInterface $fill)
    {
        $size = $this->getSize();

        for ($x = 0, $width = $size->getWidth(); $x < $width; ++$x) {
            for ($y = 0, $height = $size->getHeight(); $y < $height; ++$y) {
                if (false === imagesetpixel($this->resource, $x, $y, $this->getColor($fill->getColor(new Point($x, $y))))) {
                    throw new RuntimeException('Fill operation failed');
                }
            }
        }

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function mask()
    {
        $mask = $this->copy();

        if (false === imagefilter($mask->resource, IMG_FILTER_GRAYSCALE)) {
            throw new RuntimeException('Mask operation failed');
        }

        return $mask;
    }

    /**
     * {@inheritdoc}
     */
    public function histogram()
    {
        $size = $this->getSize();
        $colors = array();

        for ($x = 0, $width = $size->getWidth(); $x < $width; ++$x) {
            for ($y = 0, $height = $size->getHeight(); $y < $height; ++$y) {
                $colors[] = $this->getColorAt(new Point($x, $y));
            }
        }

        return array_unique($colors);
    }

    /**
     * {@inheritdoc}
     */
    public function getColorAt(PointInterface $point)
    {
        if (!$point->in($this->getSize())) {
            throw new RuntimeException(sprintf('Error getting color at point [%s,%s]. The point must be inside the image of size [%s,%s]', $point->getX(), $point->getY(), $this->getSize()->getWidth(), $this->getSize()->getHeight()));
        }

        $index = imagecolorat($this->resource, $point->getX(), $point->getY());
        $info = imagecolorsforindex($this->resource, $index);

        return $this->palette->color(array($info['red'], $info['green'], $info['blue']), max(min(100 - (int) round($info['alpha'] / 127 * 100), 100), 0));
    }

    /**
     * {@inheritdoc}
     */
    public function layers()
    {
        if (null === $this->layers) {
            $this->layers = new Layers($this, $this->palette, $this->resource);
        }

        return $this->layers;
    }

    /**
     * {@inheritdoc}
     **/
    public function interlace($scheme)
    {
        static $supportedInterlaceSchemes = array(
            ImageInterface::INTERLACE_NONE => 0,
            ImageInterface::INTERLACE_LINE => 1,
            ImageInterface::INTERLACE_PLANE => 1,
            ImageInterface::INTERLACE_PARTITION => 1,
        );

        if (!array_key_exists($scheme, $supportedInterlaceSchemes)) {
            throw new InvalidArgumentException('Unsupported interlace type');
        }

        imageinterlace($this->resource, $supportedInterlaceSchemes[$scheme]);

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function palette()
    {
        return $this->palette;
    }

    /**
     * {@inheritdoc}
     */
    public function profile(ProfileInterface $profile)
    {
        throw new RuntimeException('GD driver does not support color profiles');
    }

    /**
     * {@inheritdoc}
     */
    public function usePalette(PaletteInterface $palette)
    {
        if (!$palette instanceof RGB) {
            throw new RuntimeException('GD driver only supports RGB palette');
        }

        $this->palette = $palette;

        return $this;
    }

    /**
     * Performs save or show operation using one of GD's image... functions.
     *
     * @param string $format
     * @param array  $options
     * @param string $filename
     *
     * @throws InvalidArgumentException
     * @throws RuntimeException
     */
    private function saveOrOutput($format, array $options, $filename = null)
    {
        $format = $this->normalizeFormat($format);

        if (!$this->supported($format)) {
            throw new InvalidArgumentException(sprintf('Saving image in "%s" format is not supported, please use one of the following extensions: "%s"', $format, implode('", "', $this->supported())));
        }

        $save = 'image'.$format;
        $args = array(&$this->resource, $filename);

        $options = $this->updateSaveOptions($options);

        if ($format === 'jpeg' && isset($options['jpeg_quality'])) {
            $args[] = $options['jpeg_quality'];
        }

        if ($format === 'png') {
            if (isset($options['png_compression_level'])) {
                if ($options['png_compression_level'] < 0 || $options['png_compression_level'] > 9) {
                    throw new InvalidArgumentException('png_compression_level option should be an integer from 0 to 9');
                }
                $args[] = $options['png_compression_level'];
            } else {
                $args[] = -1; // use default level
            }

            if (isset($options['png_compression_filter'])) {
                if (~PNG_ALL_FILTERS & $options['png_compression_filter']) {
                    throw new InvalidArgumentException('png_compression_filter option should be a combination of the PNG_FILTER_XXX constants');
                }
                $args[] = $options['png_compression_filter'];
            }
        }

        if (($format === 'wbmp' || $format === 'xbm') && isset($options['foreground'])) {
            $args[] = $options['foreground'];
        }

        set_error_handler(function ($errno, $errstr, $errfile, $errline) {
            if (0 === error_reporting()) {
                return;
            }

            throw new RuntimeException($errstr, $errno, new \ErrorException($errstr, 0, $errno, $errfile, $errline));
        });

        try {
            if (false === call_user_func_array($save, $args)) {
                throw new RuntimeException('Save operation failed');
            }
        } finally {
            restore_error_handler();
        }
    }

    /**
     * Generates a GD image.
     *
     * @param BoxInterface $size
     * @param  string the operation initiating the creation
     *
     * @return resource
     *
     * @throws RuntimeException
     */
    private function createImage(BoxInterface $size, $operation)
    {
        $resource = imagecreatetruecolor($size->getWidth(), $size->getHeight());

        if (false === $resource) {
            throw new RuntimeException('Image '.$operation.' failed');
        }

        if (false === imagealphablending($resource, false) || false === imagesavealpha($resource, true)) {
            throw new RuntimeException('Image '.$operation.' failed');
        }

        if (function_exists('imageantialias')) {
            imageantialias($resource, true);
        }

        $transparent = imagecolorallocatealpha($resource, 255, 255, 255, 127);
        imagefill($resource, 0, 0, $transparent);
        imagecolortransparent($resource, $transparent);

        return $resource;
    }

    /**
     * Generates a GD color from Color instance.
     *
     * @param ColorInterface $color
     *
     * @return int A color identifier
     *
     * @throws RuntimeException
     * @throws InvalidArgumentException
     */
    private function getColor(ColorInterface $color)
    {
        if (!$color instanceof RGBColor) {
            throw new InvalidArgumentException('GD driver only supports RGB colors');
        }

        $index = imagecolorallocatealpha($this->resource, $color->getRed(), $color->getGreen(), $color->getBlue(), round(127 * (100 - $color->getAlpha()) / 100));

        if (false === $index) {
            throw new RuntimeException(sprintf('Unable to allocate color "RGB(%s, %s, %s)" with transparency of %d percent', $color->getRed(), $color->getGreen(), $color->getBlue(), $color->getAlpha()));
        }

        return $index;
    }

    /**
     * Normalizes a given format name.
     *
     * @param string $format
     *
     * @return string
     */
    private function normalizeFormat($format)
    {
        $format = strtolower($format);

        if ('jpg' === $format || 'pjpeg' === $format) {
            $format = 'jpeg';
        }

        return $format;
    }

    /**
     * Checks whether a given format is supported by GD library.
     *
     * @param string $format
     *
     * @return bool
     */
    private function supported($format = null)
    {
        $formats = array('gif', 'jpeg', 'png', 'wbmp', 'xbm');

        if (null === $format) {
            return $formats;
        }

        return in_array($format, $formats);
    }

    /**
     * Get the mime type based on format.
     *
     * @param string $format
     *
     * @return string mime-type
     *
     * @throws RuntimeException
     */
    private function getMimeType($format)
    {
        $format = $this->normalizeFormat($format);

        if (!$this->supported($format)) {
            throw new RuntimeException('Invalid format');
        }

        static $mimeTypes = array(
            'jpeg' => 'image/jpeg',
            'gif' => 'image/gif',
            'png' => 'image/png',
            'wbmp' => 'image/vnd.wap.wbmp',
            'xbm' => 'image/xbm',
        );

        return $mimeTypes[$format];
    }
}
