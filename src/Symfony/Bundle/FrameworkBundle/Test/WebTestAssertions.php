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

use PHPUnit\Framework\Assert;
use Symfony\Bundle\FrameworkBundle\Client;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Ideas borrowed from Laravel Dusk's assertions.
 *
 * @see https://laravel.com/docs/5.7/dusk#available-assertions
 */
trait WebTestAssertions
{
    /** @var Client|null */
    protected static $client;

    public static function assertResponseIsSuccessful(): void
    {
        $response = static::getResponse();

        Assert::assertTrue(
            $response->isSuccessful(),
            sprintf('Response was expected to be successful, but actual HTTP code is %d.', $response->getStatusCode())
        );
    }

    public static function assertHttpCodeEquals(int $expectedCode): void
    {
        Assert::assertSame(
            $expectedCode,
            $code = static::getResponse()->getStatusCode(),
            sprintf('Response code "%s" does not match actual HTTP code "%s".', $expectedCode, $code)
        );
    }

    public static function assertResponseHasHeader(string $headerName): void
    {
        Assert::assertTrue(
            static::getResponse()->headers->has($headerName),
            sprintf('Header "%s" was not found in the Response.', $headerName)
        );
    }

    public static function assertResponseNotHasHeader(string $headerName): void
    {
        Assert::assertFalse(
            static::getResponse()->headers->has($headerName),
            sprintf('Header "%s" was not expected to be found in the Response.', $headerName)
        );
    }

    public static function assertResponseHeaderEquals(string $headerName, $expectedValue): void
    {
        Assert::assertSame(
            $expectedValue,
            $value = static::getResponse()->headers->get($headerName, null, true),
            sprintf('Header "%s" with value "%s" does not equal actual value "%s".', $headerName, $expectedValue, $value)
        );
    }

    public static function assertResponseHeaderNotEquals(string $headerName, $expectedValue): void
    {
        Assert::assertNotSame(
            $expectedValue,
            $value = static::getResponse()->headers->get($headerName, null, true),
            sprintf('Header "%s" with value "%s" was not expected to equal actual value "%s".', $headerName, $expectedValue, $value)
        );
    }

    public static function assertResponseRedirects(string $expectedLocation = null, int $expectedCode = null): void
    {
        $response = static::getResponse();

        Assert::assertTrue(
            $response->isRedirect(),
            sprintf('Response was expected to be a redirection, but actual HTTP code is %s.', $response->getStatusCode())
        );

        if ($expectedCode) {
            static::assertHttpCodeEquals($expectedCode);
        }

        if (null !== $expectedLocation) {
            Assert::assertSame(
                $expectedLocation,
                $location = $response->headers->get('Location'),
                sprintf('Location "%s" does not match actual redirection URL "%s".', $expectedLocation, $location)
            );
        }
    }

    public static function assertPageTitleEquals(string $expectedTitle): void
    {
        $titleNode = static::getCrawler()->filter('title');

        Assert::assertSame(1, $count = $titleNode->count(), sprintf('There must be one <title> tag in the current page but there is actually %s.', $count));

        Assert::assertEquals(
            $expectedTitle,
            trim($title = $titleNode->text()),
            sprintf('Expected title "%s" does not equal actual title "%s".', $expectedTitle, $title)
        );
    }

    public static function assertPageTitleContains(string $expectedTitle): void
    {
        $titleNode = static::getCrawler()->filter('title');

        Assert::assertSame(1, $count = $titleNode->count(), sprintf('There must be one <title> tag in the current page but there is actually %s.', $count));

        Assert::assertContains(
            $expectedTitle,
            trim($title = $titleNode->text()),
            sprintf('Expected title "%s" does not contain "%s".', $expectedTitle, $title)
        );
    }

    public static function assertClientHasCookie(string $name, string $path = '/', string $domain = null): void
    {
        static::getClientForAssertion();

        Assert::assertNotNull(
            static::$client->getCookieJar()->get($name, $path, $domain),
            sprintf('Did not find expected cookie "%s".', $name)
        );
    }

