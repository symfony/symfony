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

use PHPUnit\Framework\Attributes\RequiresPhp;

#[RequiresPhp('<8.4')]
class LegacyParserCrawlerTest extends CrawlerTest
{
    public static function getDoctype(): string
    {
        return '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">';
    }

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

    public function testAddHtmlContentWithErrors()
    {
        $internalErrors = libxml_use_internal_errors(true);

        $crawler = $this->createCrawler();
        $crawler->addHtmlContent(<<<'EOF'
            <!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
            <html>
                <head>
                </head>
                <body>
                    <div><a href="#"></div>
                </body>
                </body>
            </html>
            EOF,
            'UTF-8'
        );

        $errors = libxml_get_errors();
        $this->assertCount(1, $errors);
        $this->assertEquals("Unexpected end tag : body\n", $errors[0]->message);

        libxml_clear_errors();
        libxml_use_internal_errors($internalErrors);
    }

    #[RequiresPhp('>=8.4')]
    public function testAddHtml5()
    {
    }

    #[RequiresPhp('>=8.4')]
    public function testHtml5ParserParseContentStartingWithValidHeading(string $content)
    {
    }

    public function testHtml5MalformedContent()
    {
        $crawler = $this->createCrawler();
        $crawler->addHtmlContent('<script&>');
        self::assertEquals('<head><script></script></head>', $crawler->html());
    }
}
