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

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\IgnoreDeprecations;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\HttpFoundation\Exception\ExpiredSignedUriException;
use Symfony\Component\HttpFoundation\Exception\LogicException;
use Symfony\Component\HttpFoundation\Exception\UnsignedUriException;
use Symfony\Component\HttpFoundation\Exception\UnverifiedSignedUriException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\UriSigner;

#[Group('time-sensitive')]
class UriSignerTest extends TestCase
{
    public function testSign()
    {
        $signer = new UriSigner('foobar');

        $this->assertStringContainsString('?_expiration=', $signer->sign('http://example.com/foo', 1));
        $this->assertStringContainsString('&_hash=', $signer->sign('http://example.com/foo', 1));
        $this->assertStringContainsString('?_expiration=', $signer->sign('http://example.com/foo?foo=bar', 1));
        $this->assertStringContainsString('&_hash=', $signer->sign('http://example.com/foo?foo=bar', 1));
        $this->assertStringContainsString('&foo=', $signer->sign('http://example.com/foo?foo=bar', 1));
    }

    #[Group('legacy')]
    #[IgnoreDeprecations]
    public function testSignWithoutExpiration()
    {
        $signer = new UriSigner('foobar');

        $this->assertStringContainsString('?_hash=', $signer->sign('http://example.com/foo'));
        $this->assertStringContainsString('?_hash=', $signer->sign('http://example.com/foo?foo=bar'));
        $this->assertStringContainsString('&foo=', $signer->sign('http://example.com/foo?foo=bar'));
    }

    public function testCheckWithExpirationAtTheUnixEpoch()
    {
        $signer = new UriSigner('foobar');

        // "0" is a real expiration date, not the absence of one
        $this->assertFalse($signer->check($signer->sign('http://example.com/foo', 0)));
        $this->assertFalse($signer->check($signer->sign('http://example.com/foo', new \DateTimeImmutable('@0'))));
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

        $this->assertTrue($signer->check($signer->sign('http://example.com/foo', new \DateTimeImmutable('2099-01-01 00:00:00'))));
        $this->assertTrue($signer->check($signer->sign('http://example.com/foo?foo=bar', new \DateTimeImmutable('2099-01-01 00:00:00'))));
        $this->assertTrue($signer->check($signer->sign('http://example.com/foo?foo=bar&0=integer', new \DateTimeImmutable('2099-01-01 00:00:00'))));

        $this->assertSame($signer->sign('http://example.com/foo?foo=bar&bar=foo', 1), $signer->sign('http://example.com/foo?bar=foo&foo=bar', 1));
    }

    #[Group('legacy')]
    #[IgnoreDeprecations]
    public function testCheckWithoutExpiration()
    {
        $signer = new UriSigner('foobar');

        $this->assertTrue($signer->check($signer->sign('http://example.com/foo')));
        $this->assertTrue($signer->check($signer->sign('http://example.com/foo?foo=bar')));
        $this->assertTrue($signer->check($signer->sign('http://example.com/foo?foo=bar&0=integer')));

        $this->assertSame($signer->sign('http://example.com/foo?foo=bar&bar=foo'), $signer->sign('http://example.com/foo?bar=foo&foo=bar'));
    }

    public function testCheckWithNonStringHash()
    {
        $signer = new UriSigner('foobar');

        $this->assertFalse($signer->check('http://example.com/foo?_hash[]=y'));
        $this->assertFalse($signer->check('http://example.com/foo?_hash[k]=y'));
        $this->assertFalse($signer->check('http://example.com/foo?foo=bar&_hash[]='));
    }

    public function testCheckRequestWithNonStringHash()
    {
        $signer = new UriSigner('foobar');

        $this->assertFalse($signer->checkRequest(Request::create('http://example.com/foo?_path=x&_hash[]=y')));
        $this->assertFalse($signer->checkRequest(Request::create('http://example.com/foo?_hash[k]=y')));
    }

