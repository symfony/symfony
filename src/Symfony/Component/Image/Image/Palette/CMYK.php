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

use Symfony\Component\Image\Image\Palette\Color\CMYK as CMYKColor;
use Symfony\Component\Image\Image\Palette\Color\ColorInterface;
use Symfony\Component\Image\Exception\RuntimeException;
use Symfony\Component\Image\Exception\InvalidArgumentException;
use Symfony\Component\Image\Image\Profile;
use Symfony\Component\Image\Image\ProfileInterface;

class CMYK implements PaletteInterface
{
    private $parser;
    private $profile;
    private static $colors = array();

    public function __construct()
    {
        $this->parser = new ColorParser();
    }

    /**
     * {@inheritdoc}
     */
    public function name()
    {
        return PaletteInterface::PALETTE_CMYK;
    }

    /**
     * {@inheritdoc}
     */
    public function pixelDefinition()
    {
        return array(
            ColorInterface::COLOR_CYAN,
            ColorInterface::COLOR_MAGENTA,
            ColorInterface::COLOR_YELLOW,
            ColorInterface::COLOR_KEYLINE,
        );
    }

    /**
     * {@inheritdoc}
     */
    public function supportsAlpha()
    {
        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function color($color, $alpha = null)
    {
        if (null !== $alpha) {
            throw new InvalidArgumentException('CMYK palette does not support alpha');
        }

        $color = $this->parser->parseToCMYK($color);
        $index = sprintf('cmyk(%d, %d, %d, %d)', $color[0], $color[1], $color[2], $color[3]);

        if (false === array_key_exists($index, self::$colors)) {
            self::$colors[$index] = new CMYKColor($this, $color);
        }

        return self::$colors[$index];
    }

    /**
     * {@inheritdoc}
     */
    public function blend(ColorInterface $color1, ColorInterface $color2, $amount)
    {
        if (!$color1 instanceof CMYKColor || !$color2 instanceof CMYKColor) {
            throw new RuntimeException('CMYK palette can only blend CMYK colors');
        }

        return $this->color(array(
            min(100, $color1->getCyan() + $color2->getCyan() * $amount),
            min(100, $color1->getMagenta() + $color2->getMagenta() * $amount),
            min(100, $color1->getYellow() + $color2->getYellow() * $amount),
            min(100, $color1->getKeyline() + $color2->getKeyline() * $amount),
        ));
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
            $this->profile = Profile::fromPath(__DIR__.'/../../Resources/Adobe/CMYK/USWebUncoated.icc');
        }

        return $this->profile;
    }
}
