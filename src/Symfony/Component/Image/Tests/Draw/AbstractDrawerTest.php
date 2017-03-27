<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Image\Tests\Draw;

use Symfony\Component\Image\Fixtures\Loader;
use Symfony\Component\Image\Image\Box;
use Symfony\Component\Image\Image\Font;
use Symfony\Component\Image\Image\Palette\RGB;
use Symfony\Component\Image\Image\Point;
use Symfony\Component\Image\Image\Point\Center;
use Symfony\Component\Image\Image\LoaderInterface;
use Symfony\Component\Image\Tests\TestCase;

abstract class AbstractDrawerTest extends TestCase
{
    public function testDrawASmileyFace()
    {
        $loader = $this->getLoader();

        $canvas = $loader->create(new Box(400, 300), $this->getColor('000'));

        $canvas->draw()
            ->chord(new Point(200, 200), new Box(200, 150), 0, 180, $this->getColor('fff'), false)
            ->ellipse(new Point(125, 100), new Box(50, 50), $this->getColor('fff'))
            ->ellipse(new Point(275, 100), new Box(50, 50), $this->getColor('fff'), true);

        $canvas->save(__DIR__.'/../results/smiley.png');

        $this->assertTrue(file_exists(__DIR__.'/../results/smiley.png'));

        unlink(__DIR__.'/../results/smiley.png');
    }

    public function testDrawAnEllipse()
    {
        $loader = $this->getLoader();

        $canvas = $loader->create(new Box(400, 300),  $this->getColor('000'));

        $canvas->draw()
            ->ellipse(new Center($canvas->getSize()), new Box(300, 200), $this->getColor('fff'), true);

        $canvas->save(__DIR__.'/../results/ellipse.png');

        $this->assertTrue(file_exists(__DIR__.'/../results/ellipse.png'));

        unlink(__DIR__.'/../results/ellipse.png');
    }

    public function testDrawAPieSlice()
    {
        $loader = $this->getLoader();

        $canvas = $loader->create(new Box(400, 300),  $this->getColor('000'));

        $canvas->draw()
            ->pieSlice(new Point(200, 150), new Box(100, 200), 45, 135, $this->getColor('fff'), true);

        $canvas->save(__DIR__.'/../results/pie.png');

        $this->assertTrue(file_exists(__DIR__.'/../results/pie.png'));

        unlink(__DIR__.'/../results/pie.png');
    }

    public function testDrawAChord()
    {
        $loader = $this->getLoader();

        $canvas = $loader->create(new Box(400, 300),  $this->getColor('000'));

        $canvas->draw()
            ->chord(new Point(200, 150), new Box(100, 200), 45, 135, $this->getColor('fff'), true);

        $canvas->save(__DIR__.'/../results/chord.png');

        $this->assertTrue(file_exists(__DIR__.'/../results/chord.png'));

        unlink(__DIR__.'/../results/chord.png');
    }

    public function testDrawALine()
    {
        $loader = $this->getLoader();

        $canvas = $loader->create(new Box(400, 300),  $this->getColor('000'));

        $canvas->draw()
            ->line(new Point(50, 50), new Point(350, 250), $this->getColor('fff'))
            ->line(new Point(50, 250), new Point(350, 50), $this->getColor('fff'));

        $canvas->save(__DIR__.'/../results/lines.png');

        $this->assertTrue(file_exists(__DIR__.'/../results/lines.png'));

        unlink(__DIR__.'/../results/lines.png');
    }

    public function testDrawAPolygon()
    {
        $loader = $this->getLoader();

        $canvas = $loader->create(new Box(400, 300),  $this->getColor('000'));

        $canvas->draw()
            ->polygon(array(
                new Point(50, 20),
                new Point(350, 20),
                new Point(350, 280),
                new Point(50, 280),
            ), $this->getColor('fff'), true);

        $canvas->save(__DIR__.'/../results/polygon.png');

        $this->assertTrue(file_exists(__DIR__.'/../results/polygon.png'));

        unlink(__DIR__.'/../results/polygon.png');
    }

    public function testDrawADot()
    {
        $loader = $this->getLoader();

        $canvas = $loader->create(new Box(400, 300),  $this->getColor('000'));

        $canvas->draw()
            ->dot(new Point(200, 150), $this->getColor('fff'))
            ->dot(new Point(200, 151), $this->getColor('fff'))
            ->dot(new Point(200, 152), $this->getColor('fff'))
            ->dot(new Point(200, 153), $this->getColor('fff'));

        $canvas->save(__DIR__.'/../results/dot.png');

        $this->assertTrue(file_exists(__DIR__.'/../results/dot.png'));

        unlink(__DIR__.'/../results/dot.png');
    }

    public function testDrawAnArc()
    {
        $loader = $this->getLoader();

        $canvas = $loader->create(new Box(400, 300),  $this->getColor('000'));
        $size   = $canvas->getSize();

        $canvas->draw()
            ->arc(new Center($size), $size->scale(0.5), 0, 180, $this->getColor('fff'));

        $canvas->save(__DIR__.'/../results/arc.png');

        $this->assertTrue(file_exists(__DIR__.'/../results/arc.png'));

        unlink(__DIR__.'/../results/arc.png');
    }

    public function testDrawText()
    {
        if (!$this->isFontTestSupported()) {
            $this->markTestSkipped('This install does not support font tests');
        }

        $path   = Loader::getFixture('font/Arial.ttf');
        $black  =  $this->getColor('000');
        $file36 = __DIR__.'/../results/bulat36.png';
        $file24 = __DIR__.'/../results/bulat24.png';
        $file18 = __DIR__.'/../results/bulat18.png';
        $file12 = __DIR__.'/../results/bulat12.png';

        $loader = $this->getLoader();
        $canvas  = $loader->create(new Box(400, 300), $this->getColor('fff'));
        $font    = $loader->font($path, 36, $black);

        $canvas->draw()
            ->text('Bulat', $font, new Point(0, 0), 135);

        $canvas->save($file36);

        unset($canvas);

        $this->assertTrue(file_exists($file36));

        unlink($file36);

        $canvas = $loader->create(new Box(400, 300), $this->getColor('fff'));
        $font   = $loader->font($path, 24, $black);

        $canvas->draw()
            ->text('Bulat', $font, new Point(24, 24));

        $canvas->save($file24);

        unset($canvas);

        $this->assertTrue(file_exists($file24));

        unlink($file24);

        $canvas = $loader->create(new Box(400, 300), $this->getColor('fff'));
        $font   = $loader->font($path, 18, $black);

        $canvas->draw()
            ->text('Bulat', $font, new Point(18, 18));

        $canvas->save($file18);

        unset($canvas);

        $this->assertTrue(file_exists($file18));

        unlink($file18);

        $canvas = $loader->create(new Box(400, 300), $this->getColor('fff'));
        $font   = $loader->font($path, 12, $black);

        $canvas->draw()
            ->text('Bulat', $font, new Point(12, 12));

        $canvas->save($file12);

        unset($canvas);

        $this->assertTrue(file_exists($file12));

        unlink($file12);
    }

    private function getColor($color)
    {
        static $palette;

        if (!$palette) {
            $palette = new RGB();
        }

        return $palette->color($color);
    }

    /**
     * @return LoaderInterface
     */
    abstract protected function getLoader();

    abstract protected function isFontTestSupported();
}
