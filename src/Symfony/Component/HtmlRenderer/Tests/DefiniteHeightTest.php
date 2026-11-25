<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HtmlRenderer\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HtmlRenderer\HtmlRenderer;
use Symfony\Component\HtmlRenderer\RenderingContext;

class DefiniteHeightTest extends TestCase
{
    public function testPercentageChildrenUseTheUsedParentContentHeight(): void
    {
        foreach (['block', 'flex', 'grid'] as $display) {
            foreach (['height:64px' => 64.0, 'height:64px;padding:4px;border:2px solid;box-sizing:border-box' => 52.0, 'height:64px;padding:4px;border:2px solid;box-sizing:content-box' => 64.0, 'height:64.5px' => 64.5] as $style => $expected) {
                $session = new HtmlRenderer()->createSession('<div style="display:'.$display.';'.$style.'"><div id="child" style="height:100%">A</div></div>', new RenderingContext(320, 240));
                self::assertSame($expected, $session->render()->getBounds($session->getDocument()->getElementById('child'))[0]->height, $display.' '.$style);
            }
        }
    }

    public function testAForcedColumnFlexItemSuppliesThePercentageBasisOfItsDescendants(): void
    {
        $session = new HtmlRenderer()->createSession('<div style="display:flex;flex-direction:column;height:64px"><div id="item" style="display:flex;flex:1;min-height:0"><span id="child" style="height:100%">A</span></div></div>', new RenderingContext(320, 240));
        $result = $session->render();
        self::assertSame(64.0, $result->getBounds($session->getDocument()->getElementById('item'))[0]->height);
        self::assertSame(64.0, $result->getBounds($session->getDocument()->getElementById('child'))[0]->height);
    }
}
