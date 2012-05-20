<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Tests\Component\DomCrawler;

use Symfony\Component\DomCrawler\Crawler;

class CrawlerTest extends \PHPUnit_Framework_TestCase
{
    public function testConstructor()
    {
        $crawler = new Crawler();
        $this->assertEquals(0, count($crawler), '__construct() returns an empty crawler');

        $crawler = new Crawler(new \DOMNode());
        $this->assertEquals(1, count($crawler), '__construct() takes a node as a first argument');
    }

    /**
     * @covers Symfony\Component\DomCrawler\Crawler::add
     */
    public function testAdd()
    {
        $crawler = new Crawler();
        $crawler->add($this->createDomDocument());
        $this->assertEquals('foo', $crawler->filter('div')->attr('class'), '->add() adds nodes from a \DOMDocument');

        $crawler = new Crawler();
        $crawler->add($this->createNodeList());
        $this->assertEquals('foo', $crawler->filter('div')->attr('class'), '->add() adds nodes from a \DOMNodeList');

        foreach ($this->createNodeList() as $node) {
            $list[] = $node;
        }
        $crawler = new Crawler();
        $crawler->add($list);
        $this->assertEquals('foo', $crawler->filter('div')->attr('class'), '->add() adds nodes from an array of nodes');

        $crawler = new Crawler();
        $crawler->add($this->createNodeList()->item(0));
        $this->assertEquals('foo', $crawler->filter('div')->attr('class'), '->add() adds nodes from an \DOMNode');

        $crawler = new Crawler();
        $crawler->add('<html><body>Foo</body></html>');
        $this->assertEquals('Foo', $crawler->filter('body')->text(), '->add() adds nodes from a string');
    }

    /**
     * @covers Symfony\Component\DomCrawler\Crawler::addHtmlContent
     */
    public function testAddHtmlContent()
    {
        $crawler = new Crawler();
        $crawler->addHtmlContent('<html><div class="foo"></html>', 'UTF-8');

        $this->assertEquals('foo', $crawler->filter('div')->attr('class'), '->addHtmlContent() adds nodes from an HTML string');

        $crawler->addHtmlContent('<html><head><base href="http://symfony.com"></head><a href="/contact"></a></html>', 'UTF-8');

        $this->assertEquals('http://symfony.com', $crawler->filter('base')->attr('href'), '->addHtmlContent() adds nodes from an HTML string');
        $this->assertEquals('http://symfony.com/contact', $crawler->filter('a')->link()->getUri(), '->addHtmlContent() adds nodes from an HTML string');
    }

    /**
     * @covers Symfony\Component\DomCrawler\Crawler::addXmlContent
     */
    public function testAddXmlContent()
    {
        $crawler = new Crawler();
        $crawler->addXmlContent('<html><div class="foo"></div></html>', 'UTF-8');

        $this->assertEquals('foo', $crawler->filter('div')->attr('class'), '->addXmlContent() adds nodes from an XML string');
    }

    /**
     * @covers Symfony\Component\DomCrawler\Crawler::addContent
     */
    public function testAddContent()
    {
        $crawler = new Crawler();
        $crawler->addContent('<html><div class="foo"></html>', 'text/html; charset=UTF-8');
        $this->assertEquals('foo', $crawler->filter('div')->attr('class'), '->addContent() adds nodes from an HTML string');

        $crawler = new Crawler();
        $crawler->addContent('<html><div class="foo"></html>', 'text/html; charset=UTF-8; dir=RTL');
        $this->assertEquals('foo', $crawler->filter('div')->attr('class'), '->addContent() adds nodes from an HTML string with extended content type');

        $crawler = new Crawler();
        $crawler->addContent('<html><div class="foo"></html>');
        $this->assertEquals('foo', $crawler->filter('div')->attr('class'), '->addContent() uses text/html as the default type');

        $crawler = new Crawler();
        $crawler->addContent('<html><div class="foo"></div></html>', 'text/xml; charset=UTF-8');
        $this->assertEquals('foo', $crawler->filter('div')->attr('class'), '->addContent() adds nodes from an XML string');

        $crawler = new Crawler();
        $crawler->addContent('<html><div class="foo"></div></html>', 'text/xml');
        $this->assertEquals('foo', $crawler->filter('div')->attr('class'), '->addContent() adds nodes from an XML string');

        $crawler = new Crawler();
        $crawler->addContent('foo bar', 'text/plain');
        $this->assertEquals(0, count($crawler), '->addContent() does nothing if the type is not (x|ht)ml');
    }

