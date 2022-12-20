<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpFoundation\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\RedirectResponse;

class RedirectResponseTest extends TestCase
{
    public function testGenerateMetaRedirect()
    {
        $response = new RedirectResponse('foo.bar');

        self::assertMatchesRegularExpression('#<meta http-equiv="refresh" content="\d+;url=\'foo\.bar\'" />#', preg_replace('/\s+/', ' ', $response->getContent()));
    }

    public function testRedirectResponseConstructorEmptyUrl()
    {
        self::expectException(\InvalidArgumentException::class);
        self::expectExceptionMessage('Cannot redirect to an empty URL.');
        new RedirectResponse('');
    }

    public function testRedirectResponseConstructorWrongStatusCode()
    {
        self::expectException(\InvalidArgumentException::class);
        new RedirectResponse('foo.bar', 404);
    }

    public function testGenerateLocationHeader()
    {
        $response = new RedirectResponse('foo.bar');

        self::assertTrue($response->headers->has('Location'));
        self::assertEquals('foo.bar', $response->headers->get('Location'));
    }

    public function testGetTargetUrl()
    {
        $response = new RedirectResponse('foo.bar');

        self::assertEquals('foo.bar', $response->getTargetUrl());
    }

    public function testSetTargetUrl()
    {
        $response = new RedirectResponse('foo.bar');
        $response->setTargetUrl('baz.beep');

        self::assertEquals('baz.beep', $response->getTargetUrl());
    }

    /**
     * @group legacy
     */
    public function testCreate()
    {
        $response = RedirectResponse::create('foo', 301);

        self::assertInstanceOf(RedirectResponse::class, $response);
        self::assertEquals(301, $response->getStatusCode());
    }

    public function testCacheHeaders()
    {
        $response = new RedirectResponse('foo.bar', 301);
        self::assertFalse($response->headers->hasCacheControlDirective('no-cache'));

        $response = new RedirectResponse('foo.bar', 301, ['cache-control' => 'max-age=86400']);
        self::assertFalse($response->headers->hasCacheControlDirective('no-cache'));
        self::assertTrue($response->headers->hasCacheControlDirective('max-age'));

        $response = new RedirectResponse('foo.bar', 301, ['Cache-Control' => 'max-age=86400']);
        self::assertFalse($response->headers->hasCacheControlDirective('no-cache'));
        self::assertTrue($response->headers->hasCacheControlDirective('max-age'));

        $response = new RedirectResponse('foo.bar', 302);
        self::assertTrue($response->headers->hasCacheControlDirective('no-cache'));
    }
}
