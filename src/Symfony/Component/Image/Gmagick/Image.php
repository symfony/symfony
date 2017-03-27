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

use Symfony\Component\Image\Exception\OutOfBoundsException;
use Symfony\Component\Image\Exception\InvalidArgumentException;
use Symfony\Component\Image\Exception\RuntimeException;
use Symfony\Component\Image\Image\AbstractImage;
use Symfony\Component\Image\Image\Metadata\MetadataBag;
use Symfony\Component\Image\Image\Palette\PaletteInterface;
use Symfony\Component\Image\Image\ImageInterface;
use Symfony\Component\Image\Image\Box;
use Symfony\Component\Image\Image\BoxInterface;
use Symfony\Component\Image\Image\Palette\Color\ColorInterface;
use Symfony\Component\Image\Image\Fill\FillInterface;
use Symfony\Component\Image\Image\Point;
use Symfony\Component\Image\Image\PointInterface;
use Symfony\Component\Image\Image\ProfileInterface;

/**
 * Image implementation using the Gmagick PHP extension
 */
final class Image extends AbstractImage
{
    /**
     * @var \Gmagick
     */
    private $gmagick;
    /**
     * @var Layers
     */
    private $layers;

    /**
     * @var PaletteInterface
     */
    private $palette;

    private static $colorspaceMapping = array(
        PaletteInterface::PALETTE_CMYK => \Gmagick::COLORSPACE_CMYK,
        PaletteInterface::PALETTE_RGB  => \Gmagick::COLORSPACE_RGB,
    );

    /**
     * Constructs a new Image instance
     *
     * @param \Gmagick         $gmagick
     * @param PaletteInterface $palette
     * @param MetadataBag      $metadata
     */
    public function __construct(\Gmagick $gmagick, PaletteInterface $palette, MetadataBag $metadata)
    {
        $this->metadata = $metadata;
        $this->gmagick = $gmagick;
        $this->setColorspace($palette);
        $this->layers = new Layers($this, $this->palette, $this->gmagick);
    }

    /**
     * Destroys allocated gmagick resources
     */
    public function __destruct()
    {
        if ($this->gmagick instanceof \Gmagick) {
            $this->gmagick->clear();
            $this->gmagick->destroy();
        }
    }

    /**
     * Returns gmagick instance
     *
     * @return \Gmagick
     */
    public function getGmagick()
    {
        return $this->gmagick;
    }

    /**
     * {@inheritdoc}
     *
     * @return ImageInterface
     */
    public function copy()
    {
        return new self(clone $this->gmagick, $this->palette, clone $this->metadata);
    }

    /**
     * {@inheritdoc}
     *
     * @return ImageInterface
     */
    public function crop(PointInterface $start, BoxInterface $size)
    {
        if (!$start->in($this->getSize())) {
            throw new OutOfBoundsException('Crop coordinates must start at minimum 0, 0 position from top left corner, crop height and width must be positive integers and must not exceed the current image borders');
        }

        try {
            $this->gmagick->cropimage($size->getWidth(), $size->getHeight(), $start->getX(), $start->getY());
        } catch (\GmagickException $e) {
            throw new RuntimeException('Crop operation failed', $e->getCode(), $e);
        }

        return $this;
    }

    /**
     * {@inheritdoc}
     *
     * @return ImageInterface
     */
    public function flipHorizontally()
    {
        try {
            $this->gmagick->flopimage();
        } catch (\GmagickException $e) {
            throw new RuntimeException('Horizontal flip operation failed', $e->getCode(), $e);
        }

        return $this;
    }

    /**
     * {@inheritdoc}
     *
     * @return ImageInterface
     */
    public function flipVertically()
    {
        try {
            $this->gmagick->flipimage();
        } catch (\GmagickException $e) {
            throw new RuntimeException('Vertical flip operation failed', $e->getCode(), $e);
        }

        return $this;
    }

    /**
     * {@inheritdoc}
     *
     * @return ImageInterface
     */
    public function strip()
    {
        try {
            try {
                $this->profile($this->palette->profile());
            } catch (\Exception $e) {
                // here we discard setting the profile as the previous incorporated profile
                // is corrupted, let's now strip the image
            }
            $this->gmagick->stripimage();
        } catch (\GmagickException $e) {
            throw new RuntimeException('Strip operation failed', $e->getCode(), $e);
        }

        return $this;
    }

