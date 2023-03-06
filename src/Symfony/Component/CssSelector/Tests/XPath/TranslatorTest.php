<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\CssSelector\Tests\XPath;

use PHPUnit\Framework\TestCase;
use Symfony\Component\CssSelector\Exception\ExpressionErrorException;
use Symfony\Component\CssSelector\Node\ElementNode;
use Symfony\Component\CssSelector\Node\FunctionNode;
use Symfony\Component\CssSelector\Parser\Parser;
use Symfony\Component\CssSelector\XPath\Extension\HtmlExtension;
use Symfony\Component\CssSelector\XPath\Translator;
use Symfony\Component\CssSelector\XPath\XPathExpr;

class TranslatorTest extends TestCase
{
    /** @dataProvider getXpathLiteralTestData */
    public function testXpathLiteral($value, $literal)
    {
        $this->assertEquals($literal, Translator::getXpathLiteral($value));
    }

    /** @dataProvider getCssToXPathTestData */
    public function testCssToXPath($css, $xpath)
    {
        $translator = new Translator();
        $translator->registerExtension(new HtmlExtension($translator));
        $this->assertEquals($xpath, $translator->cssToXPath($css, ''));
    }

    public function testCssToXPathPseudoElement()
    {
        $this->expectException(ExpressionErrorException::class);
        $translator = new Translator();
        $translator->registerExtension(new HtmlExtension($translator));
        $translator->cssToXPath('e::first-line');
    }

    public function testGetExtensionNotExistsExtension()
    {
        $this->expectException(ExpressionErrorException::class);
        $translator = new Translator();
        $translator->registerExtension(new HtmlExtension($translator));
        $translator->getExtension('fake');
    }

    public function testAddCombinationNotExistsExtension()
    {
        $this->expectException(ExpressionErrorException::class);
        $translator = new Translator();
        $translator->registerExtension(new HtmlExtension($translator));
        $parser = new Parser();
        $xpath = $parser->parse('*')[0];
        $combinedXpath = $parser->parse('*')[0];
        $translator->addCombination('fake', $xpath, $combinedXpath);
    }

    public function testAddFunctionNotExistsFunction()
    {
        $this->expectException(ExpressionErrorException::class);
        $translator = new Translator();
        $translator->registerExtension(new HtmlExtension($translator));
        $xpath = new XPathExpr();
        $function = new FunctionNode(new ElementNode(), 'fake');
        $translator->addFunction($xpath, $function);
    }

    public function testAddPseudoClassNotExistsClass()
    {
        $this->expectException(ExpressionErrorException::class);
        $translator = new Translator();
        $translator->registerExtension(new HtmlExtension($translator));
        $xpath = new XPathExpr();
        $translator->addPseudoClass($xpath, 'fake');
    }

    public function testAddAttributeMatchingClassNotExistsClass()
    {
        $this->expectException(ExpressionErrorException::class);
        $translator = new Translator();
        $translator->registerExtension(new HtmlExtension($translator));
        $xpath = new XPathExpr();
        $translator->addAttributeMatching($xpath, '', '', '');
    }

    /** @dataProvider getXmlLangTestData */
    public function testXmlLang($css, array $elementsId)
    {
        $translator = new Translator();
        $document = new \SimpleXMLElement(file_get_contents(__DIR__.'/Fixtures/lang.xml'));
        $elements = $document->xpath($translator->cssToXPath($css));
        $this->assertCount(\count($elementsId), $elements);
        foreach ($elements as $element) {
            $this->assertContains((string) $element->attributes()->id, $elementsId);
        }
    }

    /** @dataProvider getHtmlIdsTestData */
    public function testHtmlIds($css, array $elementsId)
    {
        $translator = new Translator();
        $translator->registerExtension(new HtmlExtension($translator));
        $document = new \DOMDocument();
        $document->strictErrorChecking = false;
        $internalErrors = libxml_use_internal_errors(true);
        $document->loadHTMLFile(__DIR__.'/Fixtures/ids.html');
        $document = simplexml_import_dom($document);
        $elements = $document->xpath($translator->cssToXPath($css));
        $this->assertCount(\count($elementsId), $elementsId);
        foreach ($elements as $element) {
            if (null !== $element->attributes()->id) {
                $this->assertContains((string) $element->attributes()->id, $elementsId);
            }
        }
        libxml_clear_errors();
        libxml_use_internal_errors($internalErrors);
    }

