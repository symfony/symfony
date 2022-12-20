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
use Symfony\Bridge\PhpUnit\ExpectDeprecationTrait;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\DomCrawler\Form;
use Symfony\Component\DomCrawler\Image;
use Symfony\Component\DomCrawler\Link;

abstract class AbstractCrawlerTest extends TestCase
{
    use ExpectDeprecationTrait;

    abstract public function getDoctype(): string;

    protected function createCrawler($node = null, string $uri = null, string $baseHref = null)
    {
        return new Crawler($node, $uri, $baseHref);
    }

    public function testConstructor()
    {
        $crawler = $this->createCrawler();
        self::assertCount(0, $crawler, '__construct() returns an empty crawler');

        $doc = new \DOMDocument();
        $node = $doc->createElement('test');

        $crawler = $this->createCrawler($node);
        self::assertCount(1, $crawler, '__construct() takes a node as a first argument');
    }

    public function testGetUri()
    {
        $uri = 'http://symfony.com';
        $crawler = $this->createCrawler(null, $uri);
        self::assertEquals($uri, $crawler->getUri());
    }

    public function testGetBaseHref()
    {
        $baseHref = 'http://symfony.com';
        $crawler = $this->createCrawler(null, null, $baseHref);
        self::assertEquals($baseHref, $crawler->getBaseHref());
    }

    public function testAdd()
    {
        $crawler = $this->createCrawler();
        $crawler->add($this->createDomDocument());
        self::assertEquals('foo', $crawler->filterXPath('//div')->attr('class'), '->add() adds nodes from a \DOMDocument');

        $crawler = $this->createCrawler();
        $crawler->add($this->createNodeList());
        self::assertEquals('foo', $crawler->filterXPath('//div')->attr('class'), '->add() adds nodes from a \DOMNodeList');

        $list = [];
        foreach ($this->createNodeList() as $node) {
            $list[] = $node;
        }
        $crawler = $this->createCrawler();
        $crawler->add($list);
        self::assertEquals('foo', $crawler->filterXPath('//div')->attr('class'), '->add() adds nodes from an array of nodes');

        $crawler = $this->createCrawler();
        $crawler->add($this->createNodeList()->item(0));
        self::assertEquals('foo', $crawler->filterXPath('//div')->attr('class'), '->add() adds nodes from a \DOMNode');

        $crawler = $this->createCrawler();
        $crawler->add($this->getDoctype().'<html><body>Foo</body></html>');
        self::assertEquals('Foo', $crawler->filterXPath('//body')->text(), '->add() adds nodes from a string');
    }

    public function testAddInvalidType()
    {
        self::expectException(\InvalidArgumentException::class);
        $crawler = $this->createCrawler();
        $crawler->add(1);
    }

    public function testAddMultipleDocumentNode()
    {
        self::expectException(\InvalidArgumentException::class);
        self::expectExceptionMessage('Attaching DOM nodes from multiple documents in the same crawler is forbidden.');
        $crawler = $this->createTestCrawler();
        $crawler->addHtmlContent($this->getDoctype().'<html><div class="foo"></html>', 'UTF-8');
    }

    public function testAddHtmlContent()
    {
        $crawler = $this->createCrawler();
        $crawler->addHtmlContent($this->getDoctype().'<html><div class="foo"></html>', 'UTF-8');

        self::assertEquals('foo', $crawler->filterXPath('//div')->attr('class'), '->addHtmlContent() adds nodes from an HTML string');
    }

    public function testAddHtmlContentWithBaseTag()
    {
        $crawler = $this->createCrawler();
        $crawler->addHtmlContent($this->getDoctype().'<html><head><base href="http://symfony.com"></head><a href="/contact"></a></html>', 'UTF-8');

        self::assertEquals('http://symfony.com', $crawler->filterXPath('//base')->attr('href'), '->addHtmlContent() adds nodes from an HTML string');
        self::assertEquals('http://symfony.com/contact', $crawler->filterXPath('//a')->link()->getUri(), '->addHtmlContent() adds nodes from an HTML string');
    }

    /**
     * @requires extension mbstring
     */
    public function testAddHtmlContentCharset()
    {
        $crawler = $this->createCrawler();
        $crawler->addHtmlContent($this->getDoctype().'<html><div class="foo">Tiếng Việt</html>', 'UTF-8');

        self::assertEquals('Tiếng Việt', $crawler->filterXPath('//div')->text());
    }

    public function testAddHtmlContentInvalidBaseTag()
    {
        $crawler = $this->createCrawler(null, 'http://symfony.com');
        $crawler->addHtmlContent($this->getDoctype().'<html><head><base target="_top"></head><a href="/contact"></a></html>', 'UTF-8');

        self::assertEquals('http://symfony.com/contact', current($crawler->filterXPath('//a')->links())->getUri(), '->addHtmlContent() correctly handles a non-existent base tag href attribute');
    }

    /**
     * @requires extension mbstring
     */
    public function testAddHtmlContentCharsetGbk()
    {
        $crawler = $this->createCrawler();
        // gbk encode of <html><p>中文</p></html>
        $crawler->addHtmlContent($this->getDoctype().base64_decode('PGh0bWw+PHA+1tDOxDwvcD48L2h0bWw+'), 'gbk');

        self::assertEquals('中文', $crawler->filterXPath('//p')->text());
    }

    public function testAddXmlContent()
    {
        $crawler = $this->createCrawler();
        $crawler->addXmlContent($this->getDoctype().'<html><div class="foo"></div></html>', 'UTF-8');

        self::assertEquals('foo', $crawler->filterXPath('//div')->attr('class'), '->addXmlContent() adds nodes from an XML string');
    }

    public function testAddXmlContentCharset()
    {
        $crawler = $this->createCrawler();
        $crawler->addXmlContent($this->getDoctype().'<html><div class="foo">Tiếng Việt</div></html>', 'UTF-8');

        self::assertEquals('Tiếng Việt', $crawler->filterXPath('//div')->text());
    }