    /**
     * {@inheritdoc}
     *
     * @return ImageInterface
     */
    public function paste(ImageInterface $image, PointInterface $start)
    {
        if (!$image instanceof self) {
            throw new InvalidArgumentException(sprintf('Gmagick\Image can only paste() Gmagick\Image instances, %s given', get_class($image)));
        }

        if (!$this->getSize()->contains($image->getSize(), $start)) {
            throw new OutOfBoundsException('Cannot paste image of the given size at the specified position, as it moves outside of the current image\'s box');
        }

        try {
            $this->gmagick->compositeimage($image->gmagick, \Gmagick::COMPOSITE_DEFAULT, $start->getX(), $start->getY());
        } catch (\GmagickException $e) {
            throw new RuntimeException('Paste operation failed', $e->getCode(), $e);
        }

        return $this;
    }

    /**
     * {@inheritdoc}
     *
     * @return ImageInterface
     */
    public function resize(BoxInterface $size, $filter = ImageInterface::FILTER_UNDEFINED)
    {
        static $supportedFilters = array(
            ImageInterface::FILTER_UNDEFINED => \Gmagick::FILTER_UNDEFINED,
            ImageInterface::FILTER_BESSEL    => \Gmagick::FILTER_BESSEL,
            ImageInterface::FILTER_BLACKMAN  => \Gmagick::FILTER_BLACKMAN,
            ImageInterface::FILTER_BOX       => \Gmagick::FILTER_BOX,
            ImageInterface::FILTER_CATROM    => \Gmagick::FILTER_CATROM,
            ImageInterface::FILTER_CUBIC     => \Gmagick::FILTER_CUBIC,
            ImageInterface::FILTER_GAUSSIAN  => \Gmagick::FILTER_GAUSSIAN,
            ImageInterface::FILTER_HANNING   => \Gmagick::FILTER_HANNING,
            ImageInterface::FILTER_HAMMING   => \Gmagick::FILTER_HAMMING,
            ImageInterface::FILTER_HERMITE   => \Gmagick::FILTER_HERMITE,
            ImageInterface::FILTER_LANCZOS   => \Gmagick::FILTER_LANCZOS,
            ImageInterface::FILTER_MITCHELL  => \Gmagick::FILTER_MITCHELL,
            ImageInterface::FILTER_POINT     => \Gmagick::FILTER_POINT,
            ImageInterface::FILTER_QUADRATIC => \Gmagick::FILTER_QUADRATIC,
            ImageInterface::FILTER_SINC      => \Gmagick::FILTER_SINC,
            ImageInterface::FILTER_TRIANGLE  => \Gmagick::FILTER_TRIANGLE
        );

        if (!array_key_exists($filter, $supportedFilters)) {
            throw new InvalidArgumentException('Unsupported filter type');
        }

        try {
            $this->gmagick->resizeimage($size->getWidth(), $size->getHeight(), $supportedFilters[$filter], 1);
        } catch (\GmagickException $e) {
            throw new RuntimeException('Resize operation failed', $e->getCode(), $e);
        }

        return $this;
    }

    /**
     * {@inheritdoc}
     *
     * @return ImageInterface
     */
    public function rotate($angle, ColorInterface $background = null)
    {
        try {
            $background = $background ?: $this->palette->color('fff');
            $pixel = $this->getColor($background);

            $this->gmagick->rotateimage($pixel, $angle);

            unset($pixel);
        } catch (\GmagickException $e) {
            throw new RuntimeException('Rotate operation failed', $e->getCode(), $e);
        }

        return $this;
    }

