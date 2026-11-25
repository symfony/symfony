<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HtmlRenderer\Tests\Css;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HtmlRenderer\Css\PositionArea;

class PositionAreaTest extends TestCase
{
    public function testSharedPhysicalGrammarAndLogicalAxes()
    {
        self::assertSame(['bottom', 'span-right'], PositionArea::parse('bottom span-right'));
        self::assertSame(['top', 'left'], PositionArea::parse('start end', 'rtl'));
        self::assertSame(['bottom', 'right'], PositionArea::parse('start end', 'ltr', 'vertical-rl'));
        self::assertNull(PositionArea::parse('top bottom'));
    }
}
