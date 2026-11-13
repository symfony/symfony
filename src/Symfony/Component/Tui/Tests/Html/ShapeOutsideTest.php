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

use PHPUnit\Framework\TestCase;
use Symfony\Component\Tui\Html\HtmlBuilder;
use Symfony\Component\Tui\Terminal\ScreenBuffer;
use Symfony\Component\Tui\Terminal\VirtualTerminal;
use Symfony\Component\Tui\Tui;

final class ShapeOutsideTest extends TestCase
{
    private const TEXT = '<p>ab cd ef gh ij kl mn op qr st uv wx yz</p>';

    public function testTheLinesWrapAroundACircle()
    {
        $lines = $this->render('<div style="float: left; width: 12ch; height: 6lh; shape-outside: circle()"></div>'.self::TEXT, 24);

        // 12 columns and 6 rows two columns tall: a circle 6 columns wide each way, narrower at its top and bottom rows
        $this->assertSame(['            ab cd ef gh', '             ij kl mn op', '             qr st uv wx', '             yz'], $lines);
    }

    public function testTheTextComesOverTheFloatWhereItsShapeLeavesRoom()
    {
        $float = '<div style="float: left; width: 6ch; shape-outside: %s">######<br>######<br>######</div>';

        $this->assertSame(['####ab cd ef gh', '####ij kl mn op', '######'], $this->render(\sprintf($float, 'circle(50% at 0 0)').'<p>ab cd ef gh ij kl mn op</p>', 16), 'The float drawn under the text beside its shape.');
        $this->assertSame(['ab#cd#ef gh ij', 'kl#mn#op', '######'], $this->render(\sprintf($float, 'inset(0 100% 0 0)').'<p>ab cd ef gh ij kl mn op</p>', 16), 'No shape left: the text over all of it, the float between the words.');
        $this->assertSame(['ab#cd#ef gh ij', 'kl#mn#op', '######'], $this->render(\sprintf($float, 'ellipse(closest-side farthest-side at left)').'<p>ab cd ef gh ij kl mn op</p>', 16), 'No room from the left edge: no ellipse.');
    }

    public function testTheShapeOfAFloatAtTheRight()
    {
        $lines = $this->render('<div style="float: right; width: 6ch; height: 3lh; shape-outside: polygon(100% 0, 100% 100%, 0 100%)"></div>'.self::TEXT, 16);

        $this->assertSame(['ab cd ef gh', 'ij kl mn op', 'qr st uv', 'wx yz'], $lines, 'Rows wider down the slope: 2, 4 and 6 columns, each a column apart.');
    }

    public function testABoxOfTheFloatAndTheShapeMargin()
    {
        $float = '<div style="float: left; width: 8ch; padding-right: 2ch; %s">######<br>######<br>######</div><p>ab cd ef gh ij kl mn op</p>';

        $this->assertSame(['###### ab cd ef', '###### gh ij kl', '###### mn op'], $this->render(\sprintf($float, 'shape-outside: content-box'), 16), 'Its content box: closer than its padding.');
        $this->assertSame(['######  ab cd ef', '######  gh ij kl', '######  mn op'], $this->render(\sprintf($float, 'shape-outside: content-box; shape-margin: 1ch'), 16));
        $this->assertSame(['######   ab cd', '######   ef gh', '######   ij kl', 'mn op'], $this->render(\sprintf($float, 'shape-outside: content-box; shape-margin: 9ch'), 16), 'Clipped to its margin box, sideways and down.');
        $this->assertSame(['######   ab cd', '######   ef gh', '######   ij kl', 'mn op'], $this->render(\sprintf($float, 'shape-outside: margin-box'), 16), 'Like no shape.');
    }

    public function testInvalidShapesAndImagesKeepTheBox()
    {
        $expected = $this->render('<div style="float: left; width: 6ch; height: 3lh"></div>'.self::TEXT, 20);

        foreach (['bogus(1)', 'url(a.png)', 'linear-gradient(red, blue)', 'circle(-1ch)', 'ellipse(1ch)', 'polygon(0 0, 1ch 1ch)', 'inset(1ch round 1ch / 1ch / 1ch)', 'circle() circle()', 'border-box padding-box'] as $shape) {
            $this->assertSame($expected, $this->render('<div style="float: left; width: 6ch; height: 3lh; shape-outside: '.$shape.'"></div>'.self::TEXT, 20), $shape);
        }
    }

    public function testFloatsBesideAShapedFloatStayBesideItsBox()
    {
        $lines = $this->render('<div style="float: left; width: 4ch; height: 2lh; shape-outside: inset(0 100% 0 0)">aaaa</div><div style="float: left; width: 2ch">bb</div>'.self::TEXT, 20);

        $this->assertSame('aaaa bb ab cd ef gh', $lines[0], 'The second float after the box of the first, the text after it.');
    }

    public function testTheShapeOfAFloatReachesTheParagraphsInABlock()
    {
        $lines = $this->render('<div style="float: left; width: 12ch; height: 6lh; shape-outside: circle()"></div><div style="padding-left: 1ch">'.self::TEXT.'</div>', 24);

        $this->assertSame(['            ab cd ef gh', '             ij kl mn op', '             qr st uv wx', '             yz'], $lines, 'Its padding within the room the shape takes.');
    }

    public function testTheLinesWrapAroundTheOpaquePixelsOfAnImage()
    {
        $image = imagecreatetruecolor(64, 64);
        imagesavealpha($image, true);
        imagealphablending($image, false);
        imagefill($image, 0, 0, imagecolorallocatealpha($image, 0, 0, 0, 127) ?: 0);
        imagefilledpolygon($image, [0, 0, 63, 63, 0, 63], imagecolorallocate($image, 200, 50, 50) ?: 0);
        ob_start();
        imagepng($image);
        $src = 'data:image/png;base64,'.base64_encode((string) ob_get_clean());

        $lines = $this->render('<img src="'.$src.'" alt="t" style="float: left; shape-outside: url('.$src.')"><p>ab cd ef gh ij kl mn op qr st uv wx yz ab cd ef gh ij kl mn op qr st uv wx yz</p>', 18);

        // A triangle 8 columns wide and 4 rows tall, wider down
        $this->assertSame([3, 5, 7, 9, 0], array_map(static fn (string $line): int => preg_match('/\p{L}/u', $line, $m, \PREG_OFFSET_CAPTURE) ? mb_strlen(substr($line, 0, $m[0][1])) : -1, \array_slice($lines, 0, 5)), 'The column each line starts at.');
    }

    /**
     * @return list<string>
     */
    private function render(string $html, int $width): array
    {
        $terminal = new VirtualTerminal($width, 12);
        $tui = new Tui(terminal: $terminal);
        (new HtmlBuilder())->build('<style>p { margin: 0 }</style>'.$html)->mount($tui);
        $tui->start();
        $tui->processRender();
        $buffer = new ScreenBuffer($width, 12);
        $buffer->write($terminal->consumeOutput());

        return array_values(array_filter(array_map('rtrim', explode("\n", $buffer->getScreen())), static fn (string $line) => '' !== $line));
    }
}