    /**
     * @covers Symfony\Component\DomCrawler\Crawler::addDocument
     */
    public function testAddDocument()
    {
        $crawler = new Crawler();
        $crawler->addDocument($this->createDomDocument());

        $this->assertEquals('foo', $crawler->filter('div')->attr('class'), '->addDocument() adds nodes from a \DOMDocument');
    }

    /**
     * @covers Symfony\Component\DomCrawler\Crawler::addNodeList
     */
    public function testAddNodeList()
    {
        $crawler = new Crawler();
        $crawler->addNodeList($this->createNodeList());

        $this->assertEquals('foo', $crawler->filter('div')->attr('class'), '->addNodeList() adds nodes from a \DOMNodeList');
    }

    /**
     * @covers Symfony\Component\DomCrawler\Crawler::addNodes
     */
    public function testAddNodes()
    {
        foreach ($this->createNodeList() as $node) {
            $list[] = $node;
        }

        $crawler = new Crawler();
        $crawler->addNodes($list);

        $this->assertEquals('foo', $crawler->filter('div')->attr('class'), '->addNodes() adds nodes from an array of nodes');
    }

    /**
     * @covers Symfony\Component\DomCrawler\Crawler::addNode
     */
    public function testAddNode()
    {
        $crawler = new Crawler();
        $crawler->addNode($this->createNodeList()->item(0));

        $this->assertEquals('foo', $crawler->filter('div')->attr('class'), '->addNode() adds nodes from an \DOMNode');
    }

    public function testClear()
    {
        $crawler = new Crawler(new \DOMNode());
        $crawler->clear();
        $this->assertEquals(0, count($crawler), '->clear() removes all the nodes from the crawler');
    }

    public function testEq()
    {
        $crawler = $this->createTestCrawler()->filter('li');
        $this->assertNotSame($crawler, $crawler->eq(0), '->eq() returns a new instance of a crawler');
        $this->assertInstanceOf('Symfony\\Component\\DomCrawler\\Crawler', $crawler, '->eq() returns a new instance of a crawler');

        $this->assertEquals('Two', $crawler->eq(1)->text(), '->eq() returns the nth node of the list');
        $this->assertEquals(0, count($crawler->eq(100)), '->eq() returns an empty crawler if the nth node does not exist');
    }

    public function testEach()
    {
        $data = $this->createTestCrawler()->filter('ul.first li')->each(function ($node, $i) {
            return $i.'-'.$node->nodeValue;
        });

        $this->assertEquals(array('0-One', '1-Two', '2-Three'), $data, '->each() executes an anonymous function on each node of the list');
    }

    public function testReduce()
    {
        $crawler = $this->createTestCrawler()->filter('ul.first li');
        $nodes = $crawler->reduce(function ($node, $i) {
            return $i == 1 ? false : true;
        });
        $this->assertNotSame($nodes, $crawler, '->reduce() returns a new instance of a crawler');
        $this->assertInstanceOf('Symfony\\Component\\DomCrawler\\Crawler', $nodes, '->reduce() returns a new instance of a crawler');

        $this->assertEquals(2, count($nodes), '->reduce() filters the nodes in the list');
    }

    public function testAttr()
    {
        $this->assertEquals('first', $this->createTestCrawler()->filter('li')->attr('class'), '->attr() returns the attribute of the first element of the node list');

        try {
            $this->createTestCrawler()->filter('ol')->attr('class');
            $this->fail('->attr() throws an \InvalidArgumentException if the node list is empty');
        } catch (\InvalidArgumentException $e) {
            $this->assertTrue(true, '->attr() throws an \InvalidArgumentException if the node list is empty');
        }
    }

    public function testText()
    {
        $this->assertEquals('One', $this->createTestCrawler()->filter('li')->text(), '->text() returns the node value of the first element of the node list');

        try {
            $this->createTestCrawler()->filter('ol')->text();
            $this->fail('->text() throws an \InvalidArgumentException if the node list is empty');
        } catch (\InvalidArgumentException $e) {
            $this->assertTrue(true, '->text() throws an \InvalidArgumentException if the node list is empty');
        }
    }

    public function testExtract()
    {
        $crawler = $this->createTestCrawler()->filter('ul.first li');

        $this->assertEquals(array('One', 'Two', 'Three'), $crawler->extract('_text'), '->extract() returns an array of extracted data from the node list');
        $this->assertEquals(array(array('One', 'first'), array('Two', ''), array('Three', '')), $crawler->extract(array('_text', 'class')), '->extract() returns an array of extracted data from the node list');

        $this->assertEquals(array(), $this->createTestCrawler()->filter('lo')->extract('_text'), '->extract() returns an empty array if the node list is empty');
    }