    /** @dataProvider getHtmlShakespearTestData */
    public function testHtmlShakespear($css, $count)
    {
        $translator = new Translator();
        $translator->registerExtension(new HtmlExtension($translator));
        $document = new \DOMDocument();
        $document->strictErrorChecking = false;
        $document->loadHTMLFile(__DIR__.'/Fixtures/shakespear.html');
        $document = simplexml_import_dom($document);
        $bodies = $document->xpath('//body');
        $elements = $bodies[0]->xpath($translator->cssToXPath($css));
        $this->assertCount($count, $elements);
    }

    public function testOnlyOfTypeFindsSingleChildrenOfGivenType()
    {
        $translator = new Translator();
        $translator->registerExtension(new HtmlExtension($translator));
        $document = new \DOMDocument();
        $document->loadHTML(<<<'HTML'
<html>
  <body>
    <p>
      <span>A</span>
    </p>
    <p>
      <span>B</span>
      <span>C</span>
    </p>
  </body>
</html>
HTML
        );

        $xpath = new \DOMXPath($document);
        $nodeList = $xpath->query($translator->cssToXPath('span:only-of-type'));

        $this->assertSame(1, $nodeList->length);
        $this->assertSame('A', $nodeList->item(0)->textContent);
    }

    public static function getXpathLiteralTestData()
    {
        return [
            ['foo', "'foo'"],
            ["foo's bar", '"foo\'s bar"'],
            ["foo's \"middle\" bar", 'concat(\'foo\', "\'", \'s "middle" bar\')'],
            ["foo's 'middle' \"bar\"", 'concat(\'foo\', "\'", \'s \', "\'", \'middle\', "\'", \' "bar"\')'],
        ];
    }

    public static function getCssToXPathTestData()
    {
        return [
            ['*', '*'],
            ['e', 'e'],
            ['*|e', 'e'],
            ['e|f', 'e:f'],
            ['e[foo]', 'e[@foo]'],
            ['e[foo|bar]', 'e[@foo:bar]'],
            ['e[foo="bar"]', "e[@foo = 'bar']"],
            ['e[foo~="bar"]', "e[@foo and contains(concat(' ', normalize-space(@foo), ' '), ' bar ')]"],
            ['e[foo^="bar"]', "e[@foo and starts-with(@foo, 'bar')]"],
            ['e[foo$="bar"]', "e[@foo and substring(@foo, string-length(@foo)-2) = 'bar']"],
            ['e[foo*="bar"]', "e[@foo and contains(@foo, 'bar')]"],
            ['e[foo!="bar"]', "e[not(@foo) or @foo != 'bar']"],
            ['e[foo!="bar"][foo!="baz"]', "e[(not(@foo) or @foo != 'bar') and (not(@foo) or @foo != 'baz')]"],
            ['e[hreflang|="en"]', "e[@hreflang and (@hreflang = 'en' or starts-with(@hreflang, 'en-'))]"],
            ['e:nth-child(1)', "*/*[(name() = 'e') and (position() = 1)]"],
            ['e:nth-last-child(1)', "*/*[(name() = 'e') and (position() = last() - 0)]"],
            ['e:nth-last-child(2n+2)', "*/*[(name() = 'e') and (last() - position() - 1 >= 0 and (last() - position() - 1) mod 2 = 0)]"],
            ['e:nth-of-type(1)', '*/e[position() = 1]'],
            ['e:nth-last-of-type(1)', '*/e[position() = last() - 0]'],
            ['div e:nth-last-of-type(1) .aclass', "div/descendant-or-self::*/e[position() = last() - 0]/descendant-or-self::*/*[@class and contains(concat(' ', normalize-space(@class), ' '), ' aclass ')]"],
            ['e:first-child', "*/*[(name() = 'e') and (position() = 1)]"],
            ['e:last-child', "*/*[(name() = 'e') and (position() = last())]"],
            ['e:first-of-type', '*/e[position() = 1]'],
            ['e:last-of-type', '*/e[position() = last()]'],
            ['e:only-child', "*/*[(name() = 'e') and (last() = 1)]"],
            ['e:only-of-type', 'e[count(preceding-sibling::e)=0 and count(following-sibling::e)=0]'],
            ['e:empty', 'e[not(*) and not(string-length())]'],
            ['e:EmPTY', 'e[not(*) and not(string-length())]'],
            ['e:root', 'e[not(parent::*)]'],
            ['e:hover', 'e[0]'],
            ['e:contains("foo")', "e[contains(string(.), 'foo')]"],
            ['e:ConTains(foo)', "e[contains(string(.), 'foo')]"],
            ['e.warning', "e[@class and contains(concat(' ', normalize-space(@class), ' '), ' warning ')]"],
            ['e#myid', "e[@id = 'myid']"],
            ['e:not(:nth-child(odd))', 'e[not(position() - 1 >= 0 and (position() - 1) mod 2 = 0)]'],
            ['e:nOT(*)', 'e[0]'],
            ['e f', 'e/descendant-or-self::*/f'],
            ['e > f', 'e/f'],
            ['e + f', "e/following-sibling::*[(name() = 'f') and (position() = 1)]"],
            ['e ~ f', 'e/following-sibling::f'],
            ['div#container p', "div[@id = 'container']/descendant-or-self::*/p"],
            [':scope > div[dataimg="<testmessage>"]', "*[1]/div[@dataimg = '<testmessage>']"],
            [':scope', '*[1]'],
        ];
    }