    public static function assertClientNotHasCookie(string $name): void
    {
        static::getClientForAssertion();

        $cookie = static::$client->getCookieJar()->get($name);

        Assert::assertNull(
            $cookie,
            sprintf('Cookie "%s" was not expected to be set.', $name)
        );
    }

    public static function assertClientCookieValueEquals(string $name, $expectedValue, string $path = '/', string $domain = null): void
    {
        static::getClientForAssertion();

        $cookie = static::$client->getCookieJar()->get($name, $path, $domain);

        Assert::assertNotNull(
            $cookie,
            sprintf('Did not find expected cookie "%s".', $name)
        );
        Assert::assertSame(
            $expectedValue,
            $value = $cookie->getValue(),
            sprintf('Cookie name "%s" with value "%s" does not match actual value "%s".', $name, $expectedValue, $value)
        );
    }

    public static function assertClientRawCookieValueEquals(string $name, $expectedValue, string $path = '/', string $domain = null): void
    {
        static::getClientForAssertion();

        $cookie = static::$client->getCookieJar()->get($name, $path, $domain);

        Assert::assertNotNull(
            $cookie,
            sprintf('Did not find expected cookie "%s".', $name)
        );
        Assert::assertSame(
            $expectedValue,
            $value = $cookie->getRawValue(),
            sprintf('Cookie name "%s" with raw value "%s" does not match actual value "%s".', $name, $expectedValue, $value)
        );
    }

    public static function assertResponseHasCookie(string $name, string $path = '/', string $domain = null): void
    {
        $cookie = static::getResponseCookieFromClient($name, $path, $domain);

        Assert::assertNotNull(
            $cookie,
            sprintf('Did not find expected cookie "%s".', $name)
        );
    }

    public static function assertResponseNotHasCookie(string $name, string $path = '/', string $domain = null): void
    {
        $cookie = static::getResponseCookieFromClient($name, $path, $domain);

        Assert::assertNull(
            $cookie,
            sprintf('Cookie "%s" was not expected to be set.', $name)
        );
    }

    public static function assertResponseCookieValueEquals(string $name, $expectedValue, string $path = '/', string $domain = null): void
    {
        $cookie = static::getResponseCookieFromClient($name, $path, $domain);

        Assert::assertNotNull(
            $cookie,
            sprintf('Did not find expected cookie "%s".', $name)
        );
        Assert::assertSame(
            $expectedValue,
            $value = $cookie->getValue(),
            sprintf('Cookie name "%s" with value "%s" does not match actual value "%s".', $name, $expectedValue, $value)
        );
    }

    public static function assertResponseCookieValueNotEquals(string $name, $expectedValue, string $path = '/', string $domain = null): void
    {
        $cookie = static::getResponseCookieFromClient($name, $path, $domain);

        Assert::assertNotNull(
            $cookie,
            sprintf('Did not find expected cookie "%s".', $name)
        );
        Assert::assertNotSame(
            $expectedValue,
            $value = $cookie->getValue(),
            sprintf('Cookie name "%s" with value "%s" was not expected to be equal to actual value "%s".', $name, $expectedValue, $value)
        );
    }

    public static function assertSelectorExists(string $selector): void
    {
        $nodes = static::getCrawler()->filter($selector);

        Assert::assertGreaterThan(0, $nodes->count(), sprintf('Selector "%s" does not resolve to any node.', $selector));
    }

    public static function assertSelectorNotExists(string $selector): void
    {
        $nodes = static::getCrawler()->filter($selector);

        Assert::assertEquals(0, $count = $nodes->count(), sprintf('Selector "%s" resolves to "%s" nodes where it expected 0.', $selector, $count));
    }

    public static function assertSelectorContainsText(string $selector, string $text): void
    {
        $nodes = static::getCrawler()->filter($selector);

        Assert::assertGreaterThan(0, $nodes->count(), sprintf('Selector "%s" does not resolve to any node.', $selector));

        Assert::assertContains($text, $nodes->text(), sprintf('Selector "%s" does not contain text "%s".', $selector, $text));
    }