    public function testAddContent()
    {
        $crawler = $this->createCrawler();
        $crawler->addContent($this->getDoctype().'<html><div class="foo"></html>', 'text/html; charset=UTF-8');
        self::assertEquals('foo', $crawler->filterXPath('//div')->attr('class'), '->addContent() adds nodes from an HTML string');

        $crawler = $this->createCrawler();
        $crawler->addContent($this->getDoctype().'<html><div class="foo"></html>', 'text/html; charset=UTF-8; dir=RTL');
        self::assertEquals('foo', $crawler->filterXPath('//div')->attr('class'), '->addContent() adds nodes from an HTML string with extended content type');

        $crawler = $this->createCrawler();
        $crawler->addContent($this->getDoctype().'<html><div class="foo"></html>');
        self::assertEquals('foo', $crawler->filterXPath('//div')->attr('class'), '->addContent() uses text/html as the default type');

        $crawler = $this->createCrawler();
        $crawler->addContent($this->getDoctype().'<html><div class="foo"></div></html>', 'text/xml; charset=UTF-8');
        self::assertEquals('foo', $crawler->filterXPath('//div')->attr('class'), '->addContent() adds nodes from an XML string');

        $crawler = $this->createCrawler();
        $crawler->addContent($this->getDoctype().'<html><div class="foo"></div></html>', 'text/xml');
        self::assertEquals('foo', $crawler->filterXPath('//div')->attr('class'), '->addContent() adds nodes from an XML string');

        $crawler = $this->createCrawler();
        $crawler->addContent('foo bar', 'text/plain');
        self::assertCount(0, $crawler, '->addContent() does nothing if the type is not (x|ht)ml');

        $crawler = $this->createCrawler();
        $crawler->addContent($this->getDoctype().'<html><meta http-equiv="Content-Type" content="text/html; charset=utf-8" /><span>中文</span></html>');
        self::assertEquals('中文', $crawler->filterXPath('//span')->text(), '->addContent() guess wrong charset');

        $crawler = $this->createCrawler();
        $crawler->addContent($this->getDoctype().'<html><meta http-equiv="Content-Type" content="text/html; charset=unicode" /><div class="foo"></html></html>');
        self::assertEquals('foo', $crawler->filterXPath('//div')->attr('class'), '->addContent() ignores bad charset');
    }

    /**
     * @requires extension iconv
     */
    public function testAddContentNonUtf8()
    {
        $crawler = $this->createCrawler();
        $crawler->addContent(iconv('UTF-8', 'SJIS', $this->getDoctype().'<html><head><meta charset="Shift_JIS"></head><body>日本語</body></html>'));
        self::assertEquals('日本語', $crawler->filterXPath('//body')->text(), '->addContent() can recognize "Shift_JIS" in html5 meta charset tag');
    }

    public function testAddDocument()
    {
        $crawler = $this->createCrawler();
        $crawler->addDocument($this->createDomDocument());

        self::assertEquals('foo', $crawler->filterXPath('//div')->attr('class'), '->addDocument() adds nodes from a \DOMDocument');
    }

    public function testAddNodeList()
    {
        $crawler = $this->createCrawler();
        $crawler->addNodeList($this->createNodeList());

        self::assertEquals('foo', $crawler->filterXPath('//div')->attr('class'), '->addNodeList() adds nodes from a \DOMNodeList');
    }

    public function testAddNodes()
    {
        $list = [];
        foreach ($this->createNodeList() as $node) {
            $list[] = $node;
        }

        $crawler = $this->createCrawler();
        $crawler->addNodes($list);

        self::assertEquals('foo', $crawler->filterXPath('//div')->attr('class'), '->addNodes() adds nodes from an array of nodes');
    }

    public function testAddNode()
    {
        $crawler = $this->createCrawler();
        $crawler->addNode($this->createNodeList()->item(0));

        self::assertEquals('foo', $crawler->filterXPath('//div')->attr('class'), '->addNode() adds nodes from a \DOMNode');
    }

    public function testClear()
    {
        $doc = new \DOMDocument();
        $node = $doc->createElement('test');

        $crawler = $this->createCrawler($node);
        $crawler->clear();
        self::assertCount(0, $crawler, '->clear() removes all the nodes from the crawler');
    }

    public function testEq()
    {
        $crawler = $this->createTestCrawler()->filterXPath('//li');
        self::assertNotSame($crawler, $crawler->eq(0), '->eq() returns a new instance of a crawler');
        self::assertInstanceOf(Crawler::class, $crawler->eq(0), '->eq() returns a new instance of a crawler');

        self::assertEquals('Two', $crawler->eq(1)->text(), '->eq() returns the nth node of the list');
        self::assertCount(0, $crawler->eq(100), '->eq() returns an empty crawler if the nth node does not exist');
    }

    public function testNormalizeWhiteSpace()
    {
        $crawler = $this->createTestCrawler()->filterXPath('//p');
        self::assertSame('Elsa <3', $crawler->text(null, true), '->text(null, true) returns the text with normalized whitespace');
        self::assertNotSame('Elsa <3', $crawler->text(null, false));
    }

    public function testEach()
    {
        $data = $this->createTestCrawler()->filterXPath('//ul[1]/li')->each(function ($node, $i) {
            return $i.'-'.$node->text();
        });

        self::assertEquals(['0-One', '1-Two', '2-Three'], $data, '->each() executes an anonymous function on each node of the list');
    }

    public function testIteration()
    {
        $crawler = $this->createTestCrawler()->filterXPath('//li');

        self::assertInstanceOf(\Traversable::class, $crawler);
        self::assertContainsOnlyInstancesOf('DOMElement', iterator_to_array($crawler), 'Iterating a Crawler gives DOMElement instances');
    }

    public function testSlice()
    {
        $crawler = $this->createTestCrawler()->filterXPath('//ul[1]/li');
        self::assertNotSame($crawler->slice(), $crawler, '->slice() returns a new instance of a crawler');
        self::assertInstanceOf(Crawler::class, $crawler->slice(), '->slice() returns a new instance of a crawler');

        self::assertCount(3, $crawler->slice(), '->slice() does not slice the nodes in the list if any param is entered');
        self::assertCount(1, $crawler->slice(1, 1), '->slice() slices the nodes in the list');
    }

    public function testReduce()
    {
        $crawler = $this->createTestCrawler()->filterXPath('//ul[1]/li');
        $nodes = $crawler->reduce(function ($node, $i) {
            return 1 !== $i;
        });
        self::assertNotSame($nodes, $crawler, '->reduce() returns a new instance of a crawler');
        self::assertInstanceOf(Crawler::class, $nodes, '->reduce() returns a new instance of a crawler');

        self::assertCount(2, $nodes, '->reduce() filters the nodes in the list');
    }

    public function testAttr()
    {
        self::assertEquals('first', $this->createTestCrawler()->filterXPath('//li')->attr('class'), '->attr() returns the attribute of the first element of the node list');

        try {
            $this->createTestCrawler()->filterXPath('//ol')->attr('class');
            self::fail('->attr() throws an \InvalidArgumentException if the node list is empty');
        } catch (\InvalidArgumentException $e) {
            self::assertTrue(true, '->attr() throws an \InvalidArgumentException if the node list is empty');
        }
    }

    public function testMissingAttrValueIsNull()
    {
        $crawler = $this->createCrawler();
        $crawler->addContent($this->getDoctype().'<html><div non-empty-attr="sample value" empty-attr=""></div></html>', 'text/html; charset=UTF-8');
        $div = $crawler->filterXPath('//div');

        self::assertEquals('sample value', $div->attr('non-empty-attr'), '->attr() reads non-empty attributes correctly');
        self::assertEquals('', $div->attr('empty-attr'), '->attr() reads empty attributes correctly');
        self::assertNull($div->attr('missing-attr'), '->attr() reads missing attributes correctly');
    }

