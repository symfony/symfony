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

use Symfony\Component\Image\Effects\EffectsInterface;
use Symfony\Component\Image\Exception\RuntimeException;
use Symfony\Component\Image\Image\Palette\Color\ColorInterface;
use Symfony\Component\Image\Image\Palette\Color\RGB as RGBColor;

/**
 * Effects implementation using the GD library.
 */
class Effects implements EffectsInterface
{
    private $resource;

    public function __construct($resource)
    {
        $this->resource = $resource;
    }

    /**
     * {@inheritdoc}
     */
    public function gamma($correction)
    {
        if (false === imagegammacorrect($this->resource, 1.0, $correction)) {
            throw new RuntimeException('Failed to apply gamma correction to the image');
        }

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function negative()
    {
        if (false === imagefilter($this->resource, IMG_FILTER_NEGATE)) {
            throw new RuntimeException('Failed to negate the image');
        }

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function grayscale()
    {
        if (false === imagefilter($this->resource, IMG_FILTER_GRAYSCALE)) {
            throw new RuntimeException('Failed to grayscale the image');
        }

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function colorize(ColorInterface $color)
    {
        if (!$color instanceof RGBColor) {
            throw new RuntimeException('Colorize effects only accepts RGB color in GD context');
        }

        if (false === imagefilter($this->resource, IMG_FILTER_COLORIZE, $color->getRed(), $color->getGreen(), $color->getBlue())) {
            throw new RuntimeException('Failed to colorize the image');
        }

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function sharpen()
    {
        $sharpenMatrix = array(array(-1, -1, -1), array(-1, 16, -1), array(-1, -1, -1));
        $divisor = array_sum(array_map('array_sum', $sharpenMatrix));

        if (false === imageconvolution($this->resource, $sharpenMatrix, $divisor, 0)) {
            throw new RuntimeException('Failed to sharpen the image');
        }

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function blur($sigma = 1)
    {
        if (false === imagefilter($this->resource, IMG_FILTER_GAUSSIAN_BLUR)) {
            throw new RuntimeException('Failed to blur the image');
        }

        return $this;
    }
}
