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

use PHPUnit\Framework\TestCase;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\DomCrawler\HtmlCrawler;

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
