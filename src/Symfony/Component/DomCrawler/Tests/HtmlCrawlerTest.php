<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DomCrawler\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\DomCrawler\HtmlCrawler;
use Symfony\Component\DomCrawler\HtmlImage;
use Symfony\Component\DomCrawler\HtmlLink;

class HtmlCrawlerTest extends TestCase
{
    private const HTML = <<<'HTML'
        <!doctype html>
        <html>
            <body>
                <div id="wrap" class="Box">
                    <p class="intro" data-tag="FOO">  hello   <b>world</b>  </p>
                    <p>second</p>
                </div>
                <form>
                    <input name="a" required>
                    <input name="b" readonly value="x">
                    <input name="c">
                </form>
                <a href="/x">link</a>
                <a>no href</a>
                <ul><li>one</li><li>two</li><li>three</li></ul>
            </body>
        </html>
        HTML;

    private const SELECTION_HTML = <<<'HTML'
        <!doctype html>
        <html>
            <body>
                <a href="foo">Foo</a>
                <a href="/foo">   Fabien's Foo   </a>
                <a href="/foo">Fabien"s Foo</a>
                <a href="/foo">' Fabien"s Foo</a>

                <a href="/bar"><img alt="Bar"/></a>
                <a href="/bar"><img alt="   Fabien's Bar   "/></a>
                <a href="/bar"><img alt="Fabien&quot;s Bar"/></a>
                <a href="/bar"><img alt="' Fabien&quot;s Bar"/></a>

                <a href="/example">Klausi|Claudiu</a>
                <a href="/deep"><span><img alt="Deep"></span></a>

                <form action="foo" id="FooFormId">
                    <input type="text" value="TextValue" name="TextName">
                    <input type="submit" value="FooValue" name="FooName" id="FooId">
                    <input type="button" value="BarValue" name="BarName" id="BarId">
                    <button value="ButtonValue" name="ButtonName" id="ButtonId"></button>
                    <button type="submit" name="Click 'Here'">Quoted</button>
                    <input type="SUBMIT" value="UpperValue" name="UpperName">
                    <input type="submit image" value="BothValue" alt="BothAlt">
                </form>

                <input type="image" alt="ImageAlt" form="FooFormId">
                <button form="FooFormId">ButtonText</button>
                <img src="/pic.png" alt="  A   picture  ">
            </body>
        </html>
        HTML;

    #[DataProvider('provideSelectLinkValues')]
    public function testSelectLinkMatchesCrawler(string $value, int $expectedCount)
    {
        $native = new HtmlCrawler(self::SELECTION_HTML, 'http://localhost/');
        $classic = new Crawler(self::SELECTION_HTML, 'http://localhost/');

        $this->assertCount($expectedCount, $native->selectLink($value));
        $this->assertSame($classic->selectLink($value)->extract(['href', '_text']), $native->selectLink($value)->extract(['href', '_text']));
    }

    public static function provideSelectLinkValues(): iterable
    {
        yield 'by text' => ['Foo', 4];
        yield 'text with a single quote' => ["Fabien's Foo", 1];
        yield 'text with a double quote' => ['Fabien"s Foo', 2];
        yield 'text with both quotes' => ['\' Fabien"s Foo', 1];
        yield 'by the alt of a child image' => ['Bar', 4];
        yield 'image alt with a single quote' => ["Fabien's Bar", 1];
        yield 'image alt with a double quote' => ['Fabien"s Bar', 2];
        yield 'text with a pipe' => ['Klausi|Claudiu', 1];
        yield 'a deeper image is not a direct child' => ['Deep', 0];
        yield 'partial words do not match' => ['Fabie', 0];
        yield 'unknown text' => ['Nothing', 0];
    }

    #[DataProvider('provideSelectImageValues')]
    public function testSelectImageMatchesCrawler(string $value, int $expectedCount)
    {
        $native = new HtmlCrawler(self::SELECTION_HTML, 'http://localhost/');
        $classic = new Crawler(self::SELECTION_HTML, 'http://localhost/');

        $this->assertCount($expectedCount, $native->selectImage($value));
        $this->assertSame($classic->selectImage($value)->extract(['alt', 'src']), $native->selectImage($value)->extract(['alt', 'src']));
    }