    public function testNodeName()
    {
        self::assertEquals('li', $this->createTestCrawler()->filterXPath('//li')->nodeName(), '->nodeName() returns the node name of the first element of the node list');

        try {
            $this->createTestCrawler()->filterXPath('//ol')->nodeName();
            self::fail('->nodeName() throws an \InvalidArgumentException if the node list is empty');
        } catch (\InvalidArgumentException $e) {
            self::assertTrue(true, '->nodeName() throws an \InvalidArgumentException if the node list is empty');
        }
    }

    public function testText()
    {
        self::assertEquals('One', $this->createTestCrawler()->filterXPath('//li')->text(), '->text() returns the node value of the first element of the node list');

        try {
            $this->createTestCrawler()->filterXPath('//ol')->text();
            self::fail('->text() throws an \InvalidArgumentException if the node list is empty');
        } catch (\InvalidArgumentException $e) {
            self::assertTrue(true, '->text() throws an \InvalidArgumentException if the node list is empty');
        }

        self::assertSame('my value', $this->createTestCrawler(null)->filterXPath('//ol')->text('my value'));
    }

    public function testInnerText()
    {
        self::assertCount(1, $crawler = $this->createTestCrawler()->filterXPath('//*[@id="complex-element"]'));

        self::assertSame('Parent text Child text', $crawler->text());
        self::assertSame('Parent text', $crawler->innerText());
    }

    public function testHtml()
    {
        self::assertEquals('<img alt="Bar">', $this->createTestCrawler()->filterXPath('//a[5]')->html());
        self::assertEquals('<input type="text" value="TextValue" name="TextName"><input type="submit" value="FooValue" name="FooName" id="FooId"><input type="button" value="BarValue" name="BarName" id="BarId"><button value="ButtonValue" name="ButtonName" id="ButtonId"></button>', trim(preg_replace('~>\s+<~', '><', $this->createTestCrawler()->filterXPath('//form[@id="FooFormId"]')->html())));

        try {
            $this->createTestCrawler()->filterXPath('//ol')->html();
            self::fail('->html() throws an \InvalidArgumentException if the node list is empty');
        } catch (\InvalidArgumentException $e) {
            self::assertTrue(true, '->html() throws an \InvalidArgumentException if the node list is empty');
        }

        self::assertSame('my value', $this->createTestCrawler(null)->filterXPath('//ol')->html('my value'));
    }

    public function testEmojis()
    {
        $crawler = $this->createCrawler('<body><p>Hey 👋</p></body>');

        self::assertSame('<body><p>Hey 👋</p></body>', $crawler->html());
    }

    public function testExtract()
    {
        $crawler = $this->createTestCrawler()->filterXPath('//ul[1]/li');

        self::assertEquals(['One', 'Two', 'Three'], $crawler->extract(['_text']), '->extract() returns an array of extracted data from the node list');
        self::assertEquals([['One', 'first'], ['Two', ''], ['Three', '']], $crawler->extract(['_text', 'class']), '->extract() returns an array of extracted data from the node list');
        self::assertEquals([[], [], []], $crawler->extract([]), '->extract() returns empty arrays if the attribute list is empty');

        self::assertEquals([], $this->createTestCrawler()->filterXPath('//ol')->extract(['_text']), '->extract() returns an empty array if the node list is empty');

        self::assertEquals([['One', 'li'], ['Two', 'li'], ['Three', 'li']], $crawler->extract(['_text', '_name']), '->extract() returns an array of extracted data from the node list');
    }

    public function testFilterXpathComplexQueries()
    {
        $crawler = $this->createTestCrawler()->filterXPath('//body');

        self::assertCount(0, $crawler->filterXPath('/input'));
        self::assertCount(0, $crawler->filterXPath('/body'));
        self::assertCount(1, $crawler->filterXPath('./body'));
        self::assertCount(1, $crawler->filterXPath('.//body'));
        self::assertCount(5, $crawler->filterXPath('.//input'));
        self::assertCount(4, $crawler->filterXPath('//form')->filterXPath('//button | //input'));
        self::assertCount(1, $crawler->filterXPath('body'));
        self::assertCount(6, $crawler->filterXPath('//button | //input'));
        self::assertCount(1, $crawler->filterXPath('//body'));
        self::assertCount(1, $crawler->filterXPath('descendant-or-self::body'));
        self::assertCount(1, $crawler->filterXPath('//div[@id="parent"]')->filterXPath('./div'), 'A child selection finds only the current div');
        self::assertCount(3, $crawler->filterXPath('//div[@id="parent"]')->filterXPath('descendant::div'), 'A descendant selector matches the current div and its child');
        self::assertCount(3, $crawler->filterXPath('//div[@id="parent"]')->filterXPath('//div'), 'A descendant selector matches the current div and its child');
        self::assertCount(5, $crawler->filterXPath('(//a | //div)//img'));
        self::assertCount(7, $crawler->filterXPath('((//a | //div)//img | //ul)'));
        self::assertCount(7, $crawler->filterXPath('( ( //a | //div )//img | //ul )'));
        self::assertCount(1, $crawler->filterXPath("//a[./@href][((./@id = 'Klausi|Claudiu' or normalize-space(string(.)) = 'Klausi|Claudiu' or ./@title = 'Klausi|Claudiu' or ./@rel = 'Klausi|Claudiu') or .//img[./@alt = 'Klausi|Claudiu'])]"));
    }

    public function testFilterXPath()
    {
        $crawler = $this->createTestCrawler();
        self::assertNotSame($crawler, $crawler->filterXPath('//li'), '->filterXPath() returns a new instance of a crawler');
        self::assertInstanceOf(Crawler::class, $crawler->filterXPath('//li'), '->filterXPath() returns a new instance of a crawler');

        $crawler = $this->createTestCrawler()->filterXPath('//ul');
        self::assertCount(6, $crawler->filterXPath('//li'), '->filterXPath() filters the node list with the XPath expression');

        $crawler = $this->createTestCrawler();
        self::assertCount(3, $crawler->filterXPath('//body')->filterXPath('//button')->ancestors(), '->filterXpath() preserves ancestors when chained');
    }

    public function testFilterRemovesDuplicates()
    {
        $crawler = $this->createTestCrawler()->filter('html, body')->filter('li');
        self::assertCount(6, $crawler, 'The crawler removes duplicates when filtering.');
    }

    public function testFilterXPathWithDefaultNamespace()
    {
        $crawler = $this->createTestXmlCrawler()->filterXPath('//default:entry/default:id');
        self::assertCount(1, $crawler, '->filterXPath() automatically registers a namespace');
        self::assertSame('tag:youtube.com,2008:video:kgZRZmEc9j4', $crawler->text());
    }

    public function testFilterXPathWithCustomDefaultNamespace()
    {
        $crawler = $this->createTestXmlCrawler();
        $crawler->setDefaultNamespacePrefix('x');
        $crawler = $crawler->filterXPath('//x:entry/x:id');

        self::assertCount(1, $crawler, '->filterXPath() lets to override the default namespace prefix');
        self::assertSame('tag:youtube.com,2008:video:kgZRZmEc9j4', $crawler->text());
    }

    public function testFilterXPathWithNamespace()
    {
        $crawler = $this->createTestXmlCrawler()->filterXPath('//yt:accessControl');
        self::assertCount(2, $crawler, '->filterXPath() automatically registers a namespace');
    }