    public static function assertSelectorNotContainsText(string $selector, string $text): void
    {
        $nodes = static::getCrawler()->filter($selector);

        Assert::assertGreaterThan(0, $nodes->count(), sprintf('Selector "%s" does not resolve to any node.', $selector));

        Assert::assertNotContains($text, $nodes->text(), sprintf('Selector "%s" was expected to not contain text "%s".', $selector, $text));
    }

    public static function assertInputValueEquals(string $fieldName, string $expectedValue): void
    {
        $inputNode = static::getCrawler()->filter("input[name=\"$fieldName\"]");

        Assert::assertGreaterThan(0, $inputNode->count(), sprintf('Input with name "%s" not found on the page.', $fieldName));

        Assert::assertEquals(
            $expectedValue,
            $value = $inputNode->getNode(0)->getAttribute('value'),
            sprintf('Expected value "%s" for the "%s" input does not equal the actual value "%s".', $value, $fieldName, $value)
        );
    }

    public static function assertInputValueNotEquals(string $fieldName, string $expectedValue): void
    {
        $inputNode = static::getCrawler()->filter("input[name=\"$fieldName\"]");

        Assert::assertGreaterThan(0, $inputNode->count(), sprintf('Input with name "%s" not found on the page.', $fieldName));

        Assert::assertNotEquals(
            $expectedValue,
            $value = $inputNode->getNode(0)->getAttribute('value'),
            sprintf('Expected value "%s" for the "%s" input was expected to not equal the actual value "%s".', $value, $fieldName, $value)
        );
    }

    public static function assertRouteEquals($expectedRoute, array $parameters = []): void
    {
        $request = static::checkRequestAvailable();

        Assert::assertSame(
            $expectedRoute,
            $route = $request->attributes->get('_route'),
            sprintf('Expected route name "%s" does not match the actual value "%s".', $expectedRoute, $route)
        );

        if (\count($parameters)) {
            foreach ($parameters as $key => $expectedValue) {
                static::assertRequestAttributeValueEquals($key, $expectedValue);
            }
        }
    }

    public static function assertRequestAttributeValueEquals(string $key, $expectedValue): void
    {
        $request = static::checkRequestAvailable();

        Assert::assertSame(
            $expectedValue,
            $value = $request->attributes->get($key),
            sprintf('Expected request attribute "%s" value "%s" does not match actual value "%s".', $key, $expectedValue, $value)
        );
    }

    protected static function getResponseCookieFromClient(string $name, string $path = '/', string $domain = null): ?Cookie
    {
        $cookies = static::getResponse()->headers->getCookies();

        $filteredCookies = array_filter($cookies, function (Cookie $cookie) use ($name, $path, $domain) {
            return
                $cookie->getName() === $name
                && $cookie->getPath() === $path
                && $cookie->getDomain() === $domain
            ;
        });

        return reset($filteredCookies) ?: null;
    }

    private static function getClientForAssertion(): Client
    {
        if (!static::$client instanceof Client) {
            static::fail(\sprintf(
                'A client must be set to make assertions on it. Did you forget to call "%s::createClient"?',
                static::class
            ));
        }

        return static::$client;
    }

    private static function getCrawler(): Crawler
    {
        $client = static::getClientForAssertion();

        if (!$client->getCrawler()) {
            static::fail('A client must have a crawler to make assertions. Did you forget to make an HTTP request?');
        }

        return $client->getCrawler();
    }

    private static function getResponse(): Response
    {
        $client = static::getClientForAssertion();

        if (!$client->getResponse()) {
            static::fail('A client must have an HTTP Response to make assertions. Did you forget to make an HTTP request?');
        }

        return $client->getResponse();
    }

    private static function checkRequestAvailable(): Request
    {
        $client = static::getClientForAssertion();

        if (!$client->getRequest()) {
            static::fail('A client must have an HTTP Request to make assertions. Did you forget to make an HTTP request?');
        }

        return $client->getRequest();
    }
}
