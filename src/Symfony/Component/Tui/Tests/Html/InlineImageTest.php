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
use Symfony\Component\Tui\Css\CssStyleSheet;
use Symfony\Component\Tui\Css\StylesheetLoaderInterface;
use Symfony\Component\Tui\Html\HtmlBuilder;
use Symfony\Component\Tui\Style\ColorScheme;
use Symfony\Component\Tui\Style\MediaContext;
use Symfony\Component\Tui\Terminal\ScreenBuffer;
use Symfony\Component\Tui\Terminal\VirtualTerminal;
use Symfony\Component\Tui\Tui;
use Symfony\Component\Tui\Widget\RichTextWidget;

#[RequiresPhpExtension('gd')]
final class InlineImageTest extends TestCase
{
    public function testAnImageInALinkIsDrawnInTheLine()
    {
        [$lines, $output] = $this->render('<p style="color: cyan">Go <a href="/home"><img src="'.self::png(16, 16).'" alt="home"> Home</a> now</p>', 30);

        $this->assertSame(['Go ▀▀ Home now'], $lines, 'A row tall, two columns for a square one.');
        $buffer = new ScreenBuffer(30, 6);
        $buffer->write($output);
        $this->assertSame("\e[38;2;255;0;0;48;2;0;0;255m", $buffer->getCells()[0][3]['style']);
        $this->assertSame("\e[4;36m", $buffer->getCells()[0][6]['style']);
    }

    public function testItsProportionsAndTheWrap()
    {
        $lines = $this->render('<p><b>A <img src="'.self::png(16, 16).'" width="64" height="16" alt="banner"> banner</b> in a narrow block</p>', 12)[0];

        $this->assertSame(['A ▀▀▀▀▀▀▀▀', 'banner in a', 'narrow block'], $lines, 'Eight columns for 64 by 16 CSS pixels, wrapped like a word.');
    }

    public function testAnImageThatCannotBeDrawnIsItsTextAlternative()
    {
        $this->assertSame(['Go [broken] now'], $this->render('<p>Go <a href="/x"><img src="missing.png" alt="broken"></a> now</p>', 30)[0]);
    }

    public function testThePlainTextHasItsTextAlternative()
    {
        $view = (new HtmlBuilder())->build('<p id="p">Go <a href="/home"><img src="'.self::png(16, 16).'" alt="home"> Home</a>, '."\u{100000}".'not an image</p>');
        $text = $view->getById('p');
        $this->assertInstanceOf(RichTextWidget::class, $text);

        $this->assertSame('Go [home] Home, not an image', $text->getPlainText(), 'The characters standing for images in the text of the page are dropped.');
    }

    public function testAnImageAfterALinkInTextEndingWithAnM()
    {
        // The end of the link and the text after it, an image among them, are not a color code
        $this->assertSame(['x ▀▀ m'], $this->render('<p><a href="/a">x</a> <span><img src="'.self::png(16, 16).'" alt="i"></span> m</p>', 20)[0]);
    }

    public function testAnImageIsDecodedOnceAndTheirPixelsAreBounded()
    {
        $view = (new HtmlBuilder())->build('<p id="p">'.str_repeat('<a href="/"><img src="'.self::png(16, 16).'" alt="i"></a>', 3).'</p>');
        $text = $view->getById('p');
        $this->assertInstanceOf(RichTextWidget::class, $text);
        $images = (new \ReflectionProperty(RichTextWidget::class, 'inlineImages'))->getValue($text);
        $this->assertCount(3, $images);
        $this->assertSame($images[0][0], $images[2][0], 'The same image for the same address.');

        // Four of about 2048 by 2048 pixels are as many as a document may decode for its text: the fifth is its alt text
        $html = '<p id="p">'.implode('', array_map(static fn (int $i) => '<a href="/"><img src="'.self::png(2048, 2048 - $i).'" alt="big'.$i.'"></a>', range(1, 5))).'</p>';
        $text = (new HtmlBuilder())->build($html)->getById('p');
        $this->assertInstanceOf(RichTextWidget::class, $text);
        $this->assertCount(4, (new \ReflectionProperty(RichTextWidget::class, 'inlineImages'))->getValue($text));
        $this->assertStringNotContainsString('[big4]', $text->getText(), 'Drawn.');
        $this->assertStringContainsString('[big5]', $text->getText(), 'Past the pixels: its alt text.');
    }

