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

class Html5ParserCrawlerTest extends AbstractCrawlerTestCase
{
    public static function getDoctype(): string
    {
        return '<!DOCTYPE html>';
    }

    public function testAddHtml5()
    {
        // Ensure a bug specific to the DOM extension is fixed (see https://github.com/symfony/symfony/issues/28596)
        $crawler = $this->createCrawler();
        $crawler->add($this->getDoctype().'<html><body><h1><p>Foo</p></h1></body></html>');
        $this->assertEquals('Foo', $crawler->filterXPath('//h1')->text(), '->add() adds nodes from a string');
    }

    /** @dataProvider validHtml5Provider */
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

    /** @dataProvider invalidHtml5Provider */
    public function testHtml5ParserWithInvalidHeadedContent(string $content)
    {
        $crawler = $this->createCrawler();
        $crawler->addHtmlContent($content);
        self::assertEmpty($crawler->filterXPath('//h1')->text(), '->addHtmlContent failed as expected');
    }

    public function testHtml5ParserNotSameAsNativeParserForSpecificHtml()
    {
        // Html who create a bug specific to the DOM extension (see https://github.com/symfony/symfony/issues/28596)
        $html = $this->getDoctype().'<html><body><h1><p>Foo</p></h1></body></html>';

        $html5Crawler = $this->createCrawler(null, null, null, true);
        $html5Crawler->add($html);

        $nativeCrawler = $this->createCrawler(null, null, null, false);
        $nativeCrawler->add($html);

        $this->assertNotEquals($nativeCrawler->filterXPath('//h1')->text(), $html5Crawler->filterXPath('//h1')->text(), 'Native parser and Html5 parser must be different');
    }

    /**
     * @testWith [true]
     *           [false]
     */
    public function testHasHtml5Parser(bool $useHtml5Parser)
    {
        $crawler = $this->createCrawler(null, null, null, $useHtml5Parser);

        $r = new \ReflectionProperty($crawler::class, 'html5Parser');
        $html5Parser = $r->getValue($crawler);

        if ($useHtml5Parser) {
            $this->assertInstanceOf(\Masterminds\HTML5::class, $html5Parser, 'Html5Parser must be a Masterminds\HTML5 instance');
        } else {
            $this->assertNull($html5Parser, 'Html5Parser must be null');
        }
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

    public static function invalidHtml5Provider(): iterable
    {
        $html = self::getDoctype().'<html><body><h1><p>Foo</p></h1></body></html>';

        yield 'Text' => ['hello world'.$html];
        yield 'Text between comments' => ['<!--c--> test <!--cc-->'.$html];
    }
}