    public static function provideSelectImageValues(): iterable
    {
        yield 'by alt' => ['Bar', 4];
        yield 'whitespace in the alt is normalized' => ['A picture', 1];
        yield 'a substring of the alt matches' => ['pictur', 1];
        yield 'alt with a single quote' => ["Fabien's Bar", 1];
        yield 'alt with a double quote' => ['Fabien"s Bar', 2];
        yield 'unknown alt' => ['Nothing', 0];
    }

    #[DataProvider('provideSelectButtonValues')]
    public function testSelectButtonMatchesCrawler(string $value, int $expectedCount)
    {
        $native = new HtmlCrawler(self::SELECTION_HTML, 'http://localhost/');
        $classic = new Crawler(self::SELECTION_HTML, 'http://localhost/');

        $this->assertCount($expectedCount, $native->selectButton($value));
        $this->assertSame($classic->selectButton($value)->extract(['_name', 'name', 'id', 'value']), $native->selectButton($value)->extract(['_name', 'name', 'id', 'value']));
    }

    public static function provideSelectButtonValues(): iterable
    {
        yield 'submit input by value' => ['FooValue', 1];
        yield 'submit input by name' => ['FooName', 1];
        yield 'submit input by id' => ['FooId', 1];
        yield 'button input by value' => ['BarValue', 1];
        yield 'button input by name' => ['BarName', 1];
        yield 'button input by id' => ['BarId', 1];
        yield 'image input by alt' => ['ImageAlt', 1];
        yield 'button tag by value' => ['ButtonValue', 1];
        yield 'button tag by name' => ['ButtonName', 1];
        yield 'button tag by id' => ['ButtonId', 1];
        yield 'button tag by text' => ['ButtonText', 1];
        yield 'name holding a single quote' => ["Click 'Here'", 1];
        yield 'an uppercased type is a button too' => ['UpperValue', 1];
        yield 'a type naming both kinds matches on the value' => ['BothValue', 1];
        yield 'a type naming both kinds matches on the alt too' => ['BothAlt', 1];
        yield 'a text input is not a button' => ['TextValue', 0];
        yield 'unknown value' => ['Nothing', 0];
    }

    public function testSelectIncludesTheContextNodeItself()
    {
        $native = new HtmlCrawler(self::SELECTION_HTML, 'http://localhost/');
        $classic = new Crawler(self::SELECTION_HTML, 'http://localhost/');

        $this->assertCount(1, $native->filter('a')->eq(1)->selectLink("Fabien's Foo"));
        $this->assertSame(
            $classic->filter('a')->eq(1)->selectLink("Fabien's Foo")->extract(['href']),
            $native->filter('a')->eq(1)->selectLink("Fabien's Foo")->extract(['href']),
        );
    }

    public function testSelectIsScopedToTheCurrentNodes()
    {
        $crawler = new HtmlCrawler(self::SELECTION_HTML, 'http://localhost/');

        $this->assertCount(1, $crawler->filter('form')->selectButton('FooValue'));
        $this->assertCount(0, $crawler->filter('form')->selectButton('ImageAlt'));
        $this->assertCount(0, $crawler->filter('form')->selectButton('ButtonText'));
        $this->assertCount(0, $crawler->filter('ul')->selectLink('Foo'));
    }

    public function testLink()
    {
        $crawler = new HtmlCrawler(self::SELECTION_HTML, 'http://localhost/bar/');
        $link = $crawler->selectLink('Foo')->link();

        $this->assertInstanceOf(HtmlLink::class, $link);
        $this->assertSame('http://localhost/bar/foo', $link->getUri());
        $this->assertSame('GET', $link->getMethod());
        $this->assertSame('POST', $crawler->selectLink('Foo')->link('post')->getMethod());
    }

    public function testLinks()
    {
        $crawler = new HtmlCrawler(self::SELECTION_HTML, 'http://localhost/bar/');
        $links = $crawler->selectLink('Foo')->links();

        $this->assertContainsOnlyInstancesOf(HtmlLink::class, $links);
        $this->assertSame(
            ['http://localhost/bar/foo', 'http://localhost/foo', 'http://localhost/foo', 'http://localhost/foo'],
            array_map(static fn (HtmlLink $link) => $link->getUri(), $links),
        );
    }