    public function testFilterXPathWithMultipleNamespaces()
    {
        $crawler = $this->createTestXmlCrawler()->filterXPath('//media:group/yt:aspectRatio');
        self::assertCount(1, $crawler, '->filterXPath() automatically registers multiple namespaces');
        self::assertSame('widescreen', $crawler->text());
    }

    public function testFilterXPathWithManuallyRegisteredNamespace()
    {
        $crawler = $this->createTestXmlCrawler();
        $crawler->registerNamespace('m', 'http://search.yahoo.com/mrss/');

        $crawler = $crawler->filterXPath('//m:group/yt:aspectRatio');
        self::assertCount(1, $crawler, '->filterXPath() uses manually registered namespace');
        self::assertSame('widescreen', $crawler->text());
    }

    public function testFilterXPathWithAnUrl()
    {
        $crawler = $this->createTestXmlCrawler();

        $crawler = $crawler->filterXPath('//media:category[@scheme="http://gdata.youtube.com/schemas/2007/categories.cat"]');
        self::assertCount(1, $crawler);
        self::assertSame('Music', $crawler->text());
    }

    public function testFilterXPathWithFakeRoot()
    {
        $crawler = $this->createTestCrawler();
        self::assertCount(0, $crawler->filterXPath('.'), '->filterXPath() returns an empty result if the XPath references the fake root node');
        self::assertCount(0, $crawler->filterXPath('self::*'), '->filterXPath() returns an empty result if the XPath references the fake root node');
        self::assertCount(0, $crawler->filterXPath('self::_root'), '->filterXPath() returns an empty result if the XPath references the fake root node');
    }

    public function testFilterXPathWithAncestorAxis()
    {
        $crawler = $this->createTestCrawler()->filterXPath('//form');

        self::assertCount(0, $crawler->filterXPath('ancestor::*'), 'The fake root node has no ancestor nodes');
    }

    public function testFilterXPathWithAncestorOrSelfAxis()
    {
        $crawler = $this->createTestCrawler()->filterXPath('//form');

        self::assertCount(0, $crawler->filterXPath('ancestor-or-self::*'), 'The fake root node has no ancestor nodes');
    }

    public function testFilterXPathWithAttributeAxis()
    {
        $crawler = $this->createTestCrawler()->filterXPath('//form');

        self::assertCount(0, $crawler->filterXPath('attribute::*'), 'The fake root node has no attribute nodes');
    }

    public function testFilterXPathWithAttributeAxisAfterElementAxis()
    {
        self::assertCount(3, $this->createTestCrawler()->filterXPath('//form/button/attribute::*'), '->filterXPath() handles attribute axes properly when they are preceded by an element filtering axis');
    }

    public function testFilterXPathWithChildAxis()
    {
        $crawler = $this->createTestCrawler()->filterXPath('//div[@id="parent"]');

        self::assertCount(1, $crawler->filterXPath('child::div'), 'A child selection finds only the current div');
    }

    public function testFilterXPathWithFollowingAxis()
    {
        $crawler = $this->createTestCrawler()->filterXPath('//a');

        self::assertCount(0, $crawler->filterXPath('following::div'), 'The fake root node has no following nodes');
    }

    public function testFilterXPathWithFollowingSiblingAxis()
    {
        $crawler = $this->createTestCrawler()->filterXPath('//a');

        self::assertCount(0, $crawler->filterXPath('following-sibling::div'), 'The fake root node has no following nodes');
    }

    public function testFilterXPathWithNamespaceAxis()
    {
        $crawler = $this->createTestCrawler()->filterXPath('//button');

        self::assertCount(0, $crawler->filterXPath('namespace::*'), 'The fake root node has no namespace nodes');
    }

    public function testFilterXPathWithNamespaceAxisAfterElementAxis()
    {
        $crawler = $this->createTestCrawler()->filterXPath('//div[@id="parent"]/namespace::*');

        self::assertCount(0, $crawler->filterXPath('namespace::*'), 'Namespace axes cannot be requested');
    }

    public function testFilterXPathWithParentAxis()
    {
        $crawler = $this->createTestCrawler()->filterXPath('//button');

        self::assertCount(0, $crawler->filterXPath('parent::*'), 'The fake root node has no parent nodes');
    }

    public function testFilterXPathWithPrecedingAxis()
    {
        $crawler = $this->createTestCrawler()->filterXPath('//form');

        self::assertCount(0, $crawler->filterXPath('preceding::*'), 'The fake root node has no preceding nodes');
    }

    public function testFilterXPathWithPrecedingSiblingAxis()
    {
        $crawler = $this->createTestCrawler()->filterXPath('//form');

        self::assertCount(0, $crawler->filterXPath('preceding-sibling::*'), 'The fake root node has no preceding nodes');
    }

    public function testFilterXPathWithSelfAxes()
    {
        $crawler = $this->createTestCrawler()->filterXPath('//a');

        self::assertCount(0, $crawler->filterXPath('self::a'), 'The fake root node has no "real" element name');
        self::assertCount(0, $crawler->filterXPath('self::a/img'), 'The fake root node has no "real" element name');
        self::assertCount(10, $crawler->filterXPath('self::*/a'));
    }

    public function testFilter()
    {
        $crawler = $this->createTestCrawler();
        self::assertNotSame($crawler, $crawler->filter('li'), '->filter() returns a new instance of a crawler');
        self::assertInstanceOf(Crawler::class, $crawler->filter('li'), '->filter() returns a new instance of a crawler');

        $crawler = $this->createTestCrawler()->filter('ul');

        self::assertCount(6, $crawler->filter('li'), '->filter() filters the node list with the CSS selector');
    }

    public function testFilterWithDefaultNamespace()
    {
        $crawler = $this->createTestXmlCrawler()->filter('default|entry default|id');
        self::assertCount(1, $crawler, '->filter() automatically registers namespaces');
        self::assertSame('tag:youtube.com,2008:video:kgZRZmEc9j4', $crawler->text());
    }

    public function testFilterWithNamespace()
    {
        $crawler = $this->createTestXmlCrawler()->filter('yt|accessControl');
        self::assertCount(2, $crawler, '->filter() automatically registers namespaces');
    }

    public function testFilterWithMultipleNamespaces()
    {
        $crawler = $this->createTestXmlCrawler()->filter('media|group yt|aspectRatio');
        self::assertCount(1, $crawler, '->filter() automatically registers namespaces');
        self::assertSame('widescreen', $crawler->text());
    }

