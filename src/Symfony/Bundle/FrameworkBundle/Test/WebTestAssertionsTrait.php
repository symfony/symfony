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
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\BrowserKit\Test\Constraint as BrowserKitConstraint;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\DomCrawler\Test\Constraint as DomCrawlerConstraint;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Test\Constraint as ResponseConstraint;

/**
 * Ideas borrowed from Laravel Dusk's assertions.
 *
 * @see https://laravel.com/docs/5.7/dusk#available-assertions
 */
trait WebTestAssertionsTrait
{
    public static function assertResponseIsSuccessful(string $message = ''): void
    {
        self::assertThat(self::getResponse(), new ResponseConstraint\ResponseIsSuccessful(), $message);
    }

    public static function assertResponseStatusCodeSame(int $expectedCode, string $message = ''): void
    {
        self::assertThat(self::getResponse(), new ResponseConstraint\ResponseStatusCodeSame($expectedCode), $message);
    }

    public static function assertResponseRedirects(string $expectedLocation = null, int $expectedCode = null, string $message = ''): void
    {
        $constraint = new ResponseConstraint\ResponseIsRedirected();
        if ($expectedLocation) {
            $constraint = LogicalAnd::fromConstraints($constraint, new ResponseConstraint\ResponseHeaderSame('Location', $expectedLocation));
        }
        if ($expectedCode) {
            $constraint = LogicalAnd::fromConstraints($constraint, new ResponseConstraint\ResponseStatusCodeSame($expectedCode));
        }

        self::assertThat(self::getResponse(), $constraint, $message);
    }

    public static function assertResponseHasHeader(string $headerName, string $message = ''): void
    {
        self::assertThat(self::getResponse(), new ResponseConstraint\ResponseHasHeader($headerName), $message);
    }

    public static function assertResponseNotHasHeader(string $headerName, string $message = ''): void
    {
        self::assertThat(self::getResponse(), new LogicalNot(new ResponseConstraint\ResponseHasHeader($headerName)), $message);
    }

    public static function assertResponseHeaderSame(string $headerName, string $expectedValue, string $message = ''): void
    {
        self::assertThat(self::getResponse(), new ResponseConstraint\ResponseHeaderSame($headerName, $expectedValue), $message);
    }

    public static function assertResponseHeaderNotSame(string $headerName, string $expectedValue, string $message = ''): void
    {
        self::assertThat(self::getResponse(), new LogicalNot(new ResponseConstraint\ResponseHeaderSame($headerName, $expectedValue)), $message);
    }

    public static function assertResponseHasCookie(string $name, string $path = '/', string $domain = null, string $message = ''): void
    {
        self::assertThat(self::getResponse(), new ResponseConstraint\ResponseHasCookie($name, $path, $domain), $message);
    }

    public static function assertResponseNotHasCookie(string $name, string $path = '/', string $domain = null, string $message = ''): void
    {
        self::assertThat(self::getResponse(), new LogicalNot(new ResponseConstraint\ResponseHasCookie($name, $path, $domain)), $message);
    }

    public static function assertResponseCookieValueSame(string $name, string $expectedValue, string $path = '/', string $domain = null, string $message = ''): void
    {
        self::assertThat(self::getResponse(), LogicalAnd::fromConstraints(
            new ResponseConstraint\ResponseHasCookie($name, $path, $domain),
            new ResponseConstraint\ResponseCookieValueSame($name, $expectedValue, $path, $domain)
        ), $message);
    }

    public static function assertBrowserHasCookie(string $name, string $path = '/', string $domain = null, string $message = ''): void
    {
        self::assertThat(self::getClient(), new BrowserKitConstraint\BrowserHasCookie($name, $path, $domain), $message);
    }

    public static function assertBrowserNotHasCookie(string $name, string $path = '/', string $domain = null, string $message = ''): void
    {
        self::assertThat(self::getClient(), new LogicalNot(new BrowserKitConstraint\BrowserHasCookie($name, $path, $domain)), $message);
    }

    public static function assertBrowserCookieValueSame(string $name, string $expectedValue, bool $raw = false, string $path = '/', string $domain = null, string $message = ''): void
    {
        self::assertThat(self::getClient(), LogicalAnd::fromConstraints(
            new BrowserKitConstraint\BrowserHasCookie($name, $path, $domain),
            new BrowserKitConstraint\BrowserCookieValueSame($name, $expectedValue, $raw, $path, $domain)
        ), $message);
    }

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

    public static function assertRequestAttributeValueSame(string $name, string $expectedValue, string $message = ''): void
    {
        self::assertThat(self::getRequest(), new ResponseConstraint\RequestAttributeValueSame($name, $expectedValue), $message);
    }

    public static function assertRouteSame($expectedRoute, array $parameters = [], string $message = ''): void
    {
        $constraint = new ResponseConstraint\RequestAttributeValueSame('_route', $expectedRoute);
        $constraints = [];
        foreach ($parameters as $key => $value) {
            $constraints[] = new ResponseConstraint\RequestAttributeValueSame($key, $value);
        }
        if ($constraints) {
            $constraint = LogicalAnd::fromConstraints($constraint, ...$constraints);
        }

        self::assertThat(self::getRequest(), $constraint, $message);
    }

    private static function getClient(KernelBrowser $newClient = null): ?KernelBrowser
    {
        static $client;

        if (0 < \func_num_args()) {
            return $client = $newClient;
        }

        if (!$client instanceof KernelBrowser) {
            static::fail(sprintf('A client must be set to make assertions on it. Did you forget to call "%s::createClient()"?', __CLASS__));
        }

        return $client;
    }

    private static function getCrawler(): Crawler
    {
        if (!$crawler = self::getClient()->getCrawler()) {
            static::fail('A client must have a crawler to make assertions. Did you forget to make an HTTP request?');
        }

        return $crawler;
    }

    private static function getResponse(): Response
    {
        if (!$response = self::getClient()->getResponse()) {
            static::fail('A client must have an HTTP Response to make assertions. Did you forget to make an HTTP request?');
        }

        return $response;
    }

    private static function getRequest(): Request
    {
        if (!$request = self::getClient()->getRequest()) {
            static::fail('A client must have an HTTP Request to make assertions. Did you forget to make an HTTP request?');
        }

        return $request;
    }
}