    public function testLinkOnAnEmptyNodeListThrows()
    {
        $crawler = new HtmlCrawler(self::SELECTION_HTML, 'http://localhost/');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('The current node list is empty.');

        $crawler->filter('nothing')->link();
    }

    public function testLinkOnANonLinkTagThrows()
    {
        $crawler = new HtmlCrawler(self::SELECTION_HTML, 'http://localhost/');

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Unable to navigate from a "form" tag.');

        $crawler->filter('form')->link();
    }

    public function testImage()
    {
        $crawler = new HtmlCrawler(self::SELECTION_HTML, 'http://localhost/bar/');
        $image = $crawler->selectImage('A picture')->image();

        $this->assertInstanceOf(HtmlImage::class, $image);
        $this->assertSame('http://localhost/pic.png', $image->getUri());
    }

    public function testImages()
    {
        $crawler = new HtmlCrawler(self::SELECTION_HTML, 'http://localhost/bar/');
        $images = $crawler->selectImage('Bar')->images();

        $this->assertContainsOnlyInstancesOf(HtmlImage::class, $images);
        $this->assertCount(4, $images);
    }

    public function testImageOnAnEmptyNodeListThrows()
    {
        $crawler = new HtmlCrawler(self::SELECTION_HTML, 'http://localhost/');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('The current node list is empty.');

        $crawler->filter('nothing')->image();
    }

    public function testFilterSupportsSelectorsCssSelectorCannotTranslate()
    {
        $crawler = new HtmlCrawler(self::HTML);

        $this->assertSame(1, $crawler->filter('input:required')->count());
        $this->assertSame(1, $crawler->filter('input:read-only')->count());
        $this->assertSame(1, $crawler->filter('a:any-link')->count());
        $this->assertSame(1, $crawler->filter('[data-tag="foo" i]')->count());
        $this->assertSame(1, $crawler->filter('li:nth-child(2 of .none), li:nth-child(2)')->count());
    }

    public function testFilterAppliesHtmlSemantics()
    {
        $crawler = new HtmlCrawler(self::HTML);

        // tag names are matched case-insensitively in HTML
        $this->assertSame(2, $crawler->filter('P')->count());
        $this->assertSame(2, $crawler->filter('p')->count());
    }

    public function testFilterIsScopedToTheCurrentNodes()
    {
        $crawler = new HtmlCrawler(self::HTML);

        $this->assertSame(2, $crawler->filter('#wrap')->filter('p')->count());
        $this->assertSame(0, $crawler->filter('ul')->filter('p')->count());
    }

    public function testFilterXPathAcceptsUnprefixedNames()
    {
        $crawler = new HtmlCrawler(self::HTML);

        $this->assertSame(2, $crawler->filterXPath('//p')->count());
        $this->assertSame(1, $crawler->filterXPath('//a[@href]')->count());
        $this->assertSame(3, $crawler->filterXPath('//li')->count());
    }

    public function testFilterXPathIsRelativeToTheCurrentNodes()
    {
        $crawler = new HtmlCrawler(self::HTML);

        $this->assertSame(2, $crawler->filter('#wrap')->filterXPath('.//p')->count());
        $this->assertSame(0, $crawler->filter('ul')->filterXPath('.//p')->count());
    }

    public function testEvaluateReturnsScalars()
    {
        $crawler = new HtmlCrawler(self::HTML);

        $this->assertSame([3.0], $crawler->evaluate('count(//li)'));
    }

    public function testMatchesAndClosest()
    {
        $crawler = new HtmlCrawler(self::HTML);
        $intro = $crawler->filter('p.intro');

        $this->assertTrue($intro->matches('.intro'));
        $this->assertTrue($intro->matches('div > p'));
        $this->assertFalse($intro->matches('ul'));

        $this->assertSame('wrap', $intro->closest('#wrap')->attr('id'));
        $this->assertNull($intro->closest('table'));
    }

