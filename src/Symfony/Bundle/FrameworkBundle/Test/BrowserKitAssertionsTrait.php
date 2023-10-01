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

use PHPUnit\Framework\Constraint\Constraint;
use PHPUnit\Framework\Constraint\LogicalAnd;
use PHPUnit\Framework\Constraint\LogicalNot;
use PHPUnit\Framework\ExpectationFailedException;
use Symfony\Component\BrowserKit\AbstractBrowser;
use Symfony\Component\BrowserKit\Test\Constraint as BrowserKitConstraint;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Test\Constraint as ResponseConstraint;

/**
 * Ideas borrowed from Laravel Dusk's assertions.
 *
 * @see https://laravel.com/docs/5.7/dusk#available-assertions
 */
trait BrowserKitAssertionsTrait
{
    public static function assertResponseIsSuccessful(string $message = ''): void
    {
        self::assertThatForResponse(new ResponseConstraint\ResponseIsSuccessful(), $message);
    }

    public static function assertResponseStatusCodeSame(int $expectedCode, string $message = ''): void
    {
        self::assertThatForResponse(new ResponseConstraint\ResponseStatusCodeSame($expectedCode), $message);
    }

    public static function assertResponseFormatSame(?string $expectedFormat, string $message = ''): void
    {
        self::assertThatForResponse(new ResponseConstraint\ResponseFormatSame(self::getRequest(), $expectedFormat), $message);
    }

    public static function assertResponseRedirects(string $expectedLocation = null, int $expectedCode = null, string $message = ''): void
    {
        $constraint = new ResponseConstraint\ResponseIsRedirected();
        if ($expectedLocation) {
            if (class_exists(ResponseConstraint\ResponseHeaderLocationSame::class)) {
                $locationConstraint = new ResponseConstraint\ResponseHeaderLocationSame(self::getRequest(), $expectedLocation);
            } else {
                $locationConstraint = new ResponseConstraint\ResponseHeaderSame('Location', $expectedLocation);
            }

            $constraint = LogicalAnd::fromConstraints($constraint, $locationConstraint);
        }
        if ($expectedCode) {
            $constraint = LogicalAnd::fromConstraints($constraint, new ResponseConstraint\ResponseStatusCodeSame($expectedCode));
        }

        self::assertThatForResponse($constraint, $message);
    }

    public static function assertResponseHasHeader(string $headerName, string $message = ''): void
    {
        self::assertThatForResponse(new ResponseConstraint\ResponseHasHeader($headerName), $message);
    }

    public static function assertResponseNotHasHeader(string $headerName, string $message = ''): void
    {
        self::assertThatForResponse(new LogicalNot(new ResponseConstraint\ResponseHasHeader($headerName)), $message);
    }

    public static function assertResponseHeaderSame(string $headerName, string $expectedValue, string $message = ''): void
    {
        self::assertThatForResponse(new ResponseConstraint\ResponseHeaderSame($headerName, $expectedValue), $message);
    }

    public static function assertResponseHeaderNotSame(string $headerName, string $expectedValue, string $message = ''): void
    {
        self::assertThatForResponse(new LogicalNot(new ResponseConstraint\ResponseHeaderSame($headerName, $expectedValue)), $message);
    }

    public static function assertResponseHasCookie(string $name, string $path = '/', string $domain = null, string $message = ''): void
    {
        self::assertThatForResponse(new ResponseConstraint\ResponseHasCookie($name, $path, $domain), $message);
    }

    public static function assertResponseNotHasCookie(string $name, string $path = '/', string $domain = null, string $message = ''): void
    {
        self::assertThatForResponse(new LogicalNot(new ResponseConstraint\ResponseHasCookie($name, $path, $domain)), $message);
    }

    public static function assertResponseCookieValueSame(string $name, string $expectedValue, string $path = '/', string $domain = null, string $message = ''): void
    {
        self::assertThatForResponse(LogicalAnd::fromConstraints(
            new ResponseConstraint\ResponseHasCookie($name, $path, $domain),
            new ResponseConstraint\ResponseCookieValueSame($name, $expectedValue, $path, $domain)
        ), $message);
    }

    public static function assertResponseIsUnprocessable(string $message = ''): void
    {
        self::assertThatForResponse(new ResponseConstraint\ResponseIsUnprocessable(), $message);
    }

    public static function assertBrowserHasCookie(string $name, string $path = '/', string $domain = null, string $message = ''): void
    {
        self::assertThatForClient(new BrowserKitConstraint\BrowserHasCookie($name, $path, $domain), $message);
    }

    public static function assertBrowserNotHasCookie(string $name, string $path = '/', string $domain = null, string $message = ''): void
    {
        self::assertThatForClient(new LogicalNot(new BrowserKitConstraint\BrowserHasCookie($name, $path, $domain)), $message);
    }

    public static function assertBrowserCookieValueSame(string $name, string $expectedValue, bool $raw = false, string $path = '/', string $domain = null, string $message = ''): void
    {
        self::assertThatForClient(LogicalAnd::fromConstraints(
            new BrowserKitConstraint\BrowserHasCookie($name, $path, $domain),
            new BrowserKitConstraint\BrowserCookieValueSame($name, $expectedValue, $raw, $path, $domain)
        ), $message);
    }

    public static function assertRequestAttributeValueSame(string $name, string $expectedValue, string $message = ''): void
    {
        self::assertThat(self::getRequest(), new ResponseConstraint\RequestAttributeValueSame($name, $expectedValue), $message);
    }

    public static function assertRouteSame(string $expectedRoute, array $parameters = [], string $message = ''): void
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

    public static function assertThatForResponse(Constraint $constraint, string $message = ''): void
    {
        try {
            self::assertThat(self::getResponse(), $constraint, $message);
        } catch (ExpectationFailedException $exception) {
            if (($serverExceptionMessage = self::getResponse()->headers->get('X-Debug-Exception'))
                && ($serverExceptionFile = self::getResponse()->headers->get('X-Debug-Exception-File'))) {
                $serverExceptionFile = explode(':', $serverExceptionFile);
                $exception->__construct($exception->getMessage(), $exception->getComparisonFailure(), new \ErrorException(rawurldecode($serverExceptionMessage), 0, 1, rawurldecode($serverExceptionFile[0]), $serverExceptionFile[1]), $exception->getPrevious());
            }

            throw $exception;
        }
    }

    public static function assertThatForClient(Constraint $constraint, string $message = ''): void
    {
        self::assertThat(self::getClient(), $constraint, $message);
    }

    protected static function getClient(AbstractBrowser $newClient = null): ?AbstractBrowser
    {
        static $client;

        if (0 < \func_num_args()) {
            return $client = $newClient;
        }

        if (!$client instanceof AbstractBrowser) {
            static::fail(sprintf('A client must be set to make assertions on it. Did you forget to call "%s::createClient()"?', __CLASS__));
        }

        return $client;
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
