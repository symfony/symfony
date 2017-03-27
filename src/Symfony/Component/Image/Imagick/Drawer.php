<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Image\Imagick;

use Symfony\Component\Image\Draw\DrawerInterface;
use Symfony\Component\Image\Exception\InvalidArgumentException;
use Symfony\Component\Image\Exception\RuntimeException;
use Symfony\Component\Image\Image\AbstractFont;
use Symfony\Component\Image\Image\BoxInterface;
use Symfony\Component\Image\Image\Palette\Color\ColorInterface;
use Symfony\Component\Image\Image\Point;
use Symfony\Component\Image\Image\PointInterface;

/**
 * Drawer implementation using the Imagick PHP extension
 */
final class Drawer implements DrawerInterface
{
    /**
     * @var \Imagick
     */
    private $imagick;

    /**
     * @param \Imagick $imagick
     */
    public function __construct(\Imagick $imagick)
    {
        $this->imagick = $imagick;
    }

    /**
     * {@inheritdoc}
     */
    public function arc(PointInterface $center, BoxInterface $size, $start, $end, ColorInterface $color, $thickness = 1)
    {
        $x      = $center->getX();
        $y      = $center->getY();
        $width  = $size->getWidth();
        $height = $size->getHeight();

        try {
            $pixel = $this->getColor($color);
            $arc   = new \ImagickDraw();

            $arc->setStrokeColor($pixel);
            $arc->setStrokeWidth(max(1, (int) $thickness));
            $arc->setFillColor('transparent');
            $arc->arc($x - $width / 2, $y - $height / 2, $x + $width / 2, $y + $height / 2, $start, $end);

            $this->imagick->drawImage($arc);

            $pixel->clear();
            $pixel->destroy();

            $arc->clear();
            $arc->destroy();
        } catch (\ImagickException $e) {
            throw new RuntimeException('Draw arc operation failed', $e->getCode(), $e);
        }

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function chord(PointInterface $center, BoxInterface $size, $start, $end, ColorInterface $color, $fill = false, $thickness = 1)
    {
        $x      = $center->getX();
        $y      = $center->getY();
        $width  = $size->getWidth();
        $height = $size->getHeight();

        try {
            $pixel = $this->getColor($color);
            $chord = new \ImagickDraw();

            $chord->setStrokeColor($pixel);
            $chord->setStrokeWidth(max(1, (int) $thickness));

            if ($fill) {
                $chord->setFillColor($pixel);
            } else {
                $this->line(
                    new Point(round($x + $width / 2 * cos(deg2rad($start))), round($y + $height / 2 * sin(deg2rad($start)))),
                    new Point(round($x + $width / 2 * cos(deg2rad($end))), round($y + $height / 2 * sin(deg2rad($end)))),
                    $color
                );

                $chord->setFillColor('transparent');
            }

            $chord->arc(
                $x - $width / 2,
                $y - $height / 2,
                $x + $width / 2,
                $y + $height / 2,
                $start,
                $end
            );

            $this->imagick->drawImage($chord);

            $pixel->clear();
            $pixel->destroy();

            $chord->clear();
            $chord->destroy();
        } catch (\ImagickException $e) {
            throw new RuntimeException('Draw chord operation failed', $e->getCode(), $e);
        }

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function ellipse(PointInterface $center, BoxInterface $size, ColorInterface $color, $fill = false, $thickness = 1)
    {
        $width  = $size->getWidth();
        $height = $size->getHeight();

        try {
            $pixel   = $this->getColor($color);
            $ellipse = new \ImagickDraw();

            $ellipse->setStrokeColor($pixel);
            $ellipse->setStrokeWidth(max(1, (int) $thickness));

            if ($fill) {
                $ellipse->setFillColor($pixel);
            } else {
                $ellipse->setFillColor('transparent');
            }

            $ellipse->ellipse(
                $center->getX(),
                $center->getY(),
                $width / 2,
                $height / 2,
                0, 360
            );

            if (false === $this->imagick->drawImage($ellipse)) {
                throw new RuntimeException('Ellipse operation failed');
            }

            $pixel->clear();
            $pixel->destroy();

            $ellipse->clear();
            $ellipse->destroy();
        } catch (\ImagickException $e) {
            throw new RuntimeException('Draw ellipse operation failed', $e->getCode(), $e);
        }

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function line(PointInterface $start, PointInterface $end, ColorInterface $color, $thickness = 1)
    {
        try {
            $pixel = $this->getColor($color);
            $line  = new \ImagickDraw();

            $line->setStrokeColor($pixel);
            $line->setStrokeWidth(max(1, (int) $thickness));
            $line->setFillColor($pixel);
            $line->line(
                $start->getX(),
                $start->getY(),
                $end->getX(),
                $end->getY()
            );

            $this->imagick->drawImage($line);

            $pixel->clear();
            $pixel->destroy();

            $line->clear();
            $line->destroy();
        } catch (\ImagickException $e) {
            throw new RuntimeException('Draw line operation failed', $e->getCode(), $e);
        }

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function pieSlice(PointInterface $center, BoxInterface $size, $start, $end, ColorInterface $color, $fill = false, $thickness = 1)
    {
        $width  = $size->getWidth();
        $height = $size->getHeight();

        $x1 = round($center->getX() + $width / 2 * cos(deg2rad($start)));
        $y1 = round($center->getY() + $height / 2 * sin(deg2rad($start)));
        $x2 = round($center->getX() + $width / 2 * cos(deg2rad($end)));
        $y2 = round($center->getY() + $height / 2 * sin(deg2rad($end)));

        if ($fill) {
            $this->chord($center, $size, $start, $end, $color, true, $thickness);
            $this->polygon(
                array(
                    $center,
                    new Point($x1, $y1),
                    new Point($x2, $y2),
                ),
                $color,
                true,
                $thickness
            );
        } else {
            $this->arc($center, $size, $start, $end, $color, $thickness);
            $this->line($center, new Point($x1, $y1), $color, $thickness);
            $this->line($center, new Point($x2, $y2), $color, $thickness);
        }

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function dot(PointInterface $position, ColorInterface $color)
    {
        $x = $position->getX();
        $y = $position->getY();

        try {
            $pixel = $this->getColor($color);
            $point = new \ImagickDraw();

            $point->setFillColor($pixel);
            $point->point($x, $y);

            $this->imagick->drawimage($point);

            $pixel->clear();
            $pixel->destroy();

            $point->clear();
            $point->destroy();
        } catch (\ImagickException $e) {
            throw new RuntimeException('Draw point operation failed', $e->getCode(), $e);
        }

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function polygon(array $coordinates, ColorInterface $color, $fill = false, $thickness = 1)
    {
        if (count($coordinates) < 3) {
            throw new InvalidArgumentException(sprintf('Polygon must consist of at least 3 coordinates, %d given', count($coordinates)));
        }

        $points = array_map(function (PointInterface $p) {
            return array('x' => $p->getX(), 'y' => $p->getY());
        }, $coordinates);

        try {
            $pixel   = $this->getColor($color);
            $polygon = new \ImagickDraw();

            $polygon->setStrokeColor($pixel);
            $polygon->setStrokeWidth(max(1, (int) $thickness));

            if ($fill) {
                $polygon->setFillColor($pixel);
            } else {
                $polygon->setFillColor('transparent');
            }

            $polygon->polygon($points);
            $this->imagick->drawImage($polygon);

            $pixel->clear();
            $pixel->destroy();

            $polygon->clear();
            $polygon->destroy();
        } catch (\ImagickException $e) {
            throw new RuntimeException('Draw polygon operation failed', $e->getCode(), $e);
        }

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function text($string, AbstractFont $font, PointInterface $position, $angle = 0, $width = null)
    {
        try {
            $pixel = $this->getColor($font->getColor());
            $text  = new \ImagickDraw();

            $text->setFont($font->getFile());
            /**
             * @see http://www.php.net/manual/en/imagick.queryfontmetrics.php#101027
             *
             * ensure font resolution is the same as GD's hard-coded 96
             */
            if (version_compare(phpversion("imagick"), "3.0.2", ">=")) {
                $text->setResolution(96, 96);
                $text->setFontSize($font->getSize());
            } else {
                $text->setFontSize((int) ($font->getSize() * (96 / 72)));
            }
            $text->setFillColor($pixel);
            $text->setTextAntialias(true);

            $info = $this->imagick->queryFontMetrics($text, $string);
            $rad  = deg2rad($angle);
            $cos  = cos($rad);
            $sin  = sin($rad);

            // round(0 * $cos - 0 * $sin)
            $x1 = 0;
            $x2 = round($info['characterWidth'] * $cos - $info['characterHeight'] * $sin);
            // round(0 * $sin + 0 * $cos)
            $y1 = 0;
            $y2 = round($info['characterWidth'] * $sin + $info['characterHeight'] * $cos);

            $xdiff = 0 - min($x1, $x2);
            $ydiff = 0 - min($y1, $y2);

            if ($width !== null) {
                $string = $this->wrapText($string, $text, $angle, $width);
            }

            $this->imagick->annotateImage(
                $text, $position->getX() + $x1 + $xdiff,
                $position->getY() + $y2 + $ydiff, $angle, $string
            );

            $pixel->clear();
            $pixel->destroy();

            $text->clear();
            $text->destroy();
        } catch (\ImagickException $e) {
            throw new RuntimeException('Draw text operation failed', $e->getCode(), $e);
        }

        return $this;
    }

    /**
     * Gets specifically formatted color string from ColorInterface instance
     *
     * @param ColorInterface $color
     *
     * @return string
     */
    private function getColor(ColorInterface $color)
    {
        $pixel = new \ImagickPixel((string) $color);
        $pixel->setColorValue(\Imagick::COLOR_ALPHA, $color->getAlpha() / 100);

        return $pixel;
    }

    /**
     * Internal
     *
     * Fits a string into box with given width
     */
    private function wrapText($string, $text, $angle, $width)
    {
        $result = '';
        $words = explode(' ', $string);
        foreach ($words as $word) {
            $teststring = $result . ' ' . $word;
            $testbox = $this->imagick->queryFontMetrics($text, $teststring, true);
            if ($testbox['textWidth'] > $width) {
                $result .= ($result == '' ? '' : "\n") . $word;
            } else {
                $result .= ($result == '' ? '' : ' ') . $word;
            }
        }

        return $result;
    }
}
