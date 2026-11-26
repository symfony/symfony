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

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Bridge\TuiHtml\Tests\Compatibility\FacadeScenario;
use Symfony\Component\Tui\Ansi\AnsiUtils;

class FacadeFloatPolicyTest extends TestCase
{
    #[DataProvider('provideText')]
    public function testFloatExclusionsKeepTheTerminalGap(string $text, int $pixelRatio)
    {
        $html = '<style>.right{float:right;width:10ch;height:4lh;margin:0}.left{float:left;width:4ch;height:2lh}p{margin:0}</style><article><aside class="right">Note</aside><div class="left">AL</div><p>'.$text.'</p></article>';
        $options = ['columns' => 36, 'rows' => 15, 'dppx' => $pixelRatio];
        $plain = static fn (string $output): string => AnsiUtils::stripAnsiCodes(str_replace('⟨', "\x1b[", $output));

        self::assertSame($plain(FacadeScenario::render($html, $options)), $plain(FacadeScenario::render($html, $options, true)));
    }

    public static function provideText(): iterable
    {
        foreach ([1, 2] as $pixelRatio) {
            yield 'short '.$pixelRatio => ['Ada short', $pixelRatio];
            yield 'wrapping '.$pixelRatio => ['Ada Lovelace wrote the first program, for an engine that was never built; the text beside the floats takes the room between them.', $pixelRatio];
        }
    }
}
