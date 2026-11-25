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

use PHPUnit\Framework\TestCase;
use Symfony\Bridge\TuiHtml\Tests\Compatibility\FacadeScenario;
use Symfony\Bridge\TuiHtml\Tests\Compatibility\TerminalCellSnapshot;

final class OutlineRenderingTest extends TestCase
{
    public function testNativeOutlinePatternsOffsetsAndColorsRemainDevicePolicy()
    {
        foreach (['1px solid red', '1px dashed cyan', 'thick solid yellow', '1px double green', '20px solid red', '0 solid red', '1px solid transparent'] as $outline) {
            $html = '<p style="margin:3 3;width:8ch;outline:'.$outline.';outline-offset:1">Label</p>';
            $options = ['columns' => 20, 'rows' => 10];
            self::assertSame(
                TerminalCellSnapshot::normalize(FacadeScenario::render($html, $options), 20, 10, [229, 229, 229], [0, 0, 0]),
                TerminalCellSnapshot::normalize(FacadeScenario::render($html, $options, true), 20, 10, [229, 229, 229], [0, 0, 0]),
                $outline,
            );
        }
    }
}
