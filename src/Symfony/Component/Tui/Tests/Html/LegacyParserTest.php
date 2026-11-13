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
use Symfony\Component\Tui\Html\LegacyParser;
use Symfony\Component\Tui\Widget\DropdownWidget;

#[RequiresPhpExtension('dom')]
final class LegacyParserTest extends TestCase
{
    public function testEverySelectKeepsItsRichOptions()
    {
        [$html, $tag] = LegacyParser::prepare('<!doctype html><select id="a"><option>A <b>one</b></option></select><select id="b"><option>B <i>two</i></select>');
        $document = \Dom\HTMLDocument::createFromString($html, \LIBXML_NOERROR);
        LegacyParser::restore($document, $tag);

        $this->assertCount(2, $document->querySelectorAll('select'));
        $this->assertSame('one', $document->querySelector('#a option b')->textContent);
        $this->assertSame('two', $document->querySelector('#b option i')->textContent);
    }

    public function testRawTextCommentsAndAttributesAreUnchanged()
    {
        $html = '<!doctype html><title>Literal &lt;select&gt;</title><style>p::before { content: "<select>" }</style><script>const html = "<select></select>";</script><!-- <select> --><div title="quoted > <select>">x</div><textarea>&lt;select&gt;</textarea><select><option><b>x</b></option></select>';
        [$prepared, $tag] = LegacyParser::prepare($html);
        $document = \Dom\HTMLDocument::createFromString($prepared, \LIBXML_NOERROR);
        LegacyParser::restore($document, $tag);

        $this->assertSame('Literal <select>', $document->title);
        $this->assertSame('p::before { content: "<select>" }', $document->querySelector('style')->textContent);
        $this->assertSame('const html = "<select></select>";', $document->querySelector('script')->textContent);
        $this->assertSame('quoted > <select>', $document->querySelector('div')->getAttribute('title'));
        $this->assertSame('<select>', $document->querySelector('textarea')->textContent);
        $this->assertCount(1, $document->querySelectorAll('select'));
    }

    public function testASelectInsideAShadowTreeIsRestored()
    {
        $view = (new HtmlBuilder())->build('<x-host><template shadowrootmode="open"><select id="s"><option><b>x</b></option></select></template></x-host>');

        $this->assertInstanceOf(DropdownWidget::class, $view->getById('s'));
        $this->assertSame('x', $view->getById('s')->getList()->getItems()[0]['label']);
    }
}