    /**
     * Internal
     *
     * Applies options before save or output
     *
     * @param \Gmagick $image
     * @param array    $options
     * @param string   $path
     *
     * @throws InvalidArgumentException
     */
    private function applyImageOptions(\Gmagick $image, array $options, $path)
    {
        if (isset($options['format'])) {
            $format = $options['format'];
        } elseif ('' !== $extension = pathinfo($path, \PATHINFO_EXTENSION)) {
            $format = $extension;
        } else {
            $format = pathinfo($image->getImageFilename(), \PATHINFO_EXTENSION);
        }

        $format = strtolower($format);

        $options = $this->updateSaveOptions($options);

        if (isset($options['jpeg_quality']) && in_array($format, array('jpeg', 'jpg', 'pjpeg'))) {
            $image->setCompressionQuality($options['jpeg_quality']);
        }

        if ((isset($options['png_compression_level']) || isset($options['png_compression_filter'])) && $format === 'png') {
            // first digit: compression level (default: 7)
            if (isset($options['png_compression_level'])) {
                if ($options['png_compression_level'] < 0 || $options['png_compression_level'] > 9) {
                    throw new InvalidArgumentException('png_compression_level option should be an integer from 0 to 9');
                }
                $compression = $options['png_compression_level'] * 10;
            } else {
                $compression = 70;
            }

            // second digit: compression filter (default: 5)
            if (isset($options['png_compression_filter'])) {
                if ($options['png_compression_filter'] < 0 || $options['png_compression_filter'] > 9) {
                    throw new InvalidArgumentException('png_compression_filter option should be an integer from 0 to 9');
                }
                $compression += $options['png_compression_filter'];
            } else {
                $compression += 5;
            }

            $image->setCompressionQuality($compression);
        }

        if (isset($options['resolution-units']) && isset($options['resolution-x']) && isset($options['resolution-y'])) {
            if ($options['resolution-units'] == ImageInterface::RESOLUTION_PIXELSPERCENTIMETER) {
                $image->setimageunits(\Gmagick::RESOLUTION_PIXELSPERCENTIMETER);
            } elseif ($options['resolution-units'] == ImageInterface::RESOLUTION_PIXELSPERINCH) {
                $image->setimageunits(\Gmagick::RESOLUTION_PIXELSPERINCH);
            } else {
                throw new InvalidArgumentException('Unsupported image unit format');
            }

            $image->setimageresolution($options['resolution-x'], $options['resolution-y']);
        }
    }

