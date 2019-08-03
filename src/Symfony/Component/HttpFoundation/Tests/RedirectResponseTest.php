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

        $this->assertEquals(1, preg_match(
            '#<meta http-equiv="refresh" content="\d+;url=foo\.bar" />#',
            preg_replace(['/\s+/', '/\'/'], [' ', '"'], $response->getContent())
        ));
    }

    public function testRedirectResponseConstructorNullUrl()
    {
        $this->expectException('InvalidArgumentException');
        $response = new RedirectResponse(null);
    }

    public function testRedirectResponseConstructorWrongStatusCode()
    {
        $this->expectException('InvalidArgumentException');
        $response = new RedirectResponse('foo.bar', 404);
    }

    public function testGenerateLocationHeader()
    {
        $response = new RedirectResponse('foo.bar');

        $this->assertTrue($response->headers->has('Location'));
        $this->assertEquals('foo.bar', $response->headers->get('Location'));
    }

    public function testGetTargetUrl()
    {
        $response = new RedirectResponse('foo.bar');

        $this->assertEquals('foo.bar', $response->getTargetUrl());
    }

    public function testSetTargetUrl()
    {
        $response = new RedirectResponse('foo.bar');
        $response->setTargetUrl('baz.beep');

        $this->assertEquals('baz.beep', $response->getTargetUrl());
    }

    public function testSetTargetUrlNull()
    {
        $this->expectException('InvalidArgumentException');
        $response = new RedirectResponse('foo.bar');
        $response->setTargetUrl(null);
    }

    public function testCreate()
    {
        $response = RedirectResponse::create('foo', 301);

        $this->assertInstanceOf('Symfony\Component\HttpFoundation\RedirectResponse', $response);
        $this->assertEquals(301, $response->getStatusCode());
    }

    public function testCacheHeaders()
    {
        $response = new RedirectResponse('foo.bar', 301);
        $this->assertFalse($response->headers->hasCacheControlDirective('no-cache'));

        $response = new RedirectResponse('foo.bar', 301, ['cache-control' => 'max-age=86400']);
        $this->assertFalse($response->headers->hasCacheControlDirective('no-cache'));
        $this->assertTrue($response->headers->hasCacheControlDirective('max-age'));

        $response = new RedirectResponse('foo.bar', 301, ['Cache-Control' => 'max-age=86400']);
        $this->assertFalse($response->headers->hasCacheControlDirective('no-cache'));
        $this->assertTrue($response->headers->hasCacheControlDirective('max-age'));

        $response = new RedirectResponse('foo.bar', 302);
        $this->assertTrue($response->headers->hasCacheControlDirective('no-cache'));
    }
}