    public function testCheckWithDifferentArgSeparator()
    {
        $oldArgSeparatorOutputValue = ini_set('arg_separator.output', '&amp;');

        try {
            $signer = new UriSigner('foobar');

            $this->assertSame(
                'http://example.com/foo?_expiration=2145916800&_hash=xLhnPMzV3KqqHaaUffBUJvtRDAZ4_Z9Y8Sw-gmS-82Q&baz=bay&foo=bar',
                $signer->sign('http://example.com/foo?foo=bar&baz=bay', new \DateTimeImmutable('2038-01-01 00:00:00', new \DateTimeZone('UTC')))
            );
            $this->assertTrue($signer->check($signer->sign('http://example.com/foo?foo=bar&baz=bay', new \DateTimeImmutable('2099-01-01 00:00:00'))));
        } finally {
            ini_set('arg_separator.output', $oldArgSeparatorOutputValue);
        }
    }

    #[Group('legacy')]
    #[IgnoreDeprecations]
    public function testCheckWithDifferentArgSeparatorWithoutExpiration()
    {
        $oldArgSeparatorOutputValue = ini_set('arg_separator.output', '&amp;');

        try {
            $signer = new UriSigner('foobar');

            $this->assertSame(
                'http://example.com/foo?_hash=rIOcC_F3DoEGo_vnESjSp7uU9zA9S_-OLhxgMexoPUM&baz=bay&foo=bar',
                $signer->sign('http://example.com/foo?foo=bar&baz=bay')
            );
            $this->assertTrue($signer->check($signer->sign('http://example.com/foo?foo=bar&baz=bay')));
        } finally {
            ini_set('arg_separator.output', $oldArgSeparatorOutputValue);
        }
    }

    public function testCheckWithRequest()
    {
        $signer = new UriSigner('foobar');

        $this->assertTrue($signer->checkRequest(Request::create($signer->sign('http://example.com/foo', new \DateTimeImmutable('2099-01-01 00:00:00')))));
        $this->assertTrue($signer->checkRequest(Request::create($signer->sign('http://example.com/foo?foo=bar', new \DateTimeImmutable('2099-01-01 00:00:00')))));
        $this->assertTrue($signer->checkRequest(Request::create($signer->sign('http://example.com/foo?foo=bar&0=integer', new \DateTimeImmutable('2099-01-01 00:00:00')))));
    }

    #[Group('legacy')]
    #[IgnoreDeprecations]
    public function testCheckWithRequestWithoutExpiration()
    {
        $signer = new UriSigner('foobar');

        $this->assertTrue($signer->checkRequest(Request::create($signer->sign('http://example.com/foo'))));
        $this->assertTrue($signer->checkRequest(Request::create($signer->sign('http://example.com/foo?foo=bar'))));
        $this->assertTrue($signer->checkRequest(Request::create($signer->sign('http://example.com/foo?foo=bar&0=integer'))));
    }

    public function testCheckWithDifferentParameter()
    {
        $signer = new UriSigner('foobar', 'qux', 'abc');

        $this->assertSame(
            'http://example.com/foo?abc=2145916800&baz=bay&foo=bar&qux=kE4rK2MzeiwrYAKy-_GKvKA6bnzqCbACBdpC3yGnPVU',
            $signer->sign('http://example.com/foo?foo=bar&baz=bay', new \DateTimeImmutable('2038-01-01 00:00:00', new \DateTimeZone('UTC')))
        );
        $this->assertTrue($signer->check($signer->sign('http://example.com/foo?foo=bar&baz=bay', new \DateTimeImmutable('2099-01-01 00:00:00'))));
    }

    #[Group('legacy')]
    #[IgnoreDeprecations]
    public function testCheckWithDifferentParameterWithoutExpiration()
    {
        $signer = new UriSigner('foobar', 'qux', 'abc');

        $this->assertSame(
            'http://example.com/foo?baz=bay&foo=bar&qux=rIOcC_F3DoEGo_vnESjSp7uU9zA9S_-OLhxgMexoPUM',
            $signer->sign('http://example.com/foo?foo=bar&baz=bay')
        );
        $this->assertTrue($signer->check($signer->sign('http://example.com/foo?foo=bar&baz=bay')));
    }

    public function testSignerWorksWithFragments()
    {
        $signer = new UriSigner('foobar');

        $this->assertSame(
            'http://example.com/foo?_expiration=2145916800&_hash=jTdrIE9MJSorNpQmkX6tmOtocxXtHDzIJawcAW4IFYo&bar=foo&foo=bar#foobar',
            $signer->sign('http://example.com/foo?bar=foo&foo=bar#foobar', new \DateTimeImmutable('2038-01-01 00:00:00', new \DateTimeZone('UTC')))
        );

        $this->assertTrue($signer->check($signer->sign('http://example.com/foo?bar=foo&foo=bar#foobar', new \DateTimeImmutable('2099-01-01 00:00:00'))));
    }

