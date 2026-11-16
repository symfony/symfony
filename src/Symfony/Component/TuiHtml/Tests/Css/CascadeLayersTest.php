<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\TuiHtml\Tests\Css;

use PHPUnit\Framework\TestCase;
use Symfony\Component\TuiHtml\Css\CssStyleSheet;
use Symfony\Component\TuiHtml\Css\FilesystemStylesheetLoader;
use Symfony\Component\TuiHtml\Html\HtmlBuilder;
use Symfony\Component\TuiHtml\Html\HtmlView;
use Symfony\Component\TuiHtml\Style\Color;
use Symfony\Component\TuiHtml\Style\Style;

final class CascadeLayersTest extends TestCase
{
    public function testUnlayeredRulesWinWhateverTheSpecificity()
    {
        $this->assertSame('red', $this->color('@layer base { #x.a.b { color: blue } } p { color: red }'));
    }

    public function testLaterLayersWin()
    {
        $this->assertSame('green', $this->color('@layer one { #x { color: blue } } @layer two { p { color: green } }'));
    }

    public function testTheFirstMentionSetsTheOrder()
    {
        // two is declared before one: one comes later, and wins
        $this->assertSame('blue', $this->color('@layer two, one; @layer one { p { color: blue } } @layer two { #x { color: green } }'));
    }

    public function testImportantReversesTheOrder()
    {
        $this->assertSame('blue', $this->color('@layer one { p { color: blue !important } } @layer two { p { color: green !important } } p { color: red !important }'));
    }

    public function testTheRulesOfALayerWinOverItsSublayers()
    {
        $this->assertSame('blue', $this->color('@layer a { p { color: blue } @layer b { #x { color: green } } }'));
        $this->assertSame('yellow', $this->color('@layer a.b { #x { color: green } } @layer c { p { color: yellow } } @layer a { p { color: blue } }'), 'c comes after a, sublayers included.');
    }

    public function testAnonymousLayersAreEachTheirOwn()
    {
        $this->assertSame('green', $this->color('@layer { #x { color: blue } } @layer { p { color: green } }'));
    }

    public function testImportIntoALayer()
    {
        $dir = sys_get_temp_dir().'/tui-layers-'.bin2hex(random_bytes(4));
        mkdir($dir);
        file_put_contents($dir.'/theme.css', '#x { color: blue }');
        try {
            $sheet = new CssStyleSheet(new FilesystemStylesheetLoader([$dir]));
            $sheet->add('@import "theme.css" layer(theme); p { color: green }');
            $this->assertSame('green', $this->color('', $sheet));
        } finally {
            unlink($dir.'/theme.css');
            rmdir($dir);
        }
    }

    public function testTheUserAgentStylesComeBeforeLayeredAuthorStyles()
    {
        // The user agent stylesheet makes headings bold: an author layer, however early, undoes it
        $view = (new HtmlBuilder())->build('<style>@layer reset { h1 { font-weight: normal } }</style><h1 id="x">Title</h1>');
        $view->computeStyles();
        $this->assertFalse($this->styleOf($view)->getBold());
    }

    private function styleOf(HtmlView $view): Style
    {
        $widget = $view->getById('x');
        $this->assertNotNull($widget);

        return $view->getStyleSheet()->getStyles($widget)[0] ?? new Style();
    }

    private function color(string $css, ?CssStyleSheet $sheet = null): ?string
    {
        $sheet ??= new CssStyleSheet();
        $sheet->add($css);
        $view = (new HtmlBuilder($sheet))->build('<p id="x" class="a b">text</p>');
        $view->computeStyles();
        $color = $this->styleOf($view)->getColor();
        foreach (['red', 'blue', 'green', 'yellow'] as $name) {
            if ($color == Color::from($name)) {
                return $name;
            }
        }

        return null;
    }
}
