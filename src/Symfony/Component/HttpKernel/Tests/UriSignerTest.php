<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\UriSigner;

class UriSignerTest extends TestCase
{
    public function testSign()
    {
        $signer = new UriSigner('foobar');

        self::assertStringContainsString('?_hash=', $signer->sign('http://example.com/foo'));
        self::assertStringContainsString('?_hash=', $signer->sign('http://example.com/foo?foo=bar'));
        self::assertStringContainsString('&foo=', $signer->sign('http://example.com/foo?foo=bar'));
    }

    public function testCheck()
    {
        $signer = new UriSigner('foobar');

        self::assertFalse($signer->check('http://example.com/foo?_hash=foo'));
        self::assertFalse($signer->check('http://example.com/foo?foo=bar&_hash=foo'));
        self::assertFalse($signer->check('http://example.com/foo?foo=bar&_hash=foo&bar=foo'));

        self::assertTrue($signer->check($signer->sign('http://example.com/foo')));
        self::assertTrue($signer->check($signer->sign('http://example.com/foo?foo=bar')));
        self::assertTrue($signer->check($signer->sign('http://example.com/foo?foo=bar&0=integer')));

        self::assertSame($signer->sign('http://example.com/foo?foo=bar&bar=foo'), $signer->sign('http://example.com/foo?bar=foo&foo=bar'));
    }

    public function testCheckWithDifferentArgSeparator()
    {
        self::iniSet('arg_separator.output', '&amp;');
        $signer = new UriSigner('foobar');

        self::assertSame('http://example.com/foo?_hash=rIOcC%2FF3DoEGo%2FvnESjSp7uU9zA9S%2F%2BOLhxgMexoPUM%3D&baz=bay&foo=bar', $signer->sign('http://example.com/foo?foo=bar&baz=bay'));
        self::assertTrue($signer->check($signer->sign('http://example.com/foo?foo=bar&baz=bay')));
    }

    public function testCheckWithRequest()
    {
        $signer = new UriSigner('foobar');

        self::assertTrue($signer->checkRequest(Request::create($signer->sign('http://example.com/foo'))));
        self::assertTrue($signer->checkRequest(Request::create($signer->sign('http://example.com/foo?foo=bar'))));
        self::assertTrue($signer->checkRequest(Request::create($signer->sign('http://example.com/foo?foo=bar&0=integer'))));
    }

    public function testCheckWithDifferentParameter()
    {
        $signer = new UriSigner('foobar', 'qux');

        self::assertSame('http://example.com/foo?baz=bay&foo=bar&qux=rIOcC%2FF3DoEGo%2FvnESjSp7uU9zA9S%2F%2BOLhxgMexoPUM%3D', $signer->sign('http://example.com/foo?foo=bar&baz=bay'));
        self::assertTrue($signer->check($signer->sign('http://example.com/foo?foo=bar&baz=bay')));
    }

    public function testSignerWorksWithFragments()
    {
        $signer = new UriSigner('foobar');

        self::assertSame('http://example.com/foo?_hash=EhpAUyEobiM3QTrKxoLOtQq5IsWyWedoXDPqIjzNj5o%3D&bar=foo&foo=bar#foobar', $signer->sign('http://example.com/foo?bar=foo&foo=bar#foobar'));
        self::assertTrue($signer->check($signer->sign('http://example.com/foo?bar=foo&foo=bar#foobar')));
    }
}
