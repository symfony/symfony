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
use PHPUnit\Framework\Attributes\RequiresPhp;
use Symfony\Component\DomCrawler\DomCrawler;

#[RequiresPhp('8.4')]
class DomHtml5ParserCrawlerTest extends AbstractCrawlerTestCase
{
    public static function getDoctype(): string
    {
        return '<!DOCTYPE html>';
    }

    public function testIteration()
    {
        $crawler = $this->createTestCrawler()->filterXPath('//li');

        $this->assertInstanceOf(\Traversable::class, $crawler);
        $this->assertContainsOnlyInstancesOf('Dom\Element', iterator_to_array($crawler), 'Iterating a Crawler gives DOMElement instances');
    }

    public function testHtml()
    {
        $this->assertEquals('<img alt="Bar">', $this->createTestCrawler()->filterXPath('//a[5]')->html());
        $this->assertEquals('<input type="text" value="TextValue" name="TextName"><input type="submit" value="FooValue" name="FooName" id="FooId"><input type="button" value="BarValue" name="BarName" id="BarId"><button value="ButtonValue" name="ButtonName" id="ButtonId"><input type="submit" value="FooBarValue" name="FooBarName" form="FooFormId"><input type="text" value="FooTextValue" name="FooTextName" form="FooFormId"><input type="image" alt="ImageAlt" form="FooFormId"></button>', trim(preg_replace('~>\s+<~', '><', $this->createTestCrawler()->filterXPath('//form[@id="FooFormId"]')->html())));

        try {
            $this->createTestCrawler()->filterXPath('//ol')->html();
            $this->fail('->html() throws an \InvalidArgumentException if the node list is empty');
        } catch (\InvalidArgumentException $e) {
            $this->assertTrue(true, '->html() throws an \InvalidArgumentException if the node list is empty');
        }

        $this->assertSame('my value', $this->createTestCrawler(null)->filterXPath('//ol')->html('my value'));
    }

    public function testFilterXpathComplexQueries()
    {
        $crawler = $this->createTestCrawler()->filterXPath('//body');

        $this->assertCount(0, $crawler->filterXPath('/input'));
        $this->assertCount(0, $crawler->filterXPath('/body'));
        $this->assertCount(1, $crawler->filterXPath('./body'));
        $this->assertCount(1, $crawler->filterXPath('.//body'));
        $this->assertCount(6, $crawler->filterXPath('.//input'));
        $this->assertCount(7, $crawler->filterXPath('//form')->filterXPath('//button | //input'));
        $this->assertCount(1, $crawler->filterXPath('body'));
        $this->assertCount(8, $crawler->filterXPath('//button | //input'));
        $this->assertCount(1, $crawler->filterXPath('//body'));
        $this->assertCount(1, $crawler->filterXPath('descendant-or-self::body'));
        $this->assertCount(1, $crawler->filterXPath('//div[@id="parent"]')->filterXPath('./div'), 'A child selection finds only the current div');
        $this->assertCount(3, $crawler->filterXPath('//div[@id="parent"]')->filterXPath('descendant::div'), 'A descendant selector matches the current div and its child');
        $this->assertCount(3, $crawler->filterXPath('//div[@id="parent"]')->filterXPath('//div'), 'A descendant selector matches the current div and its child');
        $this->assertCount(5, $crawler->filterXPath('(//a | //div)//img'));
        $this->assertCount(7, $crawler->filterXPath('((//a | //div)//img | //ul)'));
        $this->assertCount(7, $crawler->filterXPath('( ( //a | //div )//img | //ul )'));
        $this->assertCount(1, $crawler->filterXPath("//a[./@href][((./@id = 'Klausi|Claudiu' or normalize-space(string(.)) = 'Klausi|Claudiu' or ./@title = 'Klausi|Claudiu' or ./@rel = 'Klausi|Claudiu') or .//img[./@alt = 'Klausi|Claudiu'])]"));
    }

    public function testAddHtml5()
    {
        // Ensure a bug specific to the DOM extension is fixed (see https://github.com/symfony/symfony/issues/28596)
        $crawler = $this->createCrawler();
        $crawler->add($this->getDoctype().'<html><body><h1><p>Foo</p></h1></body></html>');
        $this->assertEquals('Foo', $crawler->filterXPath('//h1')->text(), '->add() adds nodes from a string');
    }

    #[DataProvider('validHtml5Provider')]
    public function testHtml5ParserParseContentStartingWithValidHeading(string $content)
    {
        $crawler = $this->createCrawler();
        $crawler->addHtmlContent($content);
        self::assertEquals(
            'Foo',
            $crawler->filterXPath('//h1')->text(),
            '->addHtmlContent() parses valid HTML with comment before doctype'
        );
    }

    public function testHtml5ParserNotSameAsNativeParserForSpecificHtml()
    {
        // Html who create a bug specific to the DOM extension (see https://github.com/symfony/symfony/issues/28596)
        $html = $this->getDoctype().'<html><body><h1><p>Foo</p></h1></body></html>';

        $html5Crawler = $this->createCrawler();
        $html5Crawler->add($html);

        $nativeCrawler = parent::createCrawler();
        $nativeCrawler->add($html);

        $this->assertNotEquals($nativeCrawler->filterXPath('//h1')->text(), $html5Crawler->filterXPath('//h1')->text(), 'Native parser and Html5 parser must be different');
    }

    public static function validHtml5Provider(): iterable
    {
        $html = self::getDoctype().'<html><body><h1><p>Foo</p></h1></body></html>';
        $BOM = \chr(0xEF).\chr(0xBB).\chr(0xBF);

        yield 'BOM first' => [$BOM.$html];
        yield 'Single comment' => ['<!-- comment -->'.$html];
        yield 'Multiline comment' => ["<!-- \n multiline comment \n -->".$html];
        yield 'Several comments' => ['<!--c--> <!--cc-->'.$html];
        yield 'Whitespaces' => ['    '.$html];
        yield 'All together' => [$BOM.'  <!--c-->'.$html];
    }

    protected function createCrawler($node = null, ?string $uri = null, ?string $baseHref = null)
    {
        return new DomCrawler($node, $uri, $baseHref);
    }

    protected function createDomDocument()
    {
        return \Dom\HTMLDocument::createFromString(self::getDoctype().'<html><div class="foo"></div></html>', \Dom\HTML_NO_DEFAULT_NS);
    }

    protected function createNodeList()
    {
        $dom = $this->createDomDocument();
        $domxpath = new \Dom\XPath($dom);

        return $domxpath->query('//div');
    }
}
