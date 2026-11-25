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

final class FacadeFailedImageTest extends TestCase
{
    public function testNativeFailedImageFallbackPreservesItsSurroundingText()
    {
        foreach (['', 'missing'] as $alternative) {
            $html = '<style>img{border:1px solid}</style><p style="margin:0">Before <img src="data:image/png;base64,aW52YWxpZA==" width="20" height="20" alt="'.$alternative.'"> after.</p>';
            $options = ['columns' => 40, 'rows' => 6];
            self::assertSame(
                TerminalCellSnapshot::normalize(FacadeScenario::render($html, $options), 40, 6, [229, 229, 229], [0, 0, 0]),
                TerminalCellSnapshot::normalize(FacadeScenario::render($html, $options, true), 40, 6, [229, 229, 229], [0, 0, 0]),
                'Alternative: '.$alternative,
            );
        }
    }
}
