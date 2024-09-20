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
use Symfony\Component\HttpFoundation\Exception\LogicException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\UriSigner;

/**
 * @group time-sensitive
 */
class UriSignerTest extends TestCase
{
    public function testSign()
    {
        $signer = new UriSigner('foobar');

        $this->assertStringContainsString('?_hash=', $signer->sign('http://example.com/foo'));
        $this->assertStringContainsString('?_hash=', $signer->sign('http://example.com/foo?foo=bar'));
        $this->assertStringContainsString('&foo=', $signer->sign('http://example.com/foo?foo=bar'));

        $this->assertStringContainsString('?_expiration=', $signer->sign('http://example.com/foo', 1));
        $this->assertStringContainsString('&_hash=', $signer->sign('http://example.com/foo', 1));
        $this->assertStringContainsString('?_expiration=', $signer->sign('http://example.com/foo?foo=bar', 1));
        $this->assertStringContainsString('&_hash=', $signer->sign('http://example.com/foo?foo=bar', 1));
        $this->assertStringContainsString('&foo=', $signer->sign('http://example.com/foo?foo=bar', 1));
    }

    public function testCheck()
    {
        $signer = new UriSigner('foobar');

        $this->assertFalse($signer->check('http://example.com/foo'));
        $this->assertFalse($signer->check('http://example.com/foo?_hash=foo'));
        $this->assertFalse($signer->check('http://example.com/foo?foo=bar&_hash=foo'));
        $this->assertFalse($signer->check('http://example.com/foo?foo=bar&_hash=foo&bar=foo'));

        $this->assertFalse($signer->check('http://example.com/foo?_expiration=4070908800'));
        $this->assertFalse($signer->check('http://example.com/foo?_expiration=4070908800?_hash=foo'));
        $this->assertFalse($signer->check('http://example.com/foo?_expiration=4070908800&foo=bar&_hash=foo'));
        $this->assertFalse($signer->check('http://example.com/foo?_expiration=4070908800&foo=bar&_hash=foo&bar=foo'));

        $this->assertTrue($signer->check($signer->sign('http://example.com/foo')));
        $this->assertTrue($signer->check($signer->sign('http://example.com/foo?foo=bar')));
        $this->assertTrue($signer->check($signer->sign('http://example.com/foo?foo=bar&0=integer')));

        $this->assertTrue($signer->check($signer->sign('http://example.com/foo', new \DateTimeImmutable('2099-01-01 00:00:00'))));
        $this->assertTrue($signer->check($signer->sign('http://example.com/foo?foo=bar', new \DateTimeImmutable('2099-01-01 00:00:00'))));
        $this->assertTrue($signer->check($signer->sign('http://example.com/foo?foo=bar&0=integer', new \DateTimeImmutable('2099-01-01 00:00:00'))));

        $this->assertSame($signer->sign('http://example.com/foo?foo=bar&bar=foo'), $signer->sign('http://example.com/foo?bar=foo&foo=bar'));
        $this->assertSame($signer->sign('http://example.com/foo?foo=bar&bar=foo', 1), $signer->sign('http://example.com/foo?bar=foo&foo=bar', 1));
    }

    public function testCheckWithDifferentArgSeparator()
    {
        $oldArgSeparatorOutputValue = ini_set('arg_separator.output', '&amp;');

        try {
            $signer = new UriSigner('foobar');

            $this->assertSame(
                'http://example.com/foo?_hash=rIOcC%2FF3DoEGo%2FvnESjSp7uU9zA9S%2F%2BOLhxgMexoPUM%3D&baz=bay&foo=bar',
                $signer->sign('http://example.com/foo?foo=bar&baz=bay')
            );
            $this->assertTrue($signer->check($signer->sign('http://example.com/foo?foo=bar&baz=bay')));

            $this->assertSame(
                'http://example.com/foo?_expiration=2145916800&_hash=xLhnPMzV3KqqHaaUffBUJvtRDAZ4%2FZ9Y8Sw%2BgmS%2B82Q%3D&baz=bay&foo=bar',
                $signer->sign('http://example.com/foo?foo=bar&baz=bay', new \DateTimeImmutable('2038-01-01 00:00:00', new \DateTimeZone('UTC')))
            );
            $this->assertTrue($signer->check($signer->sign('http://example.com/foo?foo=bar&baz=bay', new \DateTimeImmutable('2099-01-01 00:00:00'))));
        } finally {
            ini_set('arg_separator.output', $oldArgSeparatorOutputValue);
        }
    }

    public function testCheckWithRequest()
    {
        $signer = new UriSigner('foobar');

        $this->assertTrue($signer->checkRequest(Request::create($signer->sign('http://example.com/foo'))));
        $this->assertTrue($signer->checkRequest(Request::create($signer->sign('http://example.com/foo?foo=bar'))));
        $this->assertTrue($signer->checkRequest(Request::create($signer->sign('http://example.com/foo?foo=bar&0=integer'))));

        $this->assertTrue($signer->checkRequest(Request::create($signer->sign('http://example.com/foo', new \DateTimeImmutable('2099-01-01 00:00:00')))));
        $this->assertTrue($signer->checkRequest(Request::create($signer->sign('http://example.com/foo?foo=bar', new \DateTimeImmutable('2099-01-01 00:00:00')))));
        $this->assertTrue($signer->checkRequest(Request::create($signer->sign('http://example.com/foo?foo=bar&0=integer', new \DateTimeImmutable('2099-01-01 00:00:00')))));
    }