    /**
     * {@inheritdoc}
     *
     * @return ImageInterface
     */
    public function save($path = null, array $options = array())
    {
        $path = null === $path ? $this->gmagick->getImageFilename() : $path;

        if ('' === trim($path)) {
            throw new RuntimeException('You can omit save path only if image has been open from a file');
        }

        try {
            $this->prepareOutput($options, $path);
            $allFrames = !isset($options['animated']) || false === $options['animated'];
            $this->gmagick->writeimage($path, $allFrames);
        } catch (\GmagickException $e) {
            throw new RuntimeException('Save operation failed', $e->getCode(), $e);
        }

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
        echo $this->get($format, $options);

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function get($format, array $options = array())
    {
        try {
            $options['format'] = $format;
            $this->prepareOutput($options);
        } catch (\GmagickException $e) {
            throw new RuntimeException('Get operation failed', $e->getCode(), $e);
        }

        return $this->gmagick->getimagesblob();
    }

    /**
     * @param array  $options
     * @param string $path
     */
    private function prepareOutput(array $options, $path = null)
    {
        if (isset($options['format'])) {
            $this->gmagick->setimageformat($options['format']);
        }

        if (isset($options['animated']) && true === $options['animated']) {
            $format = isset($options['format']) ? $options['format'] : 'gif';
            $delay = isset($options['animated.delay']) ? $options['animated.delay'] : null;
            $loops = isset($options['animated.loops']) ? $options['animated.loops'] : 0;

            $options['flatten'] = false;

            $this->layers->animate($format, $delay, $loops);
        } else {
            $this->layers->merge();
        }
        $this->applyImageOptions($this->gmagick, $options, $path);

        // flatten only if image has multiple layers
        if ((!isset($options['flatten']) || $options['flatten'] === true) && count($this->layers) > 1) {
            $this->flatten();
        }
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
     */
    public function draw()
    {
        return new Drawer($this->gmagick);
    }

    /**
     * {@inheritdoc}
     */
    public function effects()
    {
        return new Effects($this->gmagick);
    }

    /**
     * {@inheritdoc}
     */
    public function getSize()
    {
        try {
            $i = $this->gmagick->getimageindex();
            $this->gmagick->setimageindex(0); //rewind
            $width  = $this->gmagick->getimagewidth();
            $height = $this->gmagick->getimageheight();
            $this->gmagick->setimageindex($i);
        } catch (\GmagickException $e) {
            throw new RuntimeException('Get size operation failed', $e->getCode(), $e);
        }

        return new Box($width, $height);
    }

    /**
     * {@inheritdoc}
     *
     * @return ImageInterface
     */
    public function applyMask(ImageInterface $mask)
    {
        if (!$mask instanceof self) {
            throw new InvalidArgumentException('Can only apply instances of Symfony\Component\Image\Gmagick\Image as masks');
        }

        $size = $this->getSize();
        $maskSize = $mask->getSize();

        if ($size != $maskSize) {
            throw new InvalidArgumentException(sprintf('The given mask doesn\'t match current image\'s size, current mask\'s dimensions are %s, while image\'s dimensions are %s', $maskSize, $size));
        }

        try {
            $mask = $mask->copy();
            $this->gmagick->compositeimage($mask->gmagick, \Gmagick::COMPOSITE_DEFAULT, 0, 0);
        } catch (\GmagickException $e) {
            throw new RuntimeException('Apply mask operation failed', $e->getCode(), $e);
        }

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function mask()
    {
        $mask = $this->copy();

        try {
            $mask->gmagick->modulateimage(100, 0, 100);
        } catch (\GmagickException $e) {
            throw new RuntimeException('Mask operation failed', $e->getCode(), $e);
        }

        return $mask;
    }

    /**
     * {@inheritdoc}
     *
     * @return ImageInterface
     */
    public function fill(FillInterface $fill)
    {
        try {
            $draw = new \GmagickDraw();
            $size = $this->getSize();

            $w = $size->getWidth();
            $h = $size->getHeight();

            for ($x = 0; $x < $w; $x++) {
                for ($y = 0; $y < $h; $y++) {
                    $pixel = $this->getColor($fill->getColor(new Point($x, $y)));

                    $draw->setfillcolor($pixel);
                    $draw->point($x, $y);

                    $pixel = null;
                }
            }

            $this->gmagick->drawimage($draw);

            $draw = null;
        } catch (\GmagickException $e) {
            throw new RuntimeException('Fill operation failed', $e->getCode(), $e);
        }

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function histogram()
    {
        try {
            $pixels = $this->gmagick->getimagehistogram();
        } catch (\GmagickException $e) {
            throw new RuntimeException('Error while fetching histogram', $e->getCode(), $e);
        }

        $image = $this;

        return array_map(function (\GmagickPixel $pixel) use ($image) {
            return $image->pixelToColor($pixel);
        }, $pixels);
    }

    /**
     * {@inheritdoc}
     */
    public function getColorAt(PointInterface $point)
    {
        if (!$point->in($this->getSize())) {
            throw new InvalidArgumentException(sprintf('Error getting color at point [%s,%s]. The point must be inside the image of size [%s,%s]', $point->getX(), $point->getY(), $this->getSize()->getWidth(), $this->getSize()->getHeight()));
        }

        try {
            $cropped   = clone $this->gmagick;
            $histogram = $cropped
                ->cropImage(1, 1, $point->getX(), $point->getY())
                ->getImageHistogram();
        } catch (\GmagickException $e) {
            throw new RuntimeException('Unable to get the pixel', $e->getCode(), $e);
        }

        $pixel = array_shift($histogram);

        unset($histogram, $cropped);

        return $this->pixelToColor($pixel);
    }

    /**
     * Returns a color given a pixel, depending the Palette context
     *
     * Note : this method is public for PHP 5.3 compatibility
     *
     * @param \GmagickPixel $pixel
     *
     * @return ColorInterface
     *
     * @throws InvalidArgumentException In case a unknown color is requested
     */
    public function pixelToColor(\GmagickPixel $pixel)
    {
        static $colorMapping = array(
            ColorInterface::COLOR_RED     => \Gmagick::COLOR_RED,
            ColorInterface::COLOR_GREEN   => \Gmagick::COLOR_GREEN,
            ColorInterface::COLOR_BLUE    => \Gmagick::COLOR_BLUE,
            ColorInterface::COLOR_CYAN    => \Gmagick::COLOR_CYAN,
            ColorInterface::COLOR_MAGENTA => \Gmagick::COLOR_MAGENTA,
            ColorInterface::COLOR_YELLOW  => \Gmagick::COLOR_YELLOW,
            ColorInterface::COLOR_KEYLINE => \Gmagick::COLOR_BLACK,
            // There is no gray component in \Gmagick, let's use one of the RGB comp
            ColorInterface::COLOR_GRAY    => \Gmagick::COLOR_RED,
        );

        if ($this->palette->supportsAlpha()) {
            try {
                $alpha = (int) round($pixel->getcolorvalue(\Gmagick::COLOR_ALPHA) * 100);
            } catch (\GmagickPixelException $e) {
                $alpha = null;
            }
        } else {
            $alpha = null;
        }

        $palette = $this->palette();

        return $this->palette->color(array_map(function ($color) use ($palette, $pixel, $colorMapping) {
            if (!isset($colorMapping[$color])) {
                throw new InvalidArgumentException(sprintf('Color %s is not mapped in Gmagick', $color));
            }
            $multiplier = 255;
            if ($palette->name() === PaletteInterface::PALETTE_CMYK) {
                $multiplier = 100;
            }

            return $pixel->getcolorvalue($colorMapping[$color]) * $multiplier;
        }, $this->palette->pixelDefinition()), $alpha);
    }

    /**
     * {@inheritdoc}
     */
    public function layers()
    {
        return $this->layers;
    }

    /**
     * {@inheritdoc}
     */
    public function interlace($scheme)
    {
        static $supportedInterlaceSchemes = array(
            ImageInterface::INTERLACE_NONE      => \Gmagick::INTERLACE_NO,
            ImageInterface::INTERLACE_LINE      => \Gmagick::INTERLACE_LINE,
            ImageInterface::INTERLACE_PLANE     => \Gmagick::INTERLACE_PLANE,
            ImageInterface::INTERLACE_PARTITION => \Gmagick::INTERLACE_PARTITION,
        );

        if (!array_key_exists($scheme, $supportedInterlaceSchemes)) {
            throw new InvalidArgumentException('Unsupported interlace type');
        }

        $this->gmagick->setInterlaceScheme($supportedInterlaceSchemes[$scheme]);

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function usePalette(PaletteInterface $palette)
    {
        if (!isset(static::$colorspaceMapping[$palette->name()])) {
            throw new InvalidArgumentException(sprintf('The palette %s is not supported by Gmagick driver',$palette->name()));
        }

        if ($this->palette->name() === $palette->name()) {
            return $this;
        }

        try {
            try {
                $hasICCProfile = (Boolean) $this->gmagick->getimageprofile('ICM');
            } catch (\GmagickException $e) {
                $hasICCProfile = false;
            }

            if (!$hasICCProfile) {
                $this->profile($this->palette->profile());
            }

            $this->profile($palette->profile());

            $this->setColorspace($palette);
            $this->palette = $palette;
        } catch (\GmagickException $e) {
            throw new RuntimeException('Failed to set colorspace', $e->getCode(), $e);
        }

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
        try {
            $this->gmagick->profileimage('ICM', $profile->data());
        } catch (\GmagickException $e) {
            if (false !== strpos($e->getMessage(), 'LCMS encoding not enabled')) {
                throw new RuntimeException(sprintf('Unable to add profile %s to image, be sue to compile graphicsmagick with `--with-lcms2` option', $profile->name()), $e->getCode(), $e);
            }

            throw new RuntimeException(sprintf('Unable to add profile %s to image', $profile->name()), $e->getCode(), $e);
        }

        return $this;
    }

    /**
     * Internal
     *
     * Flatten the image.
     */
    private function flatten()
    {
        /**
         * @see http://pecl.php.net/bugs/bug.php?id=22435
         */
        if (method_exists($this->gmagick, 'flattenImages')) {
            try {
                $this->gmagick = $this->gmagick->flattenImages();
            } catch (\GmagickException $e) {
                throw new RuntimeException('Flatten operation failed', $e->getCode(), $e);
            }
        }
    }

    /**
     * Gets specifically formatted color string from Color instance
     *
     * @param ColorInterface $color
     *
     * @return \GmagickPixel
     *
     * @throws InvalidArgumentException
     */
    private function getColor(ColorInterface $color)
    {
        if (!$color->isOpaque()) {
            throw new InvalidArgumentException('Gmagick doesn\'t support transparency');
        }

        return new \GmagickPixel((string) $color);
    }

    /**
     * Internal
     *
     * Get the mime type based on format.
     *
     * @param string $format
     *
     * @return string mime-type
     *
     * @throws InvalidArgumentException
     */
    private function getMimeType($format)
    {
        static $mimeTypes = array(
            'jpeg' => 'image/jpeg',
            'jpg'  => 'image/jpeg',
            'gif'  => 'image/gif',
            'png'  => 'image/png',
            'wbmp' => 'image/vnd.wap.wbmp',
            'xbm'  => 'image/xbm',
        );

        if (!isset($mimeTypes[$format])) {
            throw new InvalidArgumentException(sprintf('Unsupported format given. Only %s are supported, %s given', implode(", ", array_keys($mimeTypes)), $format));
        }

        return $mimeTypes[$format];
    }

    /**
     * Sets colorspace and image type, assigns the palette.
     *
     * @param PaletteInterface $palette
     *
     * @throws InvalidArgumentException
     */
    private function setColorspace(PaletteInterface $palette)
    {
        if (!isset(static::$colorspaceMapping[$palette->name()])) {
            throw new InvalidArgumentException(sprintf('The palette %s is not supported by Gmagick driver', $palette->name()));
        }

        $this->gmagick->setimagecolorspace(static::$colorspaceMapping[$palette->name()]);
        $this->palette = $palette;
    }
}
