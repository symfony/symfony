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

class IntrinsicChildConstraintTest extends TestCase
{
    public function testChildContributionsRespectMaximumMinimumAndBoxSizing()
    {
        foreach (['width:100px;max-width:40px' => 40.0, 'width:100px;max-width:40px;min-width:60px' => 60.0, 'width:100px;max-width:40px;padding:5px;border:2px solid' => 54.0, 'width:100px;max-width:40px;padding:5px;border:2px solid;box-sizing:border-box' => 40.0] as $style => $expected) {
            foreach (['', 'width:min-content'] as $width) {
                $session = new HtmlRenderer()->createSession('<span id="outer" style="display:inline-block;'.$width.'"><div style="height:10px;'.$style.'">X</div></span>', new RenderingContext(400, 200));
                self::assertSame($expected, $session->render()->getBounds($session->getDocument()->getElementById('outer'))[0]->width, $style.' '.$width);
            }
        }
    }

    public function testPercentageConstraintsDoNotAcquireAnInventedIntrinsicContainingWidth()
    {
        foreach (['' => [300.0, 150.0], 'width:200px' => [200.0, 100.0]] as $width => [$outer, $child]) {
            $session = new HtmlRenderer()->createSession('<span id="outer" style="display:inline-block;'.$width.'"><div id="child" style="width:300px;max-width:50%;height:10px">X</div></span>', new RenderingContext(400, 200));
            $result = $session->render();
            self::assertSame($outer, $result->getBounds($session->getDocument()->getElementById('outer'))[0]->width);
            self::assertSame($child, $result->getBounds($session->getDocument()->getElementById('child'))[0]->width);
        }
    }
}