    public function testFilterWithDefaultNamespaceOnly()
    {
        $crawler = $this->createCrawler('<?xml version="1.0" encoding="UTF-8"?>
            <urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
                <url>
                    <loc>http://localhost/foo</loc>
                    <changefreq>weekly</changefreq>
                    <priority>0.5</priority>
                    <lastmod>2012-11-16</lastmod>
               </url>
               <url>
                    <loc>http://localhost/bar</loc>
                    <changefreq>weekly</changefreq>
                    <priority>0.5</priority>
                    <lastmod>2012-11-16</lastmod>
                </url>
            </urlset>
        ');

        self::assertEquals(2, $crawler->filter('url')->count());
    }

    public function testSelectLink()
    {
        $crawler = $this->createTestCrawler();
        self::assertNotSame($crawler, $crawler->selectLink('Foo'), '->selectLink() returns a new instance of a crawler');
        self::assertInstanceOf(Crawler::class, $crawler->selectLink('Foo'), '->selectLink() returns a new instance of a crawler');

        self::assertCount(1, $crawler->selectLink('Fabien\'s Foo'), '->selectLink() selects links by the node values');
        self::assertCount(1, $crawler->selectLink('Fabien\'s Bar'), '->selectLink() selects links by the alt attribute of a clickable image');

        self::assertCount(2, $crawler->selectLink('Fabien"s Foo'), '->selectLink() selects links by the node values');
        self::assertCount(2, $crawler->selectLink('Fabien"s Bar'), '->selectLink() selects links by the alt attribute of a clickable image');

        self::assertCount(1, $crawler->selectLink('\' Fabien"s Foo'), '->selectLink() selects links by the node values');
        self::assertCount(1, $crawler->selectLink('\' Fabien"s Bar'), '->selectLink() selects links by the alt attribute of a clickable image');

        self::assertCount(4, $crawler->selectLink('Foo'), '->selectLink() selects links by the node values');
        self::assertCount(4, $crawler->selectLink('Bar'), '->selectLink() selects links by the node values');
    }

    public function testSelectImage()
    {
        $crawler = $this->createTestCrawler();
        self::assertNotSame($crawler, $crawler->selectImage('Bar'), '->selectImage() returns a new instance of a crawler');
        self::assertInstanceOf(Crawler::class, $crawler->selectImage('Bar'), '->selectImage() returns a new instance of a crawler');

        self::assertCount(1, $crawler->selectImage('Fabien\'s Bar'), '->selectImage() selects images by alt attribute');
        self::assertCount(2, $crawler->selectImage('Fabien"s Bar'), '->selectImage() selects images by alt attribute');
        self::assertCount(1, $crawler->selectImage('\' Fabien"s Bar'), '->selectImage() selects images by alt attribute');
    }

    public function testSelectButton()
    {
        $crawler = $this->createTestCrawler();
        self::assertNotSame($crawler, $crawler->selectButton('FooValue'), '->selectButton() returns a new instance of a crawler');
        self::assertInstanceOf(Crawler::class, $crawler->selectButton('FooValue'), '->selectButton() returns a new instance of a crawler');

        self::assertEquals(1, $crawler->selectButton('FooValue')->count(), '->selectButton() selects buttons');
        self::assertEquals(1, $crawler->selectButton('FooName')->count(), '->selectButton() selects buttons');
        self::assertEquals(1, $crawler->selectButton('FooId')->count(), '->selectButton() selects buttons');

        self::assertEquals(1, $crawler->selectButton('BarValue')->count(), '->selectButton() selects buttons');
        self::assertEquals(1, $crawler->selectButton('BarName')->count(), '->selectButton() selects buttons');
        self::assertEquals(1, $crawler->selectButton('BarId')->count(), '->selectButton() selects buttons');

        self::assertEquals(1, $crawler->selectButton('FooBarValue')->count(), '->selectButton() selects buttons with form attribute too');
        self::assertEquals(1, $crawler->selectButton('FooBarName')->count(), '->selectButton() selects buttons with form attribute too');
    }

    public function testSelectButtonWithSingleQuotesInNameAttribute()
    {
        $html = <<<'HTML'
<html lang="en">
<body>
    <div id="action">
        <a href="/index.php?r=site/login">Login</a>
    </div>
    <form id="login-form" action="/index.php?r=site/login" method="post">
        <button type="submit" name="Click 'Here'">Submit</button>
    </form>
</body>
</html>
HTML;

        $crawler = $this->createCrawler($this->getDoctype().$html);

        self::assertCount(1, $crawler->selectButton('Click \'Here\''));
    }

    public function testSelectButtonWithDoubleQuotesInNameAttribute()
    {
        $html = <<<'HTML'
<html lang="en">
<body>
    <div id="action">
        <a href="/index.php?r=site/login">Login</a>
    </div>
    <form id="login-form" action="/index.php?r=site/login" method="post">
        <button type="submit" name='Click "Here"'>Submit</button>
    </form>
</body>
</html>
HTML;

        $crawler = $this->createCrawler($this->getDoctype().$html);

        self::assertCount(1, $crawler->selectButton('Click "Here"'));
    }

    public function testLink()
    {
        $crawler = $this->createTestCrawler('http://example.com/bar/')->selectLink('Foo');
        self::assertInstanceOf(Link::class, $crawler->link(), '->link() returns a Link instance');

        self::assertEquals('POST', $crawler->link('post')->getMethod(), '->link() takes a method as its argument');

        $crawler = $this->createTestCrawler('http://example.com/bar')->selectLink('GetLink');
        self::assertEquals('http://example.com/bar?get=param', $crawler->link()->getUri(), '->link() returns a Link instance');

        try {
            $this->createTestCrawler()->filterXPath('//ol')->link();
            self::fail('->link() throws an \InvalidArgumentException if the node list is empty');
        } catch (\InvalidArgumentException $e) {
            self::assertTrue(true, '->link() throws an \InvalidArgumentException if the node list is empty');
        }
    }

    public function testInvalidLink()
    {
        self::expectException(\InvalidArgumentException::class);
        self::expectExceptionMessage('The selected node should be instance of DOMElement');
        $crawler = $this->createTestCrawler('http://example.com/bar/');
        $crawler->filterXPath('//li/text()')->link();
    }

    public function testInvalidLinks()
    {
        self::expectException(\InvalidArgumentException::class);
        self::expectExceptionMessage('The selected node should be instance of DOMElement');
        $crawler = $this->createTestCrawler('http://example.com/bar/');
        $crawler->filterXPath('//li/text()')->link();
    }

    public function testImage()
    {
        $crawler = $this->createTestCrawler('http://example.com/bar/')->selectImage('Bar');
        self::assertInstanceOf(Image::class, $crawler->image(), '->image() returns an Image instance');

        try {
            $this->createTestCrawler()->filterXPath('//ol')->image();
            self::fail('->image() throws an \InvalidArgumentException if the node list is empty');
        } catch (\InvalidArgumentException $e) {
            self::assertTrue(true, '->image() throws an \InvalidArgumentException if the node list is empty');
        }
    }

    public function testSelectLinkAndLinkFiltered()
    {
        $html = <<<'HTML'
<html lang="en">
<body>
    <div id="action">
        <a href="/index.php?r=site/login">Login</a>
    </div>
    <form id="login-form" action="/index.php?r=site/login" method="post">
        <button type="submit">Submit</button>
    </form>
</body>
</html>
HTML;

        $crawler = $this->createCrawler($this->getDoctype().$html);
        $filtered = $crawler->filterXPath("descendant-or-self::*[@id = 'login-form']");

        self::assertCount(0, $filtered->selectLink('Login'));
        self::assertCount(1, $filtered->selectButton('Submit'));

        $filtered = $crawler->filterXPath("descendant-or-self::*[@id = 'action']");

        self::assertCount(1, $filtered->selectLink('Login'));
        self::assertCount(0, $filtered->selectButton('Submit'));

        self::assertCount(1, $crawler->selectLink('Login')->selectLink('Login'));
        self::assertCount(1, $crawler->selectButton('Submit')->selectButton('Submit'));
    }

    public function testChaining()
    {
        $crawler = $this->createCrawler($this->getDoctype().'<div name="a"><div name="b"><div name="c"></div></div></div>');

        self::assertEquals('a', $crawler->filterXPath('//div')->filterXPath('div')->filterXPath('div')->attr('name'));
    }

    public function testLinks()
    {
        $crawler = $this->createTestCrawler('http://example.com/bar/')->selectLink('Foo');
        self::assertIsArray($crawler->links(), '->links() returns an array');

        self::assertCount(4, $crawler->links(), '->links() returns an array');
        $links = $crawler->links();
        self::assertContainsOnlyInstancesOf('Symfony\\Component\\DomCrawler\\Link', $links, '->links() returns an array of Link instances');

        self::assertEquals([], $this->createTestCrawler()->filterXPath('//ol')->links(), '->links() returns an empty array if the node selection is empty');
    }

    public function testImages()
    {
        $crawler = $this->createTestCrawler('http://example.com/bar/')->selectImage('Bar');
        self::assertIsArray($crawler->images(), '->images() returns an array');

        self::assertCount(4, $crawler->images(), '->images() returns an array');
        $images = $crawler->images();
        self::assertContainsOnlyInstancesOf('Symfony\\Component\\DomCrawler\\Image', $images, '->images() returns an array of Image instances');

        self::assertEquals([], $this->createTestCrawler()->filterXPath('//ol')->links(), '->links() returns an empty array if the node selection is empty');
    }

    public function testForm()
    {
        $testCrawler = $this->createTestCrawler('http://example.com/bar/');
        $crawler = $testCrawler->selectButton('FooValue');
        $crawler2 = $testCrawler->selectButton('FooBarValue');
        self::assertInstanceOf(Form::class, $crawler->form(), '->form() returns a Form instance');
        self::assertInstanceOf(Form::class, $crawler2->form(), '->form() returns a Form instance');

        self::assertEquals($crawler->form()->getFormNode()->getAttribute('id'), $crawler2->form()->getFormNode()->getAttribute('id'), '->form() works on elements with form attribute');

        self::assertEquals(['FooName' => 'FooBar', 'TextName' => 'TextValue', 'FooTextName' => 'FooTextValue'], $crawler->form(['FooName' => 'FooBar'])->getValues(), '->form() takes an array of values to submit as its first argument');
        self::assertEquals(['FooName' => 'FooValue', 'TextName' => 'TextValue', 'FooTextName' => 'FooTextValue'], $crawler->form()->getValues(), '->getValues() returns correct form values');
        self::assertEquals(['FooBarName' => 'FooBarValue', 'TextName' => 'TextValue', 'FooTextName' => 'FooTextValue'], $crawler2->form()->getValues(), '->getValues() returns correct form values');

        try {
            $this->createTestCrawler()->filterXPath('//ol')->form();
            self::fail('->form() throws an \InvalidArgumentException if the node list is empty');
        } catch (\InvalidArgumentException $e) {
            self::assertTrue(true, '->form() throws an \InvalidArgumentException if the node list is empty');
        }
    }

    public function testInvalidForm()
    {
        self::expectException(\InvalidArgumentException::class);
        self::expectExceptionMessage('The selected node should be instance of DOMElement');
        $crawler = $this->createTestCrawler('http://example.com/bar/');
        $crawler->filterXPath('//li/text()')->form();
    }

    public function testLast()
    {
        $crawler = $this->createTestCrawler()->filterXPath('//ul[1]/li');
        self::assertNotSame($crawler, $crawler->last(), '->last() returns a new instance of a crawler');
        self::assertInstanceOf(Crawler::class, $crawler->last(), '->last() returns a new instance of a crawler');

        self::assertEquals('Three', $crawler->last()->text());
    }

    public function testFirst()
    {
        $crawler = $this->createTestCrawler()->filterXPath('//li');
        self::assertNotSame($crawler, $crawler->first(), '->first() returns a new instance of a crawler');
        self::assertInstanceOf(Crawler::class, $crawler->first(), '->first() returns a new instance of a crawler');

        self::assertEquals('One', $crawler->first()->text());
    }

    public function testSiblings()
    {
        $crawler = $this->createTestCrawler()->filterXPath('//li')->eq(1);
        self::assertNotSame($crawler, $crawler->siblings(), '->siblings() returns a new instance of a crawler');
        self::assertInstanceOf(Crawler::class, $crawler->siblings(), '->siblings() returns a new instance of a crawler');

        $nodes = $crawler->siblings();
        self::assertEquals(2, $nodes->count());
        self::assertEquals('One', $nodes->eq(0)->text());
        self::assertEquals('Three', $nodes->eq(1)->text());

        $nodes = $this->createTestCrawler()->filterXPath('//li')->eq(0)->siblings();
        self::assertEquals(2, $nodes->count());
        self::assertEquals('Two', $nodes->eq(0)->text());
        self::assertEquals('Three', $nodes->eq(1)->text());

        try {
            $this->createTestCrawler()->filterXPath('//ol')->siblings();
            self::fail('->siblings() throws an \InvalidArgumentException if the node list is empty');
        } catch (\InvalidArgumentException $e) {
            self::assertTrue(true, '->siblings() throws an \InvalidArgumentException if the node list is empty');
        }
    }

    public function provideMatchTests()
    {
        yield ['#foo', true, '#foo'];
        yield ['#foo', true, '.foo'];
        yield ['#foo', true, '.other'];
        yield ['#foo', false, '.bar'];

        yield ['#bar', true, '#bar'];
        yield ['#bar', true, '.bar'];
        yield ['#bar', true, '.other'];
        yield ['#bar', false, '.foo'];
    }

    /** @dataProvider provideMatchTests */
    public function testMatch(string $mainNodeSelector, bool $expected, string $selector)
    {
        $html = <<<'HTML'
<html lang="en">
<body>
    <div id="foo" class="foo other">
        <div>
            <div id="bar" class="bar other"></div>
        </div>
    </div>
</body>
</html>
HTML;

        $crawler = $this->createCrawler($this->getDoctype().$html);
        $node = $crawler->filter($mainNodeSelector);
        self::assertSame($expected, $node->matches($selector));
    }

    public function testClosest()
    {
        $html = <<<'HTML'
<html lang="en">
<body>
    <div class="lorem2 ok">
        <div>
            <div class="lorem3 ko"></div>
        </div>
        <div class="lorem1 ok">
            <div id="foo" class="newFoo ok">
                <div class="lorem1 ko"></div>
            </div>
        </div>
    </div>
    <div class="lorem2 ko">
    </div>
</body>
</html>
HTML;

        $crawler = $this->createCrawler($this->getDoctype().$html);
        $foo = $crawler->filter('#foo');

        $newFoo = $foo->closest('#foo');
        self::assertInstanceOf(Crawler::class, $newFoo);
        self::assertSame('newFoo ok', $newFoo->attr('class'));

        $lorem1 = $foo->closest('.lorem1');
        self::assertInstanceOf(Crawler::class, $lorem1);
        self::assertSame('lorem1 ok', $lorem1->attr('class'));

        $lorem2 = $foo->closest('.lorem2');
        self::assertInstanceOf(Crawler::class, $lorem2);
        self::assertSame('lorem2 ok', $lorem2->attr('class'));

        $lorem3 = $foo->closest('.lorem3');
        self::assertNull($lorem3);

        $notFound = $foo->closest('.not-found');
        self::assertNull($notFound);
    }

    public function testOuterHtml()
    {
        $html = <<<'HTML'
<html lang="en">
<body>
    <div class="foo">
    <ul>
        <li>1</li>
        <li>2</li>
        <li>3</li>
    </ul>
</body>
</html>
HTML;

        $crawler = $this->createCrawler($this->getDoctype().$html);
        $bar = $crawler->filter('ul');
        $output = $bar->outerHtml();
        $output = str_replace([' ', "\n"], '', $output);
        $expected = '<ul><li>1</li><li>2</li><li>3</li></ul>';
        self::assertSame($expected, $output);
    }

    public function testNextAll()
    {
        $crawler = $this->createTestCrawler()->filterXPath('//li')->eq(1);
        self::assertNotSame($crawler, $crawler->nextAll(), '->nextAll() returns a new instance of a crawler');
        self::assertInstanceOf(Crawler::class, $crawler->nextAll(), '->nextAll() returns a new instance of a crawler');

        $nodes = $crawler->nextAll();
        self::assertEquals(1, $nodes->count());
        self::assertEquals('Three', $nodes->eq(0)->text());

        try {
            $this->createTestCrawler()->filterXPath('//ol')->nextAll();
            self::fail('->nextAll() throws an \InvalidArgumentException if the node list is empty');
        } catch (\InvalidArgumentException $e) {
            self::assertTrue(true, '->nextAll() throws an \InvalidArgumentException if the node list is empty');
        }
    }

    public function testPreviousAll()
    {
        $crawler = $this->createTestCrawler()->filterXPath('//li')->eq(2);
        self::assertNotSame($crawler, $crawler->previousAll(), '->previousAll() returns a new instance of a crawler');
        self::assertInstanceOf(Crawler::class, $crawler->previousAll(), '->previousAll() returns a new instance of a crawler');

        $nodes = $crawler->previousAll();
        self::assertEquals(2, $nodes->count());
        self::assertEquals('Two', $nodes->eq(0)->text());

        try {
            $this->createTestCrawler()->filterXPath('//ol')->previousAll();
            self::fail('->previousAll() throws an \InvalidArgumentException if the node list is empty');
        } catch (\InvalidArgumentException $e) {
            self::assertTrue(true, '->previousAll() throws an \InvalidArgumentException if the node list is empty');
        }
    }

    public function testChildren()
    {
        $crawler = $this->createTestCrawler()->filterXPath('//ul');
        self::assertNotSame($crawler, $crawler->children(), '->children() returns a new instance of a crawler');
        self::assertInstanceOf(Crawler::class, $crawler->children(), '->children() returns a new instance of a crawler');

        $nodes = $crawler->children();
        self::assertEquals(3, $nodes->count());
        self::assertEquals('One', $nodes->eq(0)->text());
        self::assertEquals('Two', $nodes->eq(1)->text());
        self::assertEquals('Three', $nodes->eq(2)->text());

        try {
            $this->createTestCrawler()->filterXPath('//ol')->children();
            self::fail('->children() throws an \InvalidArgumentException if the node list is empty');
        } catch (\InvalidArgumentException $e) {
            self::assertTrue(true, '->children() throws an \InvalidArgumentException if the node list is empty');
        }

        try {
            $crawler = $this->createCrawler('<p></p>');
            $crawler->filter('p')->children();
            self::assertTrue(true, '->children() does not trigger a notice if the node has no children');
        } catch (\PHPUnit\Framework\Error\Notice $e) {
            self::fail('->children() does not trigger a notice if the node has no children');
        }
    }

    public function testFilteredChildren()
    {
        $html = <<<'HTML'
<html lang="en">
<body>
    <div id="foo">
        <div class="lorem">
            <p class="lorem"></p>
        </div>
        <div class="lorem">
            <span class="lorem"></span>
        </div>
        <span class="ipsum"></span>
    </div>
</body>
</html>
HTML;

        $crawler = $this->createCrawler($this->getDoctype().$html);
        $foo = $crawler->filter('#foo');

        self::assertEquals(3, $foo->children()->count());
        self::assertEquals(2, $foo->children('.lorem')->count());
        self::assertEquals(2, $foo->children('div')->count());
        self::assertEquals(2, $foo->children('div.lorem')->count());
        self::assertEquals(1, $foo->children('span')->count());
        self::assertEquals(1, $foo->children('span.ipsum')->count());
        self::assertEquals(1, $foo->children('.ipsum')->count());
    }

    /**
     * @group legacy
     */
    public function testParents()
    {
        $this->expectDeprecation('Since symfony/dom-crawler 5.3: The Symfony\Component\DomCrawler\Crawler::parents() method is deprecated, use ancestors() instead.');

        $crawler = $this->createTestCrawler()->filterXPath('//li[1]');
        self::assertNotSame($crawler, $crawler->parents(), '->parents() returns a new instance of a crawler');
        self::assertInstanceOf(Crawler::class, $crawler->parents(), '->parents() returns a new instance of a crawler');

        $nodes = $crawler->parents();
        self::assertEquals(3, $nodes->count());

        $nodes = $this->createTestCrawler()->filterXPath('//html')->parents();
        self::assertEquals(0, $nodes->count());

        try {
            $this->createTestCrawler()->filterXPath('//ol')->parents();
            self::fail('->parents() throws an \InvalidArgumentException if the node list is empty');
        } catch (\InvalidArgumentException $e) {
            self::assertTrue(true, '->parents() throws an \InvalidArgumentException if the node list is empty');
        }
    }

    public function testAncestors()
    {
        $crawler = $this->createTestCrawler()->filterXPath('//li[1]');

        $nodes = $crawler->ancestors();

        self::assertNotSame($crawler, $nodes, '->ancestors() returns a new instance of a crawler');
        self::assertInstanceOf(Crawler::class, $nodes, '->ancestors() returns a new instance of a crawler');

        self::assertEquals(3, $crawler->ancestors()->count());

        self::assertEquals(0, $this->createTestCrawler()->filterXPath('//html')->ancestors()->count());
    }

    public function testAncestorsThrowsIfNodeListIsEmpty()
    {
        self::expectException(\InvalidArgumentException::class);

        $this->createTestCrawler()->filterXPath('//ol')->ancestors();
    }

    /**
     * @dataProvider getBaseTagData
     */
    public function testBaseTag($baseValue, $linkValue, $expectedUri, $currentUri = null, $description = '')
    {
        $crawler = $this->createCrawler($this->getDoctype().'<html><base href="'.$baseValue.'"><a href="'.$linkValue.'"></a></html>', $currentUri);
        self::assertEquals($expectedUri, $crawler->filterXPath('//a')->link()->getUri(), $description);
    }

    public function getBaseTagData()
    {
        return [
            ['http://base.com', 'link', 'http://base.com/link'],
            ['//base.com', 'link', 'https://base.com/link', 'https://domain.com', '<base> tag can use a schema-less URL'],
            ['path/', 'link', 'https://domain.com/path/link', 'https://domain.com', '<base> tag can set a path'],
            ['http://base.com', '#', 'http://base.com#', 'http://domain.com/path/link', '<base> tag does work with links to an anchor'],
            ['http://base.com', '', 'http://base.com', 'http://domain.com/path/link', '<base> tag does work with empty links'],
        ];
    }

    /**
     * @dataProvider getBaseTagWithFormData
     */
    public function testBaseTagWithForm($baseValue, $actionValue, $expectedUri, $currentUri = null, $description = null)
    {
        $crawler = $this->createCrawler($this->getDoctype().'<html><base href="'.$baseValue.'"><form method="post" action="'.$actionValue.'"><button type="submit" name="submit"/></form></html>', $currentUri);
        self::assertEquals($expectedUri, $crawler->filterXPath('//button')->form()->getUri(), $description);
    }

    public function getBaseTagWithFormData()
    {
        return [
            ['https://base.com/', 'link/', 'https://base.com/link/', 'https://base.com/link/', '<base> tag does work with a path and relative form action'],
            ['/basepath', '/registration', 'http://domain.com/registration', 'http://domain.com/registration', '<base> tag does work with a path and form action'],
            ['/basepath', '', 'http://domain.com/registration', 'http://domain.com/registration', '<base> tag does work with a path and empty form action'],
            ['http://base.com/', '/registration', 'http://base.com/registration', 'http://domain.com/registration', '<base> tag does work with a URL and form action'],
            ['http://base.com/', 'http://base.com/registration', 'http://base.com/registration', null, '<base> tag does work with a URL and form action'],
            ['http://base.com', '', 'http://domain.com/path/form', 'http://domain.com/path/form', '<base> tag does work with a URL and an empty form action'],
            ['http://base.com/path', '/registration', 'http://base.com/registration', 'http://domain.com/path/form', '<base> tag does work with a URL and form action'],
        ];
    }

    public function testCountOfNestedElements()
    {
        $crawler = $this->createCrawler('<html><body><ul><li>List item 1<ul><li>Sublist item 1</li><li>Sublist item 2</ul></li></ul></body></html>');

        self::assertCount(1, $crawler->filter('li:contains("List item 1")'));
    }

    public function testEvaluateReturnsTypedResultOfXPathExpressionOnADocumentSubset()
    {
        $crawler = $this->createTestCrawler();

        $result = $crawler->filterXPath('//form/input')->evaluate('substring-before(@name, "Name")');

        self::assertSame(['Text', 'Foo', 'Bar'], $result);
    }

    public function testEvaluateReturnsTypedResultOfNamespacedXPathExpressionOnADocumentSubset()
    {
        $crawler = $this->createTestXmlCrawler();

        $result = $crawler->filterXPath('//yt:accessControl/@action')->evaluate('string(.)');

        self::assertSame(['comment', 'videoRespond'], $result);
    }

    public function testEvaluateReturnsTypedResultOfNamespacedXPathExpression()
    {
        $crawler = $this->createTestXmlCrawler();
        $crawler->registerNamespace('youtube', 'http://gdata.youtube.com/schemas/2007');

        $result = $crawler->evaluate('string(//youtube:accessControl/@action)');

        self::assertSame(['comment'], $result);
    }

    public function testEvaluateReturnsACrawlerIfXPathExpressionEvaluatesToANode()
    {
        $crawler = $this->createTestCrawler()->evaluate('//form/input[1]');

        self::assertInstanceOf(Crawler::class, $crawler);
        self::assertCount(1, $crawler);
        self::assertSame('input', $crawler->first()->nodeName());
    }

    public function testEvaluateThrowsAnExceptionIfDocumentIsEmpty()
    {
        self::expectException(\LogicException::class);
        $this->createCrawler()->evaluate('//form/input[1]');
    }

    public function testAddHtmlContentUnsupportedCharset()
    {
        $crawler = $this->createCrawler();
        $crawler->addHtmlContent($this->getDoctype().file_get_contents(__DIR__.'/Fixtures/windows-1250.html'), 'Windows-1250');

        self::assertEquals('Žťčýů', $crawler->filterXPath('//p')->text());
    }

    public function createTestCrawler($uri = null)
    {
        $dom = new \DOMDocument();
        $dom->loadHTML($this->getDoctype().'
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

                    <a href="/example">Klausi|Claudiu</a>

                    <form action="foo" id="FooFormId">
                        <input type="text" value="TextValue" name="TextName" />
                        <input type="submit" value="FooValue" name="FooName" id="FooId" />
                        <input type="button" value="BarValue" name="BarName" id="BarId" />
                        <button value="ButtonValue" name="ButtonName" id="ButtonId" />
                    </form>

                    <input type="submit" value="FooBarValue" name="FooBarName" form="FooFormId" />
                    <input type="text" value="FooTextValue" name="FooTextName" form="FooFormId" />

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
                    <p class="whitespace">
                        Elsa
                        &lt;3
                    </p>
                    <div id="parent">
                        <div id="child"></div>
                        <div id="child2" xmlns:foo="http://example.com"></div>
                    </div>
                    <div id="sibling"><img /></div>
                    <div id="complex-element">
                        Parent text
                        <span>Child text</span>
                    </div>
                </body>
            </html>
        ');

        return $this->createCrawler($dom, $uri);
    }

    protected function createTestXmlCrawler($uri = null)
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>
            <entry xmlns="http://www.w3.org/2005/Atom" xmlns:media="http://search.yahoo.com/mrss/" xmlns:yt="http://gdata.youtube.com/schemas/2007">
                <id>tag:youtube.com,2008:video:kgZRZmEc9j4</id>
                <yt:accessControl action="comment" permission="allowed"/>
                <yt:accessControl action="videoRespond" permission="moderated"/>
                <media:group>
                    <media:title type="plain">Chordates - CrashCourse Biology #24</media:title>
                    <yt:aspectRatio>widescreen</yt:aspectRatio>
                </media:group>
                <media:category label="Music" scheme="http://gdata.youtube.com/schemas/2007/categories.cat">Music</media:category>
            </entry>';

        return $this->createCrawler($xml, $uri);
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