    /**
     * @covers Symfony\Component\DomCrawler\Crawler::filterXPath
     */
    public function testFilterXPath()
    {
        $crawler = $this->createTestCrawler();
        $this->assertNotSame($crawler, $crawler->filterXPath('//li'), '->filterXPath() returns a new instance of a crawler');
        $this->assertInstanceOf('Symfony\\Component\\DomCrawler\\Crawler', $crawler, '->filterXPath() returns a new instance of a crawler');

        $crawler = $this->createTestCrawler()->filter('ul');

        $this->assertEquals(6, count($crawler->filterXPath('//li')), '->filterXPath() filters the node list with the XPath expression');
    }

    /**
     * @covers Symfony\Component\DomCrawler\Crawler::filter
     */
    public function testFilter()
    {
        $crawler = $this->createTestCrawler();
        $this->assertNotSame($crawler, $crawler->filter('li'), '->filter() returns a new instance of a crawler');
        $this->assertInstanceOf('Symfony\\Component\\DomCrawler\\Crawler', $crawler, '->filter() returns a new instance of a crawler');

        $crawler = $this->createTestCrawler()->filter('ul');

        $this->assertEquals(6, count($crawler->filter('li')), '->filter() filters the node list with the CSS selector');
    }

    public function testSelectLink()
    {
        $crawler = $this->createTestCrawler();
        $this->assertNotSame($crawler, $crawler->selectLink('Foo'), '->selectLink() returns a new instance of a crawler');
        $this->assertInstanceOf('Symfony\\Component\\DomCrawler\\Crawler', $crawler, '->selectLink() returns a new instance of a crawler');

        $this->assertEquals(1, count($crawler->selectLink('Fabien\'s Foo')), '->selectLink() selects links by the node values');
        $this->assertEquals(1, count($crawler->selectLink('Fabien\'s Bar')), '->selectLink() selects links by the alt attribute of a clickable image');

        $this->assertEquals(2, count($crawler->selectLink('Fabien"s Foo')), '->selectLink() selects links by the node values');
        $this->assertEquals(2, count($crawler->selectLink('Fabien"s Bar')), '->selectLink() selects links by the alt attribute of a clickable image');

        $this->assertEquals(1, count($crawler->selectLink('\' Fabien"s Foo')), '->selectLink() selects links by the node values');
        $this->assertEquals(1, count($crawler->selectLink('\' Fabien"s Bar')), '->selectLink() selects links by the alt attribute of a clickable image');

        $this->assertEquals(4, count($crawler->selectLink('Foo')), '->selectLink() selects links by the node values');
        $this->assertEquals(4, count($crawler->selectLink('Bar')), '->selectLink() selects links by the node values');
    }

    public function testSelectButton()
    {
        $crawler = $this->createTestCrawler();
        $this->assertNotSame($crawler, $crawler->selectButton('FooValue'), '->selectButton() returns a new instance of a crawler');
        $this->assertInstanceOf('Symfony\\Component\\DomCrawler\\Crawler', $crawler, '->selectButton() returns a new instance of a crawler');

        $this->assertEquals(1, $crawler->selectButton('FooValue')->count(), '->selectButton() selects buttons');
        $this->assertEquals(1, $crawler->selectButton('FooName')->count(), '->selectButton() selects buttons');
        $this->assertEquals(1, $crawler->selectButton('FooId')->count(), '->selectButton() selects buttons');

        $this->assertEquals(1, $crawler->selectButton('BarValue')->count(), '->selectButton() selects buttons');
        $this->assertEquals(1, $crawler->selectButton('BarName')->count(), '->selectButton() selects buttons');
        $this->assertEquals(1, $crawler->selectButton('BarId')->count(), '->selectButton() selects buttons');
    }

    public function testLink()
    {
        $crawler = $this->createTestCrawler('http://example.com/bar/')->selectLink('Foo');
        $this->assertInstanceOf('Symfony\\Component\\DomCrawler\\Link', $crawler->link(), '->link() returns a Link instance');

        $this->assertEquals('POST', $crawler->link('post')->getMethod(), '->link() takes a method as its argument');

        $crawler = $this->createTestCrawler('http://example.com/bar')->selectLink('GetLink');
        $this->assertEquals('http://example.com/bar?get=param', $crawler->link()->getUri(), '->link() returns a Link instance');

        try {
            $this->createTestCrawler()->filter('ol')->link();
            $this->fail('->link() throws an \InvalidArgumentException if the node list is empty');
        } catch (\InvalidArgumentException $e) {
            $this->assertTrue(true, '->link() throws an \InvalidArgumentException if the node list is empty');
        }
    }