    #[Group('legacy')]
    #[IgnoreDeprecations]
    public function testSignerWorksWithFragmentsWithoutExpiration()
    {
        $signer = new UriSigner('foobar');

        $this->assertSame(
            'http://example.com/foo?_hash=EhpAUyEobiM3QTrKxoLOtQq5IsWyWedoXDPqIjzNj5o&bar=foo&foo=bar#foobar',
            $signer->sign('http://example.com/foo?bar=foo&foo=bar#foobar')
        );

        $this->assertTrue($signer->check($signer->sign('http://example.com/foo?bar=foo&foo=bar#foobar')));
    }

    public function testSignWithUriExpiration()
    {
        $signer = new UriSigner('foobar');

        $this->assertSame($signer->sign('http://example.com/foo?foo=bar&bar=foo', new \DateTimeImmutable('2038-01-01 00:00:00', new \DateTimeZone('UTC'))), $signer->sign('http://example.com/foo?bar=foo&foo=bar', 2145916800));
    }

    public function testSignWithDefaultExpiration()
    {
        $clock = new MockClock(new \DateTimeImmutable('2000-01-01 00:00:00', new \DateTimeZone('UTC')));
        $signer = new UriSigner('foobar', clock: $clock, defaultExpiration: new \DateInterval('PT1H'));

        $uri = $signer->sign('http://example.com/foo');
        $this->assertStringContainsString('_expiration=946688400', $uri);
        $this->assertTrue($signer->check($uri));

        $this->assertStringContainsString('_expiration=946684800', $signer->sign('http://example.com/foo', 946684800));
    }

    public function testSignWithDefaultExpirationInSeconds()
    {
        $clock = new MockClock(new \DateTimeImmutable('2000-01-01 00:00:00', new \DateTimeZone('UTC')));
        $signer = new UriSigner('foobar', clock: $clock, defaultExpiration: 3600);

        $uri = $signer->sign('http://example.com/foo');
        $this->assertStringContainsString('_expiration=946688400', $uri);
        $this->assertTrue($signer->check($uri));
    }

    #[Group('legacy')]
    #[IgnoreDeprecations]
    public function testSignWithoutExpirationIsDeprecated()
    {
        $this->expectUserDeprecationMessage('Since symfony/http-foundation 8.2: Not passing an expiration to "Symfony\Component\HttpFoundation\UriSigner::sign()" is deprecated and will be required in 9.0; pass one explicitly, or set a default via the "$defaultExpiration" argument of "Symfony\Component\HttpFoundation\UriSigner::__construct()".');

        (new UriSigner('foobar'))->sign('http://example.com/foo');
    }

    #[Group('legacy')]
    #[IgnoreDeprecations]
    public function testSignWithoutExpirationAndWithReservedHashParameter()
    {
        $signer = new UriSigner('foobar');

        $this->expectException(LogicException::class);

        $signer->sign('http://example.com/foo?_hash=bar');
    }

    #[Group('legacy')]
    #[IgnoreDeprecations]
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

