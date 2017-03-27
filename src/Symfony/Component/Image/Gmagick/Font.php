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

use Symfony\Component\Image\Image\AbstractFont;
use Symfony\Component\Image\Image\Box;
use Symfony\Component\Image\Image\Palette\Color\ColorInterface;

/**
 * Font implementation using the Gmagick PHP extension
 */
final class Font extends AbstractFont
{
    /**
     * @var \Gmagick
     */
    private $gmagick;

    /**
     * @param \Gmagick       $gmagick
     * @param string         $file
     * @param integer        $size
     * @param ColorInterface $color
     */
    public function __construct(\Gmagick $gmagick, $file, $size, ColorInterface $color)
    {
        $this->gmagick = $gmagick;

        parent::__construct($file, $size, $color);
    }

    /**
     * {@inheritdoc}
     */
    public function box($string, $angle = 0)
    {
        $text  = new \GmagickDraw();

        $text->setfont($this->file);
        /**
         * @see http://www.php.net/manual/en/imagick.queryfontmetrics.php#101027
         *
         * ensure font resolution is the same as GD's hard-coded 96
         */
        $text->setfontsize((int) ($this->size * (96 / 72)));
        $text->setfontstyle(\Gmagick::STYLE_OBLIQUE);

        $info = $this->gmagick->queryfontmetrics($text, $string);

        $box = new Box($info['textWidth'], $info['textHeight']);

        return $box;
    }
}