    public function testLinks()
    {
        $crawler = $this->createTestCrawler('http://example.com/bar/')->selectLink('Foo');
        $this->assertInternalType('array', $crawler->links(), '->links() returns an array');

        $this->assertEquals(4, count($crawler->links()), '->links() returns an array');
        $links = $crawler->links();
        $this->assertInstanceOf('Symfony\\Component\\DomCrawler\\Link', $links[0], '->links() returns an array of Link instances');

        $this->assertEquals(array(), $this->createTestCrawler()->filter('ol')->links(), '->links() returns an empty array if the node selection is empty');
    }

    public function testForm()
    {
        $crawler = $this->createTestCrawler('http://example.com/bar/')->selectButton('FooValue');
        $this->assertInstanceOf('Symfony\\Component\\DomCrawler\\Form', $crawler->form(), '->form() returns a Form instance');

        $this->assertEquals(array('FooName' => 'FooBar'), $crawler->form(array('FooName' => 'FooBar'))->getValues(), '->form() takes an array of values to submit as its first argument');

        try {
            $this->createTestCrawler()->filter('ol')->form();
            $this->fail('->form() throws an \InvalidArgumentException if the node list is empty');
        } catch (\InvalidArgumentException $e) {
            $this->assertTrue(true, '->form() throws an \InvalidArgumentException if the node list is empty');
        }
    }

    public function testLast()
    {
        $crawler = $this->createTestCrawler()->filter('ul.first li');
        $this->assertNotSame($crawler, $crawler->last(), '->last() returns a new instance of a crawler');
        $this->assertInstanceOf('Symfony\\Component\\DomCrawler\\Crawler', $crawler, '->last() returns a new instance of a crawler');

        $this->assertEquals('Three', $crawler->last()->text());
    }

    public function testFirst()
    {
        $crawler = $this->createTestCrawler()->filter('li');
        $this->assertNotSame($crawler, $crawler->first(), '->first() returns a new instance of a crawler');
        $this->assertInstanceOf('Symfony\\Component\\DomCrawler\\Crawler', $crawler, '->first() returns a new instance of a crawler');

        $this->assertEquals('One', $crawler->first()->text());
    }

    public function testSiblings()
    {
        $crawler = $this->createTestCrawler()->filter('li')->eq(1);
        $this->assertNotSame($crawler, $crawler->siblings(), '->siblings() returns a new instance of a crawler');
        $this->assertInstanceOf('Symfony\\Component\\DomCrawler\\Crawler', $crawler, '->siblings() returns a new instance of a crawler');

        $nodes = $crawler->siblings();
        $this->assertEquals(2, $nodes->count());
        $this->assertEquals('One', $nodes->eq(0)->text());
        $this->assertEquals('Three', $nodes->eq(1)->text());

        $nodes = $this->createTestCrawler()->filter('li')->eq(0)->siblings();
        $this->assertEquals(2, $nodes->count());
        $this->assertEquals('Two', $nodes->eq(0)->text());
        $this->assertEquals('Three', $nodes->eq(1)->text());

        try {
            $this->createTestCrawler()->filter('ol')->siblings();
            $this->fail('->siblings() throws an \InvalidArgumentException if the node list is empty');
        } catch (\InvalidArgumentException $e) {
            $this->assertTrue(true, '->siblings() throws an \InvalidArgumentException if the node list is empty');
        }
    }

    public function testNextAll()
    {
        $crawler = $this->createTestCrawler()->filter('li')->eq(1);
        $this->assertNotSame($crawler, $crawler->nextAll(), '->nextAll() returns a new instance of a crawler');
        $this->assertInstanceOf('Symfony\\Component\\DomCrawler\\Crawler', $crawler, '->nextAll() returns a new instance of a crawler');

        $nodes = $crawler->nextAll();
        $this->assertEquals(1, $nodes->count());
        $this->assertEquals('Three', $nodes->eq(0)->text());

        try {
            $this->createTestCrawler()->filter('ol')->nextAll();
            $this->fail('->nextAll() throws an \InvalidArgumentException if the node list is empty');
        } catch (\InvalidArgumentException $e) {
            $this->assertTrue(true, '->nextAll() throws an \InvalidArgumentException if the node list is empty');
        }
    }

