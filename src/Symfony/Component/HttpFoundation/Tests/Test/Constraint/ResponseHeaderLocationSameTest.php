<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpFoundation\Tests\Test\Constraint;

use PHPUnit\Framework\ExpectationFailedException;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Test\Constraint\ResponseHeaderLocationSame;

class ResponseHeaderLocationSameTest extends TestCase
{
    /**
     * @dataProvider provideSuccessCases
     */
    public function testConstraintSuccess(string $requestUrl, ?string $location, string $expectedLocation)
    {
        $request = Request::create($requestUrl);

        $response = new Response();
        if (null !== $location) {
            $response->headers->set('Location', $location);
        }

        $constraint = new ResponseHeaderLocationSame($request, $expectedLocation);

        self::assertTrue($constraint->evaluate($response, '', true));
    }

    public static function provideSuccessCases(): iterable
    {
        yield ['http://example.com', 'http://example.com', 'http://example.com'];
        yield ['http://example.com', 'http://example.com', '//example.com'];
        yield ['http://example.com', 'http://example.com', '/'];
        yield ['http://example.com', '//example.com', 'http://example.com'];
        yield ['http://example.com', '//example.com', '//example.com'];
        yield ['http://example.com', '//example.com', '/'];
        yield ['http://example.com', '/', 'http://example.com'];
        yield ['http://example.com', '/', '//example.com'];
        yield ['http://example.com', '/', '/'];

        yield ['http://example.com/', 'http://example.com/', 'http://example.com/'];
        yield ['http://example.com/', 'http://example.com/', '//example.com/'];
        yield ['http://example.com/', 'http://example.com/', '/'];
        yield ['http://example.com/', '//example.com/', 'http://example.com/'];
        yield ['http://example.com/', '//example.com/', '//example.com/'];
        yield ['http://example.com/', '//example.com/', '/'];
        yield ['http://example.com/', '/', 'http://example.com/'];
        yield ['http://example.com/', '/', '//example.com/'];
        yield ['http://example.com/', '/', '/'];

        yield ['http://example.com/foo', 'http://example.com/', 'http://example.com/'];
        yield ['http://example.com/foo', 'http://example.com/', '//example.com/'];
        yield ['http://example.com/foo', 'http://example.com/', '/'];
        yield ['http://example.com/foo', '//example.com/', 'http://example.com/'];
        yield ['http://example.com/foo', '//example.com/', '//example.com/'];
        yield ['http://example.com/foo', '//example.com/', '/'];
        yield ['http://example.com/foo', '/', 'http://example.com/'];
        yield ['http://example.com/foo', '/', '//example.com/'];
        yield ['http://example.com/foo', '/', '/'];

        yield ['http://example.com/foo', 'http://example.com/bar', 'http://example.com/bar'];
        yield ['http://example.com/foo', 'http://example.com/bar', '//example.com/bar'];
        yield ['http://example.com/foo', 'http://example.com/bar', '/bar'];
        yield ['http://example.com/foo', '//example.com/bar', 'http://example.com/bar'];
        yield ['http://example.com/foo', '//example.com/bar', '//example.com/bar'];
        yield ['http://example.com/foo', '//example.com/bar', '/bar'];
        yield ['http://example.com/foo', '/bar', 'http://example.com/bar'];
        yield ['http://example.com/foo', '/bar', '//example.com/bar'];
        yield ['http://example.com/foo', '/bar', '/bar'];

        yield ['http://example.com', 'http://example.com/bar', 'http://example.com/bar'];
        yield ['http://example.com', 'http://example.com/bar', '//example.com/bar'];
        yield ['http://example.com', 'http://example.com/bar', '/bar'];
        yield ['http://example.com', '//example.com/bar', 'http://example.com/bar'];
        yield ['http://example.com', '//example.com/bar', '//example.com/bar'];
        yield ['http://example.com', '//example.com/bar', '/bar'];
        yield ['http://example.com', '/bar', 'http://example.com/bar'];
        yield ['http://example.com', '/bar', '//example.com/bar'];
        yield ['http://example.com', '/bar', '/bar'];

        yield ['http://example.com/', 'http://another-example.com', 'http://another-example.com'];
    }

    /**
     * @dataProvider provideFailureCases
     */
    public function testConstraintFailure(string $requestUrl, ?string $location, string $expectedLocation)
    {
        $request = Request::create($requestUrl);

        $response = new Response();
        if (null !== $location) {
            $response->headers->set('Location', $location);
        }

        $constraint = new ResponseHeaderLocationSame($request, $expectedLocation);

        self::assertFalse($constraint->evaluate($response, '', true));

        $this->expectException(ExpectationFailedException::class);

        $constraint->evaluate($response);
    }

    public static function provideFailureCases(): iterable
    {
        yield ['http://example.com', null, 'http://example.com'];
        yield ['http://example.com', null, '//example.com'];
        yield ['http://example.com', null, '/'];

        yield ['http://example.com', 'http://another-example.com', 'http://example.com'];
        yield ['http://example.com', 'http://another-example.com', '//example.com'];
        yield ['http://example.com', 'http://another-example.com', '/'];

        yield ['http://example.com', 'http://example.com/bar', 'http://example.com'];
        yield ['http://example.com', 'http://example.com/bar', '//example.com'];
        yield ['http://example.com', 'http://example.com/bar', '/'];

        yield ['http://example.com/foo', 'http://example.com/bar', 'http://example.com'];
        yield ['http://example.com/foo', 'http://example.com/bar', '//example.com'];
        yield ['http://example.com/foo', 'http://example.com/bar', '/'];

        yield ['http://example.com/foo', 'http://example.com/bar', 'http://example.com/foo'];
        yield ['http://example.com/foo', 'http://example.com/bar', '//example.com/foo'];
        yield ['http://example.com/foo', 'http://example.com/bar', '/foo'];
    }
}