    public function testCheckWithDifferentParameter()
    {
        $signer = new UriSigner('foobar', 'qux', 'abc');

        $this->assertSame(
            'http://example.com/foo?baz=bay&foo=bar&qux=rIOcC%2FF3DoEGo%2FvnESjSp7uU9zA9S%2F%2BOLhxgMexoPUM%3D',
            $signer->sign('http://example.com/foo?foo=bar&baz=bay')
        );
        $this->assertTrue($signer->check($signer->sign('http://example.com/foo?foo=bar&baz=bay')));

        $this->assertSame(
            'http://example.com/foo?abc=2145916800&baz=bay&foo=bar&qux=kE4rK2MzeiwrYAKy%2B%2FGKvKA6bnzqCbACBdpC3yGnPVU%3D',
            $signer->sign('http://example.com/foo?foo=bar&baz=bay', new \DateTimeImmutable('2038-01-01 00:00:00', new \DateTimeZone('UTC')))
        );
        $this->assertTrue($signer->check($signer->sign('http://example.com/foo?foo=bar&baz=bay', new \DateTimeImmutable('2099-01-01 00:00:00'))));
    }

    public function testSignerWorksWithFragments()
    {
        $signer = new UriSigner('foobar');

        $this->assertSame(
            'http://example.com/foo?_hash=EhpAUyEobiM3QTrKxoLOtQq5IsWyWedoXDPqIjzNj5o%3D&bar=foo&foo=bar#foobar',
            $signer->sign('http://example.com/foo?bar=foo&foo=bar#foobar')
        );

        $this->assertTrue($signer->check($signer->sign('http://example.com/foo?bar=foo&foo=bar#foobar')));

        $this->assertSame(
            'http://example.com/foo?_expiration=2145916800&_hash=jTdrIE9MJSorNpQmkX6tmOtocxXtHDzIJawcAW4IFYo%3D&bar=foo&foo=bar#foobar',
            $signer->sign('http://example.com/foo?bar=foo&foo=bar#foobar', new \DateTimeImmutable('2038-01-01 00:00:00', new \DateTimeZone('UTC')))
        );

        $this->assertTrue($signer->check($signer->sign('http://example.com/foo?bar=foo&foo=bar#foobar', new \DateTimeImmutable('2099-01-01 00:00:00'))));
    }

    public function testSignWithUriExpiration()
    {
        $signer = new UriSigner('foobar');

        $this->assertSame($signer->sign('http://example.com/foo?foo=bar&bar=foo', new \DateTimeImmutable('2038-01-01 00:00:00', new \DateTimeZone('UTC'))), $signer->sign('http://example.com/foo?bar=foo&foo=bar', 2145916800));
    }

    public function testSignWithoutExpirationAndWithReservedHashParameter()
    {
        $signer = new UriSigner('foobar');

        $this->expectException(LogicException::class);

        $signer->sign('http://example.com/foo?_hash=bar');
    }

    public function testSignWithoutExpirationAndWithReservedParameter()
    {
        $signer = new UriSigner('foobar');

        $this->expectException(LogicException::class);

        $signer->sign('http://example.com/foo?_expiration=4070908800');
    }

    public function testSignWithExpirationAndWithReservedHashParameter()
    {
        $signer = new UriSigner('foobar');

        $this->expectException(LogicException::class);

        $signer->sign('http://example.com/foo?_hash=bar', new \DateTimeImmutable('2099-01-01 00:00:00'));
    }

    public function testSignWithExpirationAndWithReservedParameter()
    {
        $signer = new UriSigner('foobar');

        $this->expectException(LogicException::class);

        $signer->sign('http://example.com/foo?_expiration=4070908800', new \DateTimeImmutable('2099-01-01 00:00:00'));
    }

    public function testCheckWithUriExpiration()
    {
        $signer = new UriSigner('foobar');

        $this->assertFalse($signer->check($signer->sign('http://example.com/foo', new \DateTimeImmutable('2000-01-01 00:00:00'))));
        $this->assertFalse($signer->check($signer->sign('http://example.com/foo?foo=bar', new \DateTimeImmutable('2000-01-01 00:00:00'))));
        $this->assertFalse($signer->check($signer->sign('http://example.com/foo?foo=bar&0=integer', new \DateTimeImmutable('2000-01-01 00:00:00'))));

        $this->assertFalse($signer->check($signer->sign('http://example.com/foo', 1577836800))); // 2000-01-01
        $this->assertFalse($signer->check($signer->sign('http://example.com/foo?foo=bar', 1577836800))); // 2000-01-01
        $this->assertFalse($signer->check($signer->sign('http://example.com/foo?foo=bar&0=integer', 1577836800))); // 2000-01-01

        $relativeUriFromNow1 = $signer->sign('http://example.com/foo', new \DateInterval('PT3S'));
        $relativeUriFromNow2 = $signer->sign('http://example.com/foo?foo=bar', new \DateInterval('PT3S'));
        $relativeUriFromNow3 = $signer->sign('http://example.com/foo?foo=bar&0=integer', new \DateInterval('PT3S'));
        sleep(10);

        $this->assertFalse($signer->check($relativeUriFromNow1));
        $this->assertFalse($signer->check($relativeUriFromNow2));
        $this->assertFalse($signer->check($relativeUriFromNow3));
    }
}
