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

use Symfony\Component\Image\Image\Palette\Color\Gray as GrayColor;
use Symfony\Component\Image\Image\Palette\Color\ColorInterface;
use Symfony\Component\Image\Image\ProfileInterface;
use Symfony\Component\Image\Image\Profile;
use Symfony\Component\Image\Exception\RuntimeException;

class Grayscale implements PaletteInterface
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
        return PaletteInterface::PALETTE_GRAYSCALE;
    }

    /**
     * {@inheritdoc}
     */
    public function pixelDefinition()
    {
        return array(ColorInterface::COLOR_GRAY);
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
            $this->profile = Profile::fromPath(__DIR__ . '/../../Resources/colormanagement.org/ISOcoated_v2_grey1c_bas.ICC');
        }

        return $this->profile;
    }

    /**
     * {@inheritdoc}
     */
    public function color($color, $alpha = null)
    {
        if (null === $alpha) {
            $alpha = 0;
        }

        $color = $this->parser->parseToGrayscale($color);
        $index = sprintf('#%02x%02x%02x-%d', $color[0], $color[0], $color[0], $alpha);

        if (false === array_key_exists($index, static::$colors)) {
            static::$colors[$index] = new GrayColor($this, $color, $alpha);
        }

        return static::$colors[$index];
    }

    /**
     * {@inheritdoc}
     */
    public function blend(ColorInterface $color1, ColorInterface $color2, $amount)
    {
        if (!$color1 instanceof GrayColor || ! $color2 instanceof GrayColor) {
            throw new RuntimeException('Grayscale palette can only blend Grayscale colors');
        }

        return $this->color(
            array(
                (int) min(255, min($color1->getGray(), $color2->getGray()) + round(abs($color2->getGray() - $color1->getGray()) * $amount)),
            ),
            (int) min(100, min($color1->getAlpha(), $color2->getAlpha()) + round(abs($color2->getAlpha() - $color1->getAlpha()) * $amount))
        );
    }
}
