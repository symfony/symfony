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
use Symfony\Component\Tui\Style\MediaContext;
use Symfony\Component\Tui\Terminal\ScreenBuffer;
use Symfony\Component\Tui\Terminal\VirtualTerminal;
use Symfony\Component\Tui\Tui;

#[RequiresPhpExtension('gd')]
final class ListStyleImageTest extends TestCase
{
    public function testTheImageIsTheMarker()
    {
        [$lines, $output] = $this->render('<ul style="list-style-image: url('.self::png(16, 16).')"><li>one</li><li style="list-style-image: none">two</li></ul>');

        $this->assertSame(['▀▀ one', '• two'], $lines, 'A square image: two columns a row tall.');
        $this->assertStringContainsString("\e[38;2;255;0;0;48;2;0;0;255m▀", $output, 'Its top half red, its bottom half blue.');
    }

    public function testItsWidthFollowsItsProportions()
    {
        $this->assertSame(['▀▀▀▀ wide', '▀ tall'], $this->render('<ul><li style="list-style-image: url('.self::png(64, 16).')">wide</li><li style="list-style: url('.self::png(8, 16).') square">tall</li></ul>')[0], 'From 1 to 4 columns, from the shorthand too.');
    }

    public function testTheTypeWhenTheImageIsNotDrawn()
    {
        $this->assertSame(['▪ missing'], $this->render('<ul style="list-style: square url(nope.png)"><li>missing</li></ul>')[0], 'Not loaded.');
        $this->assertSame(['• gradient'], $this->render('<ul style="list-style-image: linear-gradient(red, blue)"><li>gradient</li></ul>')[0]);
        $this->assertSame(['• inside'], $this->render('<ul style="list-style: inside url('.self::png(16, 16).')"><li>inside</li></ul>')[0], 'In the text, where no image goes.');
        $this->assertSame(['• forced'], $this->render('<ul style="list-style-image: url('.self::png(16, 16).')"><li>forced</li></ul>', new MediaContext(forcedColors: true))[0]);
        $this->assertSame(['- custom'], $this->render('<style>li::marker { content: "- " }</style><ul style="list-style-image: url('.self::png(16, 16).')"><li>custom</li></ul>')[0], 'The content of ::marker first.');
    }

    public function testRightToLeftTheImageIsAtTheRight()
    {
        $this->assertSame(['          one ▀▀'], $this->render('<ul dir="rtl" style="list-style-image: url('.self::png(16, 16).')"><li>one</li></ul>', null, 16)[0]);
    }

    private static function png(int $width, int $height): string
    {
        $image = imagecreatetruecolor($width, $height);
        imagefilledrectangle($image, 0, 0, $width - 1, intdiv($height, 2) - 1, imagecolorallocate($image, 255, 0, 0) ?: 0);
        imagefilledrectangle($image, 0, intdiv($height, 2), $width - 1, $height - 1, imagecolorallocate($image, 0, 0, 255) ?: 0);
        ob_start();
        imagepng($image);

        return 'data:image/png;base64,'.base64_encode((string) ob_get_clean());
    }

    /**
     * @return array{list<string>, string}
     */
    private function render(string $html, ?MediaContext $media = null, int $width = 20): array
    {
        $terminal = new VirtualTerminal($width, 6);
        $tui = new Tui(terminal: $terminal);
        if (null !== $media) {
            $tui->setMediaContext($media);
        }
        (new HtmlBuilder())->build('<style>ul { margin: 0; padding: 0 }</style>'.$html)->mount($tui);
        $tui->start();
        $tui->processRender();
        $output = $terminal->consumeOutput();
        $buffer = new ScreenBuffer($width, 6);
        $buffer->write($output);

        return [array_values(array_filter(array_map('rtrim', explode("\n", $buffer->getScreen())), static fn (string $line) => '' !== $line)), $output];
    }
}