    public function testPreviousAll()
    {
        $crawler = $this->createTestCrawler()->filter('li')->eq(2);
        $this->assertNotSame($crawler, $crawler->previousAll(), '->previousAll() returns a new instance of a crawler');
        $this->assertInstanceOf('Symfony\\Component\\DomCrawler\\Crawler', $crawler, '->previousAll() returns a new instance of a crawler');

        $nodes = $crawler->previousAll();
        $this->assertEquals(2, $nodes->count());
        $this->assertEquals('Two', $nodes->eq(0)->text());

        try {
            $this->createTestCrawler()->filter('ol')->previousAll();
            $this->fail('->previousAll() throws an \InvalidArgumentException if the node list is empty');
        } catch (\InvalidArgumentException $e) {
            $this->assertTrue(true, '->previousAll() throws an \InvalidArgumentException if the node list is empty');
        }
    }

    public function testChildren()
    {
        $crawler = $this->createTestCrawler()->filter('ul');
        $this->assertNotSame($crawler, $crawler->children(), '->children() returns a new instance of a crawler');
        $this->assertInstanceOf('Symfony\\Component\\DomCrawler\\Crawler', $crawler, '->children() returns a new instance of a crawler');

        $nodes = $crawler->children();
        $this->assertEquals(3, $nodes->count());
        $this->assertEquals('One', $nodes->eq(0)->text());
        $this->assertEquals('Two', $nodes->eq(1)->text());
        $this->assertEquals('Three', $nodes->eq(2)->text());

        try {
            $this->createTestCrawler()->filter('ol')->children();
            $this->fail('->children() throws an \InvalidArgumentException if the node list is empty');
        } catch (\InvalidArgumentException $e) {
            $this->assertTrue(true, '->children() throws an \InvalidArgumentException if the node list is empty');
        }
    }

    public function testParents()
    {
        $crawler = $this->createTestCrawler()->filter('li:first-child');
        $this->assertNotSame($crawler, $crawler->parents(), '->parents() returns a new instance of a crawler');
        $this->assertInstanceOf('Symfony\\Component\\DomCrawler\\Crawler', $crawler, '->parents() returns a new instance of a crawler');

        $nodes = $crawler->parents();
        $this->assertEquals(3, $nodes->count());

        $nodes = $this->createTestCrawler()->filter('html')->parents();
        $this->assertEquals(0, $nodes->count());

        try {
            $this->createTestCrawler()->filter('ol')->parents();
            $this->fail('->parents() throws an \InvalidArgumentException if the node list is empty');
        } catch (\InvalidArgumentException $e) {
            $this->assertTrue(true, '->parents() throws an \InvalidArgumentException if the node list is empty');
        }
    }

    public function createTestCrawler($uri = null)
    {
        $dom = new \DOMDocument();
        $dom->loadHTML('
            <html>
                <body>
                    <a href="foo">Foo</a>
                    <a href="/foo">   Fabien\'s Foo   </a>
                    <a href="/foo">Fabien"s Foo</a>
                    <a href="/foo">\' Fabien"s Foo</a>

                    <a href="/bar"><img alt="Bar"/></a>
                    <a href="/bar"><img alt="   Fabien\'s Bar   "/></a>
                    <a href="/bar"><img alt="Fabien&quot;s Bar"/></a>
                    <a href="/bar"><img alt="\' Fabien&quot;s Bar"/></a>

                    <a href="?get=param">GetLink</a>

                    <form action="foo">
                        <input type="submit" value="FooValue" name="FooName" id="FooId" />
                        <input type="button" value="BarValue" name="BarName" id="BarId" />
                        <button value="ButtonValue" name="ButtonName" id="ButtonId" />
                    </form>

                    <ul class="first">
                        <li class="first">One</li>
                        <li>Two</li>
                        <li>Three</li>
                    </ul>
                    <ul>
                        <li>One Bis</li>
                        <li>Two Bis</li>
                        <li>Three Bis</li>
                    </ul>
                </body>
            </html>
        ');

        return new Crawler($dom, $uri);
    }

    protected function createDomDocument()
    {
        $dom = new \DOMDocument();
        $dom->loadXML('<html><div class="foo"></div></html>');

        return $dom;
    }

    protected function createNodeList()
    {
        $dom = new \DOMDocument();
        $dom->loadXML('<html><div class="foo"></div></html>');
        $domxpath = new \DOMXPath($dom);

        return $domxpath->query('//div');
    }
}