    public static function getXmlLangTestData()
    {
        return [
            [':lang("EN")', ['first', 'second', 'third', 'fourth']],
            [':lang("en-us")', ['second', 'fourth']],
            [':lang(en-nz)', ['third']],
            [':lang(fr)', ['fifth']],
            [':lang(ru)', ['sixth']],
            [":lang('ZH')", ['eighth']],
            [':lang(de) :lang(zh)', ['eighth']],
            [':lang(en), :lang(zh)', ['first', 'second', 'third', 'fourth', 'eighth']],
            [':lang(es)', []],
        ];
    }

    public static function getHtmlIdsTestData()
    {
        return [
            ['div', ['outer-div', 'li-div', 'foobar-div']],
            ['DIV', ['outer-div', 'li-div', 'foobar-div']],  // case-insensitive in HTML
            ['div div', ['li-div']],
            ['div, div div', ['outer-div', 'li-div', 'foobar-div']],
            ['a[name]', ['name-anchor']],
            ['a[NAme]', ['name-anchor']], // case-insensitive in HTML:
            ['a[rel]', ['tag-anchor', 'nofollow-anchor']],
            ['a[rel="tag"]', ['tag-anchor']],
            ['a[href*="localhost"]', ['tag-anchor']],
            ['a[href*=""]', []],
            ['a[href^="http"]', ['tag-anchor', 'nofollow-anchor']],
            ['a[href^="http:"]', ['tag-anchor']],
            ['a[href^=""]', []],
            ['a[href$="org"]', ['nofollow-anchor']],
            ['a[href$=""]', []],
            ['div[foobar~="bc"]', ['foobar-div']],
            ['div[foobar~="cde"]', ['foobar-div']],
            ['[foobar~="ab bc"]', ['foobar-div']],
            ['[foobar~=""]', []],
            ['[foobar~=" \t"]', []],
            ['div[foobar~="cd"]', []],
            ['*[lang|="En"]', ['second-li']],
            ['[lang|="En-us"]', ['second-li']],
            // Attribute values are case sensitive
            ['*[lang|="en"]', []],
            ['[lang|="en-US"]', []],
            ['*[lang|="e"]', []],
            // ... :lang() is not.
            [':lang("EN")', ['second-li', 'li-div']],
            ['*:lang(en-US)', ['second-li', 'li-div']],
            [':lang("e")', []],
            ['li:nth-child(3)', ['third-li']],
            ['li:nth-child(10)', []],
            ['li:nth-child(2n)', ['second-li', 'fourth-li', 'sixth-li']],
            ['li:nth-child(even)', ['second-li', 'fourth-li', 'sixth-li']],
            ['li:nth-child(2n+0)', ['second-li', 'fourth-li', 'sixth-li']],
            ['li:nth-child(+2n+1)', ['first-li', 'third-li', 'fifth-li', 'seventh-li']],
            ['li:nth-child(odd)', ['first-li', 'third-li', 'fifth-li', 'seventh-li']],
            ['li:nth-child(2n+4)', ['fourth-li', 'sixth-li']],
            ['li:nth-child(3n+1)', ['first-li', 'fourth-li', 'seventh-li']],
            ['li:nth-child(n)', ['first-li', 'second-li', 'third-li', 'fourth-li', 'fifth-li', 'sixth-li', 'seventh-li']],
            ['li:nth-child(n-1)', ['first-li', 'second-li', 'third-li', 'fourth-li', 'fifth-li', 'sixth-li', 'seventh-li']],
            ['li:nth-child(n+1)', ['first-li', 'second-li', 'third-li', 'fourth-li', 'fifth-li', 'sixth-li', 'seventh-li']],
            ['li:nth-child(n+3)', ['third-li', 'fourth-li', 'fifth-li', 'sixth-li', 'seventh-li']],
            ['li:nth-child(-n)', []],
            ['li:nth-child(-n-1)', []],
            ['li:nth-child(-n+1)', ['first-li']],
            ['li:nth-child(-n+3)', ['first-li', 'second-li', 'third-li']],
            ['li:nth-last-child(0)', []],
            ['li:nth-last-child(2n)', ['second-li', 'fourth-li', 'sixth-li']],
            ['li:nth-last-child(even)', ['second-li', 'fourth-li', 'sixth-li']],
            ['li:nth-last-child(2n+2)', ['second-li', 'fourth-li', 'sixth-li']],
            ['li:nth-last-child(n)', ['first-li', 'second-li', 'third-li', 'fourth-li', 'fifth-li', 'sixth-li', 'seventh-li']],
            ['li:nth-last-child(n-1)', ['first-li', 'second-li', 'third-li', 'fourth-li', 'fifth-li', 'sixth-li', 'seventh-li']],
            ['li:nth-last-child(n-3)', ['first-li', 'second-li', 'third-li', 'fourth-li', 'fifth-li', 'sixth-li', 'seventh-li']],
            ['li:nth-last-child(n+1)', ['first-li', 'second-li', 'third-li', 'fourth-li', 'fifth-li', 'sixth-li', 'seventh-li']],
            ['li:nth-last-child(n+3)', ['first-li', 'second-li', 'third-li', 'fourth-li', 'fifth-li']],
            ['li:nth-last-child(-n)', []],
            ['li:nth-last-child(-n-1)', []],
            ['li:nth-last-child(-n+1)', ['seventh-li']],
            ['li:nth-last-child(-n+3)', ['fifth-li', 'sixth-li', 'seventh-li']],
            ['ol:first-of-type', ['first-ol']],
            ['ol:nth-child(1)', ['first-ol']],
            ['ol:nth-of-type(2)', ['second-ol']],
            ['ol:nth-last-of-type(1)', ['second-ol']],
            ['span:only-child', ['foobar-span']],
            ['li div:only-child', ['li-div']],
            ['div *:only-child', ['li-div', 'foobar-span']],
            ['p:only-of-type', ['paragraph']],
            [':only-of-type', ['html', 'li-div', 'foobar-span', 'paragraph']],
            ['div#foobar-div :only-of-type', ['foobar-span']],
            ['a:empty', ['name-anchor']],
            ['a:EMpty', ['name-anchor']],
            ['li:empty', ['third-li', 'fourth-li', 'fifth-li', 'sixth-li']],
            [':root', ['html']],
            ['html:root', ['html']],
            ['li:root', []],
            ['* :root', []],
            ['*:contains("link")', ['html', 'outer-div', 'tag-anchor', 'nofollow-anchor']],
            [':CONtains("link")', ['html', 'outer-div', 'tag-anchor', 'nofollow-anchor']],
            ['*:contains("LInk")', []],  // case sensitive
            ['*:contains("e")', ['html', 'nil', 'outer-div', 'first-ol', 'first-li', 'paragraph', 'p-em']],
            ['*:contains("E")', []],  // case-sensitive
            ['.a', ['first-ol']],
            ['.b', ['first-ol']],
            ['*.a', ['first-ol']],
            ['ol.a', ['first-ol']],
            ['.c', ['first-ol', 'third-li', 'fourth-li']],
            ['*.c', ['first-ol', 'third-li', 'fourth-li']],
            ['ol *.c', ['third-li', 'fourth-li']],
            ['ol li.c', ['third-li', 'fourth-li']],
            ['li ~ li.c', ['third-li', 'fourth-li']],
            ['ol > li.c', ['third-li', 'fourth-li']],
            ['#first-li', ['first-li']],
            ['li#first-li', ['first-li']],
            ['*#first-li', ['first-li']],
            ['li div', ['li-div']],
            ['li > div', ['li-div']],
            ['div div', ['li-div']],
            ['div > div', []],
            ['div>.c', ['first-ol']],
            ['div > .c', ['first-ol']],
            ['div + div', ['foobar-div']],
            ['a ~ a', ['tag-anchor', 'nofollow-anchor']],
            ['a[rel="tag"] ~ a', ['nofollow-anchor']],
            ['ol#first-ol li:last-child', ['seventh-li']],
            ['ol#first-ol *:last-child', ['li-div', 'seventh-li']],
            ['#outer-div:first-child', ['outer-div']],
            ['#outer-div :first-child', ['name-anchor', 'first-li', 'li-div', 'p-b', 'checkbox-fieldset-disabled', 'area-href']],
            ['a[href]', ['tag-anchor', 'nofollow-anchor']],
            [':not(*)', []],
            ['a:not([href])', ['name-anchor']],
            ['ol :Not(li[class])', ['first-li', 'second-li', 'li-div', 'fifth-li', 'sixth-li', 'seventh-li']],
            // HTML-specific
            [':link', ['link-href', 'tag-anchor', 'nofollow-anchor', 'area-href']],
            [':visited', []],
            [':enabled', ['link-href', 'tag-anchor', 'nofollow-anchor', 'checkbox-unchecked', 'text-checked', 'checkbox-checked', 'area-href']],
            [':disabled', ['checkbox-disabled', 'checkbox-disabled-checked', 'fieldset', 'checkbox-fieldset-disabled']],
            [':checked', ['checkbox-checked', 'checkbox-disabled-checked']],
        ];
    }

