<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Test;

use PHPUnit\Framework\Constraint\LogicalAnd;
use PHPUnit\Framework\Constraint\LogicalNot;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\DomCrawler\Test\Constraint as DomCrawlerConstraint;

/**
 * Ideas borrowed from Laravel Dusk's assertions.
 *
 * @see https://laravel.com/docs/5.7/dusk#available-assertions
 */
trait DomCrawlerAssertionsTrait
{
    public static function assertSelectorExists(string $selector, string $message = ''): void
    {
        self::assertThat(self::getCrawler(), new DomCrawlerConstraint\CrawlerSelectorExists($selector), $message);
    }

    public static function assertSelectorNotExists(string $selector, string $message = ''): void
    {
        self::assertThat(self::getCrawler(), new LogicalNot(new DomCrawlerConstraint\CrawlerSelectorExists($selector)), $message);
    }

    public static function assertSelectorTextContains(string $selector, string $text, string $message = ''): void
    {
        self::assertThat(self::getCrawler(), LogicalAnd::fromConstraints(
            new DomCrawlerConstraint\CrawlerSelectorExists($selector),
            new DomCrawlerConstraint\CrawlerSelectorTextContains($selector, $text)
        ), $message);
    }

    public static function assertSelectorTextSame(string $selector, string $text, string $message = ''): void
    {
        self::assertThat(self::getCrawler(), LogicalAnd::fromConstraints(
            new DomCrawlerConstraint\CrawlerSelectorExists($selector),
            new DomCrawlerConstraint\CrawlerSelectorTextSame($selector, $text)
        ), $message);
    }

    public static function assertSelectorTextNotContains(string $selector, string $text, string $message = ''): void
    {
        self::assertThat(self::getCrawler(), LogicalAnd::fromConstraints(
            new DomCrawlerConstraint\CrawlerSelectorExists($selector),
            new LogicalNot(new DomCrawlerConstraint\CrawlerSelectorTextContains($selector, $text))
        ), $message);
    }

    public static function assertPageTitleSame(string $expectedTitle, string $message = ''): void
    {
        self::assertSelectorTextSame('title', $expectedTitle, $message);
    }

    public static function assertPageTitleContains(string $expectedTitle, string $message = ''): void
    {
        self::assertSelectorTextContains('title', $expectedTitle, $message);
    }

    public static function assertInputValueSame(string $fieldName, string $expectedValue, string $message = ''): void
    {
        self::assertThat(self::getCrawler(), LogicalAnd::fromConstraints(
            new DomCrawlerConstraint\CrawlerSelectorExists("input[name=\"$fieldName\"]"),
            new DomCrawlerConstraint\CrawlerSelectorAttributeValueSame("input[name=\"$fieldName\"]", 'value', $expectedValue)
        ), $message);
    }

    public static function assertInputValueNotSame(string $fieldName, string $expectedValue, string $message = ''): void
    {
        self::assertThat(self::getCrawler(), LogicalAnd::fromConstraints(
            new DomCrawlerConstraint\CrawlerSelectorExists("input[name=\"$fieldName\"]"),
            new LogicalNot(new DomCrawlerConstraint\CrawlerSelectorAttributeValueSame("input[name=\"$fieldName\"]", 'value', $expectedValue))
        ), $message);
    }

    private static function getCrawler(): Crawler
    {
        if (!$crawler = self::getClient()->getCrawler()) {
            static::fail('A client must have a crawler to make assertions. Did you forget to make an HTTP request?');
        }

        return $crawler;
    }
}
