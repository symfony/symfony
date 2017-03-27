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

use Symfony\Component\Image\Image\Palette\Color\RGB as RGBColor;
use Symfony\Component\Image\Image\Palette\Color\ColorInterface;
use Symfony\Component\Image\Image\ProfileInterface;
use Symfony\Component\Image\Image\Profile;
use Symfony\Component\Image\Exception\RuntimeException;

class RGB implements PaletteInterface
{
    /**
     * @var ColorParser
     */
    private $parser;

    /**
     * @var ProfileInterface
     */
    private $profile;

    /**
     * @var array
     */
    protected static $colors = array();

    public function __construct()
    {
        $this->parser = new ColorParser();
    }

    /**
     * {@inheritdoc}
     */
    public function name()
    {
        return PaletteInterface::PALETTE_RGB;
    }

    /**
     * {@inheritdoc}
     */
    public function pixelDefinition()
    {
        return array(
            ColorInterface::COLOR_RED,
            ColorInterface::COLOR_GREEN,
            ColorInterface::COLOR_BLUE,
        );
    }

    /**
     * {@inheritdoc}
     */
    public function supportsAlpha()
    {
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function useProfile(ProfileInterface $profile)
    {
        $this->profile = $profile;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function profile()
    {
        if (!$this->profile) {
            $this->profile = Profile::fromPath(__DIR__.'/../../Resources/color.org/sRGB_IEC61966-2-1_black_scaled.icc');
        }

        return $this->profile;
    }

    /**
     * {@inheritdoc}
     */
    public function color($color, $alpha = null)
    {
        if (null === $alpha) {
            $alpha = 100;
        }

        $color = $this->parser->parseToRGB($color);
        $index = sprintf('#%02x%02x%02x-%d', $color[0], $color[1], $color[2], $alpha);

        if (false === array_key_exists($index, static::$colors)) {
            static::$colors[$index] = new RGBColor($this, $color, $alpha);
        }

        return static::$colors[$index];
    }

    /**
     * {@inheritdoc}
     */
    public function blend(ColorInterface $color1, ColorInterface $color2, $amount)
    {
        if (!$color1 instanceof RGBColor || !$color2 instanceof RGBColor) {
            throw new RuntimeException('RGB palette can only blend RGB colors');
        }

        return $this->color(
            array(
                (int) min(255, min($color1->getRed(), $color2->getRed()) + round(abs($color2->getRed() - $color1->getRed()) * $amount)),
                (int) min(255, min($color1->getGreen(), $color2->getGreen()) + round(abs($color2->getGreen() - $color1->getGreen()) * $amount)),
                (int) min(255, min($color1->getBlue(), $color2->getBlue()) + round(abs($color2->getBlue() - $color1->getBlue()) * $amount)),
            ),
            (int) min(100, min($color1->getAlpha(), $color2->getAlpha()) + round(abs($color2->getAlpha() - $color1->getAlpha()) * $amount))
        );
    }
}
