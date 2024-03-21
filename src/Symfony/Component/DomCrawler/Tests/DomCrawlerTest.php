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

class DomCrawlerTest extends AbstractCrawlerTestCase
{
    public static function getDoctype(): string
    {
        return '<!DOCTYPE html>';
    }

    protected function createCrawler($node = null, ?string $uri = null, ?string $baseHref = null, bool $useHtml5Parser = false)
    {
        return new DomCrawler($node, $uri, $baseHref, DomCrawler::CRAWLER_ENABLE_HTML5_PARSING | DomCrawler::CRAWLER_USE_EXTERNAL_PARSER);
    }

    protected static function getCrawlerClass(): string
    {
        return DomCrawler::class;
    }

    /**
     * @requires PHP < 8.4
     */
    public function testOptionsNativeCrawlerBeforePHP84()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Native parser requires PHP 8.4 or higher.');

        new DomCrawler(options: DomCrawler::CRAWLER_USE_NATIVE_PARSER);
    }

    /**
     * @requires PHP 8.4
     */
    public function testOptionsUseBothNativeAndExternalParser()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('You cannot use both external and native parsers at the same time.');

        new DomCrawler(options: DomCrawler::CRAWLER_USE_NATIVE_PARSER | DomCrawler::CRAWLER_USE_EXTERNAL_PARSER);
    }

    /**
     * @requires PHP 8.4
     */
    public function testOptionsEnableHtml5ParsingWithoutParser()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('You must either choose the external or the native parser when enable HTML 5 parsing.');

        new DomCrawler(options: DomCrawler::CRAWLER_ENABLE_HTML5_PARSING);
    }

    /**
     * @requires PHP 8.4
     */
    public function testConstructorWithModernNode()
    {
        $crawler = $this->createCrawler();
        $this->assertCount(0, $crawler, '__construct() returns an empty crawler');

        $doc = \DOM\HTMLDocument::createEmpty();
        $node = $doc->createElement('test');

        $crawler = $this->createCrawler($node);
        $this->assertCount(1, $crawler, '__construct() takes a node as a first argument');
    }

    /**
     * @requires PHP 8.4
     */
    public function testClearWithModerNode()
    {
        $doc = \DOM\HTMLDocument::createEmpty();
        $node = $doc->createElement('test');

        $crawler = $this->createCrawler($node);
        $crawler->clear();
        $this->assertCount(0, $crawler, '->clear() removes all the nodes from the crawler');
    }
}
