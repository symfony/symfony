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

class Html5ParserCrawlerTest extends AbstractCrawlerTest
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

    public function validHtml5Provider(): iterable
    {
        $html = static::getDoctype().'<html><body><h1><p>Foo</p></h1></body></html>';
        $BOM = \chr(0xEF).\chr(0xBB).\chr(0xBF);

        yield 'BOM first' => [$BOM.$html];
        yield 'Single comment' => ['<!-- comment -->'.$html];
        yield 'Multiline comment' => ["<!-- \n multiline comment \n -->".$html];
        yield 'Several comments' => ['<!--c--> <!--cc-->'.$html];
        yield 'Whitespaces' => ['    '.$html];
        yield 'All together' => [$BOM.'  <!--c-->'.$html];
    }

    public function invalidHtml5Provider(): iterable
    {
        $html = static::getDoctype().'<html><body><h1><p>Foo</p></h1></body></html>';

        yield 'Text' => ['hello world'.$html];
        yield 'Text between comments' => ['<!--c--> test <!--cc-->'.$html];
    }
}