    public function testGeneratedContentDrawsImages()
    {
        $png = self::png(16, 16);
        $css = '<style>a[href^="https:"]::after { content: " " url("'.$png.'") } .new::before { content: url('.$png.') " new " } .gone::before { content: url(missing.png) "!" } li::marker { content: url('.$png.') " " }</style>';
        [$lines, $output] = $this->render($css.'<p>See <a href="https://example.com">site</a>, <span class="new">it</span> <span class="gone">m</span></p><ul><li>item</li></ul>', 40);

        $this->assertSame(['See site ▀▀, ▀▀ new it !m', '▀▀ item'], $lines, 'An image like any content, nothing for one that cannot be read; a marker too.');
        $this->assertMatchesRegularExpression('/\e\]8;[^\a\e]*;https:\/\/example\.com(?:(?!\e\]8;;).)*▀/s', $output, 'Inside its link.');
    }

    public function testAnSvgIconIsDrawnInTheColorOfItsText()
    {
        $icon = '<svg viewBox="0 0 24 24"><title>%s</title><rect width="24" height="24" fill="currentColor"/></svg>';
        [$lines, $output] = $this->render('<p style="color: #00ff00">Go <a href="/home">'.\sprintf($icon, 'home').' Home</a>, <span style="color: rgb(255 0 0)">'.\sprintf($icon, 'stop').'</span> <a href="/x"><svg><title>empty</title></svg> x</a></p>', 40);

        $this->assertSame(['Go ▀▀ Home, ▀▀ [empty] x'], $lines, 'Two columns for a square view box; its title without shapes.');
        $this->assertStringContainsString("\e[38;2;0;255;0;48;2;0;255;0m▀", $output, 'currentColor: the green of the link.');
        $this->assertStringContainsString("\e[38;2;255;0;0;48;2;255;0;0m▀", $output, 'The red of its span.');

        $thin = '<svg viewBox="0 0 24 24"><title>close</title><path d="M4 4 L20 20" stroke="currentColor"/></svg>';
        $this->assertSame(['a [close] link'], $this->render('<p>a <a href="/">'.$thin.' link</a></p>', 40)[0], 'Too thin to show in two cells: its title.');

        $view = (new HtmlBuilder())->build('<p id="p">Go <a href="/home">'.\sprintf($icon, 'home').' Home</a></p>');
        $text = $view->getById('p');
        $this->assertInstanceOf(RichTextWidget::class, $text);
        $this->assertSame('Go [home] Home', $text->getPlainText());
    }

    public function testTheIconOfAButtonIsDrawnInItsColor()
    {
        $icon = '<svg viewBox="0 0 24 24"><title>Save</title><rect width="24" height="24" fill="currentColor"/></svg>';
        [$lines, $output] = $this->render('<button autofocus style="color: #00ff00">'.$icon.' Save</button> <button><svg viewBox="0 0 24 24"><title>Close</title><path d="M4 4 L20 20" stroke="currentColor"/></svg></button>', 40);

        $this->assertSame(['[ ▀▀ Save ] [ Close ]'], $lines, 'A thin icon: its title.');
        $this->assertStringContainsString("\e[38;2;0;0;0;48;2;0;0;0m▀", $output, 'Focused, in reverse video: in the color its glyphs are drawn in, not green on green.');
        $buffer = new ScreenBuffer(40, 6);
        $buffer->write($output);
        $this->assertSame("\e[7;38;2;0;255;0m", $buffer->getCells()[0][5]['style']);
    }

    public function testTheAddressOfALinkIsNotText()
    {
        [$lines, $output] = $this->render('<p><a href="https://x.test/a&#x100000;b">link</a> <a href="#"><img src="'.self::png(16, 16).'" alt="i"></a></p>', 30);

        $this->assertSame(['link ▀▀'], $lines);
        $this->assertStringContainsString("https://x.test/a\u{100000}b\x07", $output, 'Its hyperlink as it is.');
    }

    public function testAuthorsCannotDrawTheImagesOfOthers()
    {
        $png = self::png(16, 16);
        $lines = $this->render('<style>.x::before { content: "\\100000\\100001" attr(data-x) }</style><p>hi <a href="#"><img src="'.$png.'" alt="i"></a> <a href="#"><img src="bad" alt="&#x100000;&#x100001;"></a> <span class="x" data-x="&#x100000;">t</span></p>', 30)[0];

        $this->assertSame(['hi ▀▀ t'], $lines, 'Nor from a text alternative, emptied, nor from generated content.');
    }

    public function testThePlainTextsOfThePageHaveTheTextAlternative()
    {
        $lines = $this->render('<details><summary><a href="#"><img src="'.self::png(16, 16).'" alt="Icon"></a> More</summary>x</details>', 30)[0];

        $this->assertSame('▶ [Icon] More', $lines[0], 'The text of a summary is plain.');
    }

    public function testAShadowDoesNotCopyTheImages()
    {
        [, $output] = $this->render('<p style="text-shadow: 2px 1px #00ff00">ab <a href="#"><img src="'.self::png(16, 16).'" alt="i"></a> cd</p>', 30);

        $this->assertSame(2, substr_count($output, '▀'), 'Drawn once, over its shadow, which leaves it out.');
    }