    public function testChildrenWithAndWithoutSelector()
    {
        $crawler = new HtmlCrawler(self::HTML);

        $this->assertSame(2, $crawler->filter('#wrap')->children()->count());
        $this->assertSame(1, $crawler->filter('#wrap')->children('.intro')->count());
    }

    public function testTraversal()
    {
        $crawler = new HtmlCrawler(self::HTML);
        $second = $crawler->filter('li')->eq(1);

        $this->assertSame('two', $second->text());
        $this->assertSame(1, $second->nextAll()->count());
        $this->assertSame(1, $second->previousAll()->count());
        $this->assertSame(2, $second->siblings()->count());
        $this->assertContains('ul', $second->ancestors()->each(static fn (HtmlCrawler $n) => $n->nodeName()));
    }

    public function testContentAccessorsMatchCrawler()
    {
        $native = new HtmlCrawler(self::HTML);
        $classic = new Crawler(self::HTML);

        foreach (['#wrap', 'p.intro', 'ul'] as $selector) {
            $n = $native->filter($selector);
            $c = $classic->filter($selector);

            $this->assertSame($c->nodeName(), $n->nodeName(), $selector);
            $this->assertSame($c->text(), $n->text(), $selector);
            $this->assertSame($c->innerText(), $n->innerText(), $selector);
            $this->assertSame($c->html(), $n->html(), $selector);
            $this->assertSame($c->outerHtml(), $n->outerHtml(), $selector);
        }
    }

    public function testExtractMatchesCrawler()
    {
        $native = new HtmlCrawler(self::HTML);
        $classic = new Crawler(self::HTML);

        $this->assertSame(
            $classic->filter('li')->extract(['_text', '_name']),
            $native->filter('li')->extract(['_text', '_name']),
        );
    }

    public function testExtractReportsAMissingAttributeAsAnEmptyString()
    {
        $native = new HtmlCrawler(self::HTML);
        $classic = new Crawler(self::HTML);

        $this->assertSame(['', 'x', ''], $native->filter('input')->extract(['value']));
        $this->assertSame($classic->filter('input')->extract(['value']), $native->filter('input')->extract(['value']));
    }

    public function testAttr()
    {
        $crawler = new HtmlCrawler(self::HTML);

        $this->assertSame('/x', $crawler->filter('a')->attr('href'));
        $this->assertNull($crawler->filter('ul')->attr('href'));
        $this->assertSame('fallback', $crawler->filter('ul')->attr('href', 'fallback'));
        $this->assertSame('fallback', $crawler->filter('nothing')->attr('href', 'fallback'));
    }

    public function testEmptyNodeListThrows()
    {
        $crawler = new HtmlCrawler(self::HTML);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('The current node list is empty.');

        $crawler->filter('nothing')->text();
    }

    public function testNodesFromDistinctDocumentsAreRejected()
    {
        $crawler = new HtmlCrawler(self::HTML);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Attaching DOM nodes from multiple documents in the same crawler is forbidden.');

        $crawler->addNode((new HtmlCrawler(self::HTML))->filter('ul')->getNode(0));
    }

    public function testSliceReduceFirstLastAndCount()
    {
        $crawler = new HtmlCrawler(self::HTML);
        $items = $crawler->filter('li');

        $this->assertSame(3, $items->count());
        $this->assertCount(3, iterator_to_array($items));
        $this->assertSame('one', $items->first()->text());
        $this->assertSame('three', $items->last()->text());
        $this->assertSame(2, $items->slice(1)->count());
        $this->assertSame('two', $items->reduce(static fn (HtmlCrawler $n) => 'two' === $n->text())->text());
    }

    public function testGetNodeReturnsNativeNodes()
    {
        $crawler = new HtmlCrawler(self::HTML);

        $this->assertInstanceOf(\Dom\Element::class, $crawler->filter('ul')->getNode(0));
        $this->assertNull($crawler->filter('ul')->getNode(9));
    }

    public function testClear()
    {
        $crawler = new HtmlCrawler(self::HTML);
        $crawler->clear();

        $this->assertSame(0, $crawler->count());
    }
}
