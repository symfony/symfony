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

use Symfony\Component\DomCrawler\DomCrawler;

/**
 * @requires PHP 8.4
 *
 * Native parser is following strictly the specification. Thus, some tests
 * need to be adapted to "fix" some HTML snippets considered as valid by the
 * external parser, but not by the native parser.
 */
class DomCrawlerNativeParserTest extends AbstractCrawlerTestCase
{
    public static function getDoctype(): string
    {
        return '<!DOCTYPE html>';
    }

    protected function createCrawler($node = null, ?string $uri = null, ?string $baseHref = null, bool $useHtml5Parser = false)
    {
        return new DomCrawler($node, $uri, $baseHref, DomCrawler::CRAWLER_ENABLE_HTML5_PARSING | DomCrawler::CRAWLER_USE_NATIVE_PARSER);
    }

    protected static function getCrawlerClass(): string
    {
        return DomCrawler::class;
    }

    public function testAddContent()
    {
        $crawler = $this->createCrawler();
        $crawler->addContent($this->getDoctype().'<html><div class="foo"></div></html>', 'text/html; charset=UTF-8');
        $this->assertEquals('foo', $crawler->filterXPath('//div')->attr('class'), '->addContent() adds nodes from an HTML string');

        $crawler = $this->createCrawler();
        $crawler->addContent($this->getDoctype().'<html><div class="foo"></div></html>', 'text/html; charset=UTF-8; dir=RTL');
        $this->assertEquals('foo', $crawler->filterXPath('//div')->attr('class'), '->addContent() adds nodes from an HTML string with extended content type');

        $crawler = $this->createCrawler();
        $crawler->addContent($this->getDoctype().'<html><div class="foo"></div></html>');
        $this->assertEquals('foo', $crawler->filterXPath('//div')->attr('class'), '->addContent() uses text/html as the default type');

        $crawler = $this->createCrawler();
        $crawler->addContent($this->getDoctype().'<html><div class="foo"></div></html>', 'text/xml; charset=UTF-8');
        $this->assertEquals('foo', $crawler->filterXPath('//div')->attr('class'), '->addContent() adds nodes from an XML string');

        $crawler = $this->createCrawler();
        $crawler->addContent($this->getDoctype().'<html><div class="foo"></div></html>', 'text/xml');
        $this->assertEquals('foo', $crawler->filterXPath('//div')->attr('class'), '->addContent() adds nodes from an XML string');

        $crawler = $this->createCrawler();
        $crawler->addContent('foo bar', 'text/plain');
        $this->assertCount(0, $crawler, '->addContent() does nothing if the type is not (x|ht)ml');

        $crawler = $this->createCrawler();
        $crawler->addContent($this->getDoctype().'<html><meta http-equiv="Content-Type" content="text/html; charset=utf-8"><span>中文</span></html>');
        $this->assertEquals('中文', $crawler->filterXPath('//span')->text(), '->addContent() guess wrong charset');

        $crawler = $this->createCrawler();
        $crawler->addContent($this->getDoctype().'<html><meta http-equiv="Content-Type" content="text/html; charset=unicode"><div class="foo"></div></html></html>');
        $this->assertEquals('foo', $crawler->filterXPath('//div')->attr('class'), '->addContent() ignores bad charset');
    }

    /**
     * @dataProvider getBaseTagWithFormData
     */
    public function testBaseTagWithForm($baseValue, $actionValue, $expectedUri, $currentUri = null, $description = null)
    {
        $crawler = $this->createCrawler($this->getDoctype().'<html><base href="'.$baseValue.'"><form method="post" action="'.$actionValue.'"><button type="submit" name="submit"></button></form></html>', $currentUri);
        $this->assertEquals($expectedUri, $crawler->filterXPath('//button')->form()->getUri(), $description);
    }

    public function testCountOfNestedElements()
    {
        $crawler = $this->createCrawler('<html><body><ul><li>List item 1<ul><li>Sublist item 1</li><li>Sublist item 2</li></ul></li></ul></body></html>');

        $this->assertCount(1, $crawler->filter('li:contains("List item 1")'));
    }
}
