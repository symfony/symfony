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

        $canvas->save($this->getTempDir().'/smiley.png');

        $this->assertFileExists($this->getTempDir().'/smiley.png');
    }

    public function testDrawAnEllipse()
    {
        $loader = $this->getLoader();

        $canvas = $loader->create(new Box(400, 300), $this->getColor('000'));

        $canvas->draw()
            ->ellipse(new Center($canvas->getSize()), new Box(300, 200), $this->getColor('fff'), true);

        $canvas->save($this->getTempDir().'/ellipse.png');

        $this->assertFileExists($this->getTempDir().'/ellipse.png');
    }

    public function testDrawAPieSlice()
    {
        $loader = $this->getLoader();

        $canvas = $loader->create(new Box(400, 300), $this->getColor('000'));

        $canvas->draw()
            ->pieSlice(new Point(200, 150), new Box(100, 200), 45, 135, $this->getColor('fff'), true);

        $canvas->save($this->getTempDir().'/pie.png');

        $this->assertFileExists($this->getTempDir().'/pie.png');
    }

    public function testDrawAChord()
    {
        $loader = $this->getLoader();

        $canvas = $loader->create(new Box(400, 300), $this->getColor('000'));

        $canvas->draw()
            ->chord(new Point(200, 150), new Box(100, 200), 45, 135, $this->getColor('fff'), true);

        $canvas->save($this->getTempDir().'/chord.png');

        $this->assertFileExists($this->getTempDir().'/chord.png');
    }

    public function testDrawALine()
    {
        $loader = $this->getLoader();

        $canvas = $loader->create(new Box(400, 300), $this->getColor('000'));

        $canvas->draw()
            ->line(new Point(50, 50), new Point(350, 250), $this->getColor('fff'))
            ->line(new Point(50, 250), new Point(350, 50), $this->getColor('fff'));

        $canvas->save($this->getTempDir().'/lines.png');

        $this->assertFileExists($this->getTempDir().'/lines.png');
    }

    public function testDrawAPolygon()
    {
        $loader = $this->getLoader();

        $canvas = $loader->create(new Box(400, 300), $this->getColor('000'));

        $canvas->draw()
            ->polygon(array(
                new Point(50, 20),
                new Point(350, 20),
                new Point(350, 280),
                new Point(50, 280),
            ), $this->getColor('fff'), true);

        $canvas->save($this->getTempDir().'/polygon.png');

        $this->assertFileExists($this->getTempDir().'/polygon.png');
    }

    public function testDrawADot()
    {
        $loader = $this->getLoader();

        $canvas = $loader->create(new Box(400, 300), $this->getColor('000'));

        $canvas->draw()
            ->dot(new Point(200, 150), $this->getColor('fff'))
            ->dot(new Point(200, 151), $this->getColor('fff'))
            ->dot(new Point(200, 152), $this->getColor('fff'))
            ->dot(new Point(200, 153), $this->getColor('fff'));

        $canvas->save($this->getTempDir().'/dot.png');

        $this->assertFileExists($this->getTempDir().'/dot.png');
    }

    public function testDrawAnArc()
    {
        $loader = $this->getLoader();

        $canvas = $loader->create(new Box(400, 300), $this->getColor('000'));
        $size = $canvas->getSize();

        $canvas->draw()
            ->arc(new Center($size), $size->scale(0.5), 0, 180, $this->getColor('fff'));

        $canvas->save($this->getTempDir().'/arc.png');

        $this->assertFileExists($this->getTempDir().'/arc.png');
    }

    public function testDrawText()
    {
        if (!$this->isFontTestSupported()) {
            $this->markTestSkipped('This install does not support font tests');
        }

        $path = Loader::getFixture('font/Arial.ttf');
        $black = $this->getColor('000');
        $file36 = $this->getTempDir().'/bulat36.png';
        $file24 = $this->getTempDir().'/bulat24.png';
        $file18 = $this->getTempDir().'/bulat18.png';
        $file12 = $this->getTempDir().'/bulat12.png';

        $loader = $this->getLoader();
        $canvas = $loader->create(new Box(400, 300), $this->getColor('fff'));
        $font = $loader->font($path, 36, $black);

        $canvas->draw()
            ->text('Bulat', $font, new Point(0, 0), 135);

        $canvas->save($file36);

        unset($canvas);

        $this->assertFileExists($file36);

        $canvas = $loader->create(new Box(400, 300), $this->getColor('fff'));
        $font = $loader->font($path, 24, $black);

        $canvas->draw()
            ->text('Bulat', $font, new Point(24, 24));

        $canvas->save($file24);

        unset($canvas);

        $this->assertFileExists($file24);

        $canvas = $loader->create(new Box(400, 300), $this->getColor('fff'));
        $font = $loader->font($path, 18, $black);

        $canvas->draw()
            ->text('Bulat', $font, new Point(18, 18));

        $canvas->save($file18);

        unset($canvas);

        $this->assertFileExists($file18);

        $canvas = $loader->create(new Box(400, 300), $this->getColor('fff'));
        $font = $loader->font($path, 12, $black);

        $canvas->draw()
            ->text('Bulat', $font, new Point(12, 12));

        $canvas->save($file12);

        unset($canvas);

        $this->assertFileExists($file12);
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
