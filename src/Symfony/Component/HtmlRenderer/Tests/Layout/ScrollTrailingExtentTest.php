<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HtmlRenderer\Tests\Layout;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HtmlRenderer\HtmlRenderer;
use Symfony\Component\HtmlRenderer\RenderingContext;

class ScrollTrailingExtentTest extends TestCase
{
    public function testNaturalFlowExtentKeepsTheFinalMarginAndPaddingWithoutDoubleCounting()
    {
        foreach (['height:40px' => [40.0, 90.0], 'height:40px;padding:5px 10px 15px;border:2px solid' => [60.0, 110.0], 'height:64px;box-sizing:border-box;padding:5px 10px 15px;border:2px solid' => [60.0, 110.0], 'padding:5px 10px 15px;border:2px solid' => [110.0, 110.0], 'height:200px;padding:5px 10px 15px' => [220.0, 220.0]] as $style => [$port, $content]) {
            $session = new HtmlRenderer()->createSession('<style>#s{width:160px;overflow:auto;'.$style.'}p{height:20px;margin:0 0 10px}</style><div id="s"><p>A</p><p>B</p><p>C</p></div>', new RenderingContext(320, 240));
            $geometry = $session->render()->getScrollGeometry($session->getDocument()->getElementById('s'));
            self::assertSame($port, $geometry->port->height, $style);
            self::assertSame($content, $geometry->content->height, $style);
            self::assertSame($content - $port, $geometry->maxY, $style);
        }
    }
}
