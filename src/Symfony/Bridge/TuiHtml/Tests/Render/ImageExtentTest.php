<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\TuiHtml\Tests\Render;

use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;
use Symfony\Bridge\TuiHtml\Render\DisplayListRenderer;
use Symfony\Component\HtmlRenderer\Paint\DisplayList;
use Symfony\Component\Tui\Terminal\ScreenBuffer;

#[RequiresPhpExtension('gd')]
final class ImageExtentTest extends TestCase
{
    public function testFractionalOriginsDoNotAddAnExtraRowOrColumnToAnImage()
    {
        $image = ['type' => 'image', 'x' => 4.0, 'y' => 8.0, 'width' => 8.0, 'height' => 16.0, 'data' => file_get_contents(__DIR__.'/../Fixtures/Compat/quadrants.png')];
        $screen = new ScreenBuffer(3, 3);
        $screen->write(implode("\r\n", new DisplayListRenderer()->render(new DisplayList([$image]), 3, 3)));
        self::assertNotSame(' ', $screen->getCells()[0][0]['char']);
        self::assertSame(' ', $screen->getCells()[0][1]['char']);
        self::assertSame(' ', $screen->getCells()[1][0]['char']);
        $image['width'] = 0.0;
        self::assertSame('', trim(implode('', new DisplayListRenderer()->render(new DisplayList([$image]), 3, 3))));
    }
}
