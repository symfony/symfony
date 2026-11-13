<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Tui\Tests\Widget;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Tui\Render\RenderContext;
use Symfony\Component\Tui\Widget\HorizontalRuleWidget;

final class HorizontalRuleWidgetTest extends TestCase
{
    public function testFillsTheWidth()
    {
        $this->assertSame(['─────'], (new HorizontalRuleWidget())->render(new RenderContext(5, 3)));
        $this->assertSame(['==='], (new HorizontalRuleWidget('='))->render(new RenderContext(3, 3)));
        $this->assertSame([''], (new HorizontalRuleWidget())->render(new RenderContext(0, 3)));
    }
}