    public function testLetterSpacingKeepsAnImageWhole()
    {
        $this->assertSame(['a b ▀▀ c d'], $this->render('<p style="letter-spacing: 1ch">ab <a href="#"><img src="'.self::png(16, 16).'" alt="i"></a> cd</p>', 30)[0]);
    }

    public function testTheImagesOfStyleSheetsAreDecodedWhenDrawn()
    {
        $view = (new HtmlBuilder())->build('<style>.hidden { display: none; background: url('.self::png(16, 16).') } p::before { content: url('.self::png(16, 8).') }</style><div class="hidden">x</div><p>text</p>');
        $tui = new Tui(terminal: new VirtualTerminal(20, 3));
        $view->mount($tui);
        $tui->start();
        $tui->processRender();

        $decoded = array_map(static fn ($image) => null !== $image && null !== (new \ReflectionProperty($image, 'image'))->getValue($image), (new \ReflectionProperty($view->getStyleSheet(), 'images'))->getValue($view->getStyleSheet()));
        $this->assertSame([false, true], array_values($decoded), 'The background of a hidden element is read, not decoded; the image drawn is.');
    }

    public function testEachImageAddressOfTheStyleSheetsIsReadOnceAndTheyAreBounded()
    {
        $png = base64_decode(substr(self::png(16, 16), 22));
        $loader = new class($png) implements StylesheetLoaderInterface {
            /** @var array<string, int> */
            public array $loads = [];

            public function __construct(private readonly string $png)
            {
            }

            public function load(string $href): string
            {
                $this->loads[$href] = ($this->loads[$href] ?? 0) + 1;

                return $this->png;
            }
        };
        $rules = implode(' ', array_map(static fn (int $i) => '.i'.$i.'::before { content: url(i'.$i.'.png) }', range(1, 300)));
        $view = (new HtmlBuilder(new CssStyleSheet($loader)))->build('<style>'.$rules.'</style>'.implode('', array_map(static fn (int $i) => '<p class="i'.$i.'">x</p>', range(1, 300))));
        $tui = new Tui(terminal: new VirtualTerminal(20, 400));
        $view->mount($tui);
        $tui->start();
        $tui->processRender();
        // Styled again, in another color scheme
        $tui->setMediaContext(new MediaContext(colorScheme: ColorScheme::Light));
        $tui->processRender();

        $this->assertCount(256, $loader->loads, 'As many addresses as a page may read; past them, none.');
        $this->assertSame([1], array_values(array_unique($loader->loads)), 'Each one read once, never again.');
    }

    public function testInReverseVideoAnIconIsInTheColorOfTheGlyphs()
    {
        $terminal = new VirtualTerminal(20, 3);
        $tui = new Tui(terminal: $terminal);
        $view = (new HtmlBuilder())->build('<button id="b" style="color: #00ff00"><svg viewBox="0 0 24 24"><title>Save</title><rect width="24" height="24" fill="currentColor"/></svg> Save</button>')->mount($tui);
        $tui->start();
        $tui->setFocus($view->getById('b'));
        $tui->processRender();
        $output = $terminal->consumeOutput();

        $this->assertStringContainsString('7', $output, 'Focused: in reverse video.');
        $this->assertStringNotContainsString("\e[38;2;0;255;0;48;2;0;255;0m▀", $output, 'Not green on the green the reverse video shows.');
        $this->assertStringContainsString("\e[38;2;0;0;0;48;2;0;0;0m▀", $output, 'In the dark the glyphs are drawn in.');
    }

    public function testTheImagesOfThePseudoElementsOfBlocksRowsAndGrids()
    {
        $png = self::png(16, 16);
        $lines = $this->render('<style>.f { display: flex; gap: 1ch } .f::before { content: url('.$png.') "t" } .g { display: grid; grid-template-columns: 1fr 1fr } .g::after { content: "end " url('.$png.') } .b::before { content: url('.$png.') " block" }</style><div class="f"><span>a</span></div><div class="g"><span>x</span></div><div class="b"><p>para</p></div>', 30)[0];

        $this->assertSame(['▀▀t a', 'x              end ▀▀', '▀▀ block', 'para'], $lines, 'An item of a row, of a grid, and the line of a block.');
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
    private function render(string $html, int $width): array
    {
        $terminal = new VirtualTerminal($width, 6);
        $tui = new Tui(terminal: $terminal);
        (new HtmlBuilder())->build('<style>p { margin: 0 }</style>'.$html)->mount($tui);
        $tui->start();
        $tui->processRender();
        $output = $terminal->consumeOutput();
        $buffer = new ScreenBuffer($width, 6);
        $buffer->write($output);

        return [array_values(array_filter(array_map('rtrim', explode("\n", $buffer->getScreen())), static fn (string $line) => '' !== $line)), $output];
    }
}
