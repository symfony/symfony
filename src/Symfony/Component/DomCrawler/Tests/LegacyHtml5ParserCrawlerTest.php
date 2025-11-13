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
use Symfony\Component\DomCrawler\Crawler;

#[RequiresPhp('<8.4')]
class LegacyHtml5ParserCrawlerTest extends CrawlerTest
{
    public function testHtml()
    {
        $this->assertEquals('<img alt="Bar">', $this->createTestCrawler()->filterXPath('//a[5]')->html());
        $this->assertEquals('<input type="text" value="TextValue" name="TextName"><input type="submit" value="FooValue" name="FooName" id="FooId"><input type="button" value="BarValue" name="BarName" id="BarId"><button value="ButtonValue" name="ButtonName" id="ButtonId"></button>', trim(preg_replace('~>\s+<~', '><', $this->createTestCrawler()->filterXPath('//form[@id="FooFormId"]')->html())));

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
        $this->assertCount(4, $crawler->filterXPath('//form')->filterXPath('//button | //input'));
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

    #[DataProvider('invalidHtml5Provider')]
    public function testHtml5ParserWithInvalidHeadedContent(string $content)
    {
        $crawler = $this->createCrawler();
        $crawler->addHtmlContent($content);
        self::assertSame('', $crawler->filterXPath('//h1')->text(), '->addHtmlContent failed as expected');
    }

    public static function invalidHtml5Provider(): iterable
    {
        $html = self::getDoctype().'<html><body><h1><p>Foo</p></h1></body></html>';

        yield 'Text' => ['hello world'.$html];
        yield 'Text between comments' => ['<!--c--> test <!--cc-->'.$html];
    }

    public function testHtml5MalformedContent()
    {
        $crawler = $this->createCrawler();
        $crawler->addHtmlContent('<script&>');
        self::assertEquals('<head><script></script></head>', $crawler->html());
    }

    protected function createCrawler($node = null, ?string $uri = null, ?string $baseHref = null)
    {
        return new Crawler($node, $uri, $baseHref, true);
    }
}
