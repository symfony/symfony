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
use Symfony\Component\DomCrawler\Test\Constraint\CrawlerSelectorExists;

/**
 * Ideas borrowed from Laravel Dusk's assertions.
 *
 * @see https://laravel.com/docs/5.7/dusk#available-assertions
 */
trait DomCrawlerAssertionsTrait
{
    public static function assertSelectorExists(string $selector, string $message = ''): void
    {
        self::assertThat(self::getCrawler(), new CrawlerSelectorExists($selector), $message);
    }

    public static function assertSelectorNotExists(string $selector, string $message = ''): void
    {
        self::assertThat(self::getCrawler(), new LogicalNot(new CrawlerSelectorExists($selector)), $message);
    }

    public static function assertSelectorCount(int $expectedCount, string $selector, string $message = ''): void
    {
        self::assertThat(self::getCrawler(), new DomCrawlerConstraint\CrawlerSelectorCount($expectedCount, $selector), $message);
    }

    public static function assertSelectorTextContains(string $selector, string $text, string $message = ''): void
    {
        self::assertThat(self::getCrawler(), LogicalAnd::fromConstraints(
            new CrawlerSelectorExists($selector),
            new DomCrawlerConstraint\CrawlerSelectorTextContains($selector, $text)
        ), $message);
    }

    public static function assertAnySelectorTextContains(string $selector, string $text, string $message = ''): void
    {
        self::assertThat(self::getCrawler(), LogicalAnd::fromConstraints(
            new CrawlerSelectorExists($selector),
            new DomCrawlerConstraint\CrawlerAnySelectorTextContains($selector, $text)
        ), $message);
    }

    public static function assertSelectorTextSame(string $selector, string $text, string $message = ''): void
    {
        self::assertThat(self::getCrawler(), LogicalAnd::fromConstraints(
            new CrawlerSelectorExists($selector),
            new DomCrawlerConstraint\CrawlerSelectorTextSame($selector, $text)
        ), $message);
    }

    public static function assertAnySelectorTextSame(string $selector, string $text, string $message = ''): void
    {
        self::assertThat(self::getCrawler(), LogicalAnd::fromConstraints(
            new CrawlerSelectorExists($selector),
            new DomCrawlerConstraint\CrawlerAnySelectorTextSame($selector, $text)
        ), $message);
    }

    public static function assertSelectorTextNotContains(string $selector, string $text, string $message = ''): void
    {
        self::assertThat(self::getCrawler(), LogicalAnd::fromConstraints(
            new CrawlerSelectorExists($selector),
            new LogicalNot(new DomCrawlerConstraint\CrawlerSelectorTextContains($selector, $text))
        ), $message);
    }

    public static function assertAnySelectorTextNotContains(string $selector, string $text, string $message = ''): void
    {
        self::assertThat(self::getCrawler(), LogicalAnd::fromConstraints(
            new CrawlerSelectorExists($selector),
            new LogicalNot(new DomCrawlerConstraint\CrawlerAnySelectorTextContains($selector, $text))
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
            new CrawlerSelectorExists("input[name=\"$fieldName\"]"),
            new DomCrawlerConstraint\CrawlerSelectorAttributeValueSame("input[name=\"$fieldName\"]", 'value', $expectedValue)
        ), $message);
    }

    public static function assertInputValueNotSame(string $fieldName, string $expectedValue, string $message = ''): void
    {
        self::assertThat(self::getCrawler(), LogicalAnd::fromConstraints(
            new CrawlerSelectorExists("input[name=\"$fieldName\"]"),
            new LogicalNot(new DomCrawlerConstraint\CrawlerSelectorAttributeValueSame("input[name=\"$fieldName\"]", 'value', $expectedValue))
        ), $message);
    }

    public static function assertCheckboxChecked(string $fieldName, string $message = ''): void
    {
        self::assertThat(self::getCrawler(), new CrawlerSelectorExists("input[name=\"$fieldName\"]:checked"), $message);
    }

    public static function assertCheckboxNotChecked(string $fieldName, string $message = ''): void
    {
        self::assertThat(self::getCrawler(), new LogicalNot(new CrawlerSelectorExists("input[name=\"$fieldName\"]:checked")), $message);
    }

    public static function assertFormValue(string $formSelector, string $fieldName, string $value, string $message = ''): void
    {
        $node = self::getCrawler()->filter($formSelector);
        self::assertNotEmpty($node, sprintf('Form "%s" not found.', $formSelector));
        $values = $node->form()->getValues();
        self::assertArrayHasKey($fieldName, $values, $message ?: sprintf('Field "%s" not found in form "%s".', $fieldName, $formSelector));
        self::assertSame($value, $values[$fieldName]);
    }

    public static function assertNoFormValue(string $formSelector, string $fieldName, string $message = ''): void
    {
        $node = self::getCrawler()->filter($formSelector);
        self::assertNotEmpty($node, sprintf('Form "%s" not found.', $formSelector));
        $values = $node->form()->getValues();
        self::assertArrayNotHasKey($fieldName, $values, $message ?: sprintf('Field "%s" has a value in form "%s".', $fieldName, $formSelector));
    }

    private static function getCrawler(): Crawler
    {
        if (!$crawler = self::getClient()->getCrawler()) {
            static::fail('A client must have a crawler to make assertions. Did you forget to make an HTTP request?');
        }

        return $crawler;
    }
}