    public function testSignDoesNotExposeVersion()
    {
        $signer = new UriSigner('foobar');
        $expiration = new \DateTimeImmutable('2099-01-01 00:00:00');
        $uri = $signer->sign('http://example.com/foo', $expiration, 'v1');

        parse_str(parse_url($uri, \PHP_URL_QUERY), $params);

        $this->assertArrayNotHasKey('_version', $params);
        $this->assertNotSame($signer->sign('http://example.com/foo', $expiration), $uri);
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

    public function testCheckWithUriExpirationWithClock()
    {
        $clock = new MockClock();
        $signer = new UriSigner('foobar', clock: $clock);

        $this->assertFalse($signer->check($signer->sign('http://example.com/foo', new \DateTimeImmutable('2000-01-01 00:00:00'))));
        $this->assertFalse($signer->check($signer->sign('http://example.com/foo?foo=bar', new \DateTimeImmutable('2000-01-01 00:00:00'))));
        $this->assertFalse($signer->check($signer->sign('http://example.com/foo?foo=bar&0=integer', new \DateTimeImmutable('2000-01-01 00:00:00'))));

        $this->assertFalse($signer->check($signer->sign('http://example.com/foo', 1577836800))); // 2000-01-01
        $this->assertFalse($signer->check($signer->sign('http://example.com/foo?foo=bar', 1577836800))); // 2000-01-01
        $this->assertFalse($signer->check($signer->sign('http://example.com/foo?foo=bar&0=integer', 1577836800))); // 2000-01-01

        $relativeUriFromNow1 = $signer->sign('http://example.com/foo', new \DateInterval('PT3S'));
        $relativeUriFromNow2 = $signer->sign('http://example.com/foo?foo=bar', new \DateInterval('PT3S'));
        $relativeUriFromNow3 = $signer->sign('http://example.com/foo?foo=bar&0=integer', new \DateInterval('PT3S'));
        $clock->sleep(10);

        $this->assertFalse($signer->check($relativeUriFromNow1));
        $this->assertFalse($signer->check($relativeUriFromNow2));
        $this->assertFalse($signer->check($relativeUriFromNow3));
    }

    public function testCheckWithUriVersion()
    {
        $signer = new UriSigner('foobar');
        $expiration = new \DateTimeImmutable('2099-01-01 00:00:00');

        $this->assertTrue($signer->check($signer->sign('http://example.com/foo', $expiration, 'valid'), 'valid'));
        $this->assertFalse($signer->check($signer->sign('http://example.com/foo', $expiration, 'valid'), 'invalid'));
        $this->assertFalse($signer->check($signer->sign('http://example.com/foo', $expiration, 'missing-on-check')));
    }

    public function testCheckRequestWithUriVersion()
    {
        $signer = new UriSigner('foobar');
        $expiration = new \DateTimeImmutable('2099-01-01 00:00:00');

        $this->assertTrue($signer->checkRequest(Request::create($signer->sign('http://example.com/foo', $expiration, 'valid')), 'valid'));
        $this->assertFalse($signer->checkRequest(Request::create($signer->sign('http://example.com/foo', $expiration, 'valid')), 'invalid'));
        $this->assertFalse($signer->checkRequest(Request::create($signer->sign('http://example.com/foo', $expiration, 'missing-on-check'))));
    }

    public function testUnversionedUriFailsWhenVersionExpected()
    {
        $signer = new UriSigner('foobar');
        $expiration = new \DateTimeImmutable('2099-01-01 00:00:00');

        $this->assertTrue($signer->check($signer->sign('http://example.com/foo', $expiration)));
        $this->assertFalse($signer->check($signer->sign('http://example.com/foo', $expiration), 'any'));
        $this->assertFalse($signer->checkRequest(Request::create($signer->sign('http://example.com/foo', $expiration)), 'any'));
    }

    public function testNonUrlSafeBase64()
    {
        $signer = new UriSigner('foobar');
        $this->assertTrue($signer->check('http://example.com/foo?_hash=rIOcC%2FF3DoEGo%2FvnESjSp7uU9zA9S%2F%2BOLhxgMexoPUM%3D&baz=bay&foo=bar'));
    }

    public function testVerifyUnSignedUri()
    {
        $signer = new UriSigner('foobar');
        $uri = 'http://example.com/foo';

        $this->expectException(UnsignedUriException::class);

        $signer->verify($uri);
    }

    public function testVerifyUnverifiedUri()
    {
        $signer = new UriSigner('foobar');
        $uri = 'http://example.com/foo?_hash=invalid';

        $this->expectException(UnverifiedSignedUriException::class);

        $signer->verify($uri);
    }

    public function testVerifyExpiredUri()
    {
        $signer = new UriSigner('foobar');
        $uri = $signer->sign('http://example.com/foo', 123456);

        $this->expectException(ExpiredSignedUriException::class);

        $signer->verify($uri);
    }

    public function testVerifyWithMatchingVersion()
    {
        $signer = new UriSigner('foobar');
        $uri = $signer->sign('http://example.com/foo', new \DateTimeImmutable('2099-01-01 00:00:00'), 'valid');

        $this->expectNotToPerformAssertions();

        $signer->verify($uri, 'valid');
    }

    public function testVerifyWithVersionMismatch()
    {
        $signer = new UriSigner('foobar');
        $uri = $signer->sign('http://example.com/foo', new \DateTimeImmutable('2099-01-01 00:00:00'), 'valid');

        $this->expectException(UnverifiedSignedUriException::class);

        $signer->verify($uri, 'invalid');
    }
}
