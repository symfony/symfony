<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Tui\Tests\Html;

use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Tui\Html\HtmlBuilder;
use Symfony\Component\Tui\Terminal\VirtualTerminal;
use Symfony\Component\Tui\Tui;

final class BackgroundLayersTest extends TestCase
{
    public function testTheFirstLayerIsOverTheOthers()
    {
        $cells = $this->colors('<div style="width: 4ch; height: 1; background-image: linear-gradient(to right, #ff0000 50%, transparent 50%), linear-gradient(#0000ff, #0000ff)"></div>');

        $this->assertSame(['ff0000', 'ff0000', '0000ff', '0000ff'], $cells, 'Red over its half, the blue under it elsewhere.');
    }

    public function testTranslucentColorsAreBlendedOverTheLayersUnder()
    {
        $cells = $this->colors('<div style="width: 2ch; height: 1; background: linear-gradient(rgb(0 0 0 / .5), rgb(0 0 0 / .5)), linear-gradient(#ffffff, #ffffff)"></div>', 2);

        $this->assertSame(['808080', '808080'], $cells, 'Half black over white.');
    }

    public function testEachLayerHasItsLayoutAndTheColorIsUnderThemAll()
    {
        // A small square at the right of the first row, over a gradient covering the box, over the color
        $cells = $this->colors('<div style="width: 4ch; height: 2; background: linear-gradient(#ff0000, #ff0000) no-repeat right top / 1ch 1, linear-gradient(to right, transparent 50%, #0000ff 50%) #00ff00"></div>');

        $this->assertSame(['00ff00', '00ff00', '0000ff', 'ff0000', '00ff00', '00ff00', '0000ff', '0000ff'], $cells);
    }

    public function testSizesAndPositionsRepeatForTheLayersPastThem()
    {
        $cells = $this->colors('<div style="width: 4ch; height: 1; background-image: linear-gradient(#ff0000, #ff0000), linear-gradient(#0000ff, #0000ff); background-repeat: no-repeat; background-size: 1ch 1, 2ch 1; background-position: left"></div>');

        $this->assertSame(['ff0000', '0000ff', 'none', 'none'], $cells, 'no-repeat for both, each of its size: nothing past the second.');
    }

    public function testEachLayerIsBlendedWithWhatIsUnderItByItsMode()
    {
        // Red multiplied over white then blue; the blue in screen mode over the color, the list repeating
        $cells = $this->colors('<div style="width: 4ch; height: 1; background: linear-gradient(to right, #ff0000 50%, transparent 50%), linear-gradient(to right, #ffffff 25%, #0000ff 25%) #404040; background-blend-mode: multiply, screen"></div>');

        $this->assertSame(['ff0000', '400000', '4040ff', '4040ff'], $cells, 'Red times the white screened over gray, red times the blue screened over it, then that blue.');
    }

    public function testWithoutABackgroundColorTheLastLayerHasNothingToBlendWith()
    {
        $cells = $this->colors('<div style="width: 2ch; height: 1; background: linear-gradient(#ff0000, #ff0000), linear-gradient(#ffff00, #ffff00); background-blend-mode: multiply"></div>', 2);

        $this->assertSame(['ff0000', 'ff0000'], $cells, 'The yellow as it is, the red multiplied over it; not both over the terminal background.');
    }

    public function testTheLayersThatCanBeDrawnAre()
    {
        $this->assertSame(['ff0000', 'ff0000'], $this->colors('<div style="width: 2ch; height: 1; background-image: url(missing.png), linear-gradient(#ff0000, #ff0000)"></div>', 2), 'A missing image over a gradient: the gradient.');
    }

    public function testATranslucentBorderImageIsOverWhatIsBehind()
    {
        $terminal = new VirtualTerminal(10, 3);
        $tui = new Tui(terminal: $terminal);
        (new HtmlBuilder())->build('<div style="background: #ffffff"><div style="width: 5ch; border: 1px solid; border-image: linear-gradient(to right, transparent, #ff0000) 1">x</div></div>')->mount($tui);
        $tui->start();
        $tui->processRender();

        $this->assertSame(1, preg_match('/38;2;(\d+);(\d+);(\d+)m┌/', $terminal->consumeOutput(), $corner));
        $this->assertGreaterThan(200, (int) $corner[2], 'Its transparent end over the white, not from black.');
    }

    #[RequiresPhpExtension('gd')]
    public function testAnImageUnderAGradient()
    {
        $image = imagecreatetruecolor(2, 2);
        imagefill($image, 0, 0, imagecolorallocate($image, 255, 255, 255) ?: 0);
        ob_start();
        imagepng($image);
        $png = 'data:image/png;base64,'.base64_encode((string) ob_get_clean());

        $cells = $this->colors('<div style="width: 2ch; height: 1; background: linear-gradient(to right, transparent 50%, rgb(0 0 0 / .5) 50%), url('.$png.') 0 0 / 100% 100%"></div>', 2);

        $this->assertSame(['ffffff', '808080'], $cells, 'The photo, darkened on its right.');
    }

    /**
     * The background colors of the cells of the box, row after row: those of the first $columns cells of each row.
     *
     * @return list<string>
     */
    private function colors(string $html, int $columns = 4): array
    {
        $terminal = new VirtualTerminal(10, 3);
        $tui = new Tui(terminal: $terminal);
        (new HtmlBuilder())->build($html)->mount($tui);
        $tui->start();
        $tui->processRender();
        $cells = [];
        foreach (explode("\n", $terminal->consumeOutput()) as $line) {
            $background = 'none';
            $row = [];
            foreach (preg_split('/(\e\[[0-9;?]*[A-Za-z]|\e\][^\a\e]*(?:\a|\e\\\\))/', $line, -1, \PREG_SPLIT_DELIM_CAPTURE | \PREG_SPLIT_NO_EMPTY) ?: [] as $part) {
                if ("\e" === $part[0]) {
                    if (preg_match('/^\e\[([0-9;]*)m$/', $part, $m)) {
                        if (preg_match('/(?:^|;)48;2;(\d+);(\d+);(\d+)/', $m[1], $c)) {
                            $background = \sprintf('%02x%02x%02x', $c[1], $c[2], $c[3]);
                        } elseif (preg_match('/(?:^|;)(?:0|49)?$/', $m[1]) && !str_contains($m[1], '48;')) {
                            $background = 'none';
                        }
                    }
                    continue;
                }
                foreach (mb_str_split($part) as $char) {
                    if ("\r" !== $char) {
                        $row[] = $background;
                    }
                }
            }
            if ($row && 'none' !== $row[0]) {
                array_push($cells, ...\array_slice($row, 0, $columns));
            }
        }

        return $cells;
    }
}
