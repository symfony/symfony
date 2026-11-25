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

final class AnonymousTextItemTest extends TestCase
{
    public function testFlexAnonymousTextCollapsesBoundaryWhitespaceBeforeGap()
    {
        $result = (new HtmlRenderer())->render('<main style="display:flex;gap:8px;font-size:16px;line-height:16px"> Fruit <span style="width:20px">X</span> </main>', new RenderingContext(200, 100));
        $span = $result->getDocument()->querySelector('span');
        self::assertSame(48.0, $result->getBounds($span)[0]->x);
        $text = array_column(array_filter($result->getDisplayList()->getOperations(), static fn ($op) => 'text' === $op['type']), 'text');
        self::assertSame(['Fruit', 'X'], $text);
    }

    public function testGridAnonymousTextTrimsNormalWhitespaceButPreservesPre()
    {
        foreach (['normal' => 'Fruit', 'pre' => ' Fruit '] as $whiteSpace => $expected) {
            $result = (new HtmlRenderer())->render('<main style="display:grid;white-space:'.$whiteSpace.'"> Fruit </main>', new RenderingContext(200, 100));
            $text = array_column(array_filter($result->getDisplayList()->getOperations(), static fn ($op) => 'text' === $op['type']), 'text');
            self::assertSame([$expected], $text);
        }
    }
}