    public static function getHtmlShakespearTestData()
    {
        return [
            ['*', 246],
            ['div:contains(CELIA)', 26],
            ['div:only-child', 22], // ?
            ['div:nth-child(even)', 106],
            ['div:nth-child(2n)', 106],
            ['div:nth-child(odd)', 137],
            ['div:nth-child(2n+1)', 137],
            ['div:nth-child(n)', 243],
            ['div:last-child', 53],
            ['div:first-child', 51],
            ['div > div', 242],
            ['div + div', 190],
            ['div ~ div', 190],
            ['body', 1],
            ['body div', 243],
            ['div', 243],
            ['div div', 242],
            ['div div div', 241],
            ['div, div, div', 243],
            ['div, a, span', 243],
            ['.dialog', 51],
            ['div.dialog', 51],
            ['div .dialog', 51],
            ['div.character, div.dialog', 99],
            ['div.direction.dialog', 0],
            ['div.dialog.direction', 0],
            ['div.dialog.scene', 1],
            ['div.scene.scene', 1],
            ['div.scene .scene', 0],
            ['div.direction .dialog ', 0],
            ['div .dialog .direction', 4],
            ['div.dialog .dialog .direction', 4],
            ['#speech5', 1],
            ['div#speech5', 1],
            ['div #speech5', 1],
            ['div.scene div.dialog', 49],
            ['div#scene1 div.dialog div', 142],
            ['#scene1 #speech1', 1],
            ['div[class]', 103],
            ['div[class=dialog]', 50],
            ['div[class^=dia]', 51],
            ['div[class$=log]', 50],
            ['div[class*=sce]', 1],
            ['div[class|=dialog]', 50], // ? Seems right
            ['div[class!=madeup]', 243], // ? Seems right
            ['div[class~=dialog]', 51], // ? Seems right
            [':scope > div', 1],
            [':scope > div > div[class=dialog]', 1],
            [':scope > div div', 242],
        ];
    }
}
