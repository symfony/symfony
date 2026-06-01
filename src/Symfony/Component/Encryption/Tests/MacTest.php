<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Encryption\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Encryption\Exception\InvalidArgumentException;
use Symfony\Component\Encryption\Exception\UnsupportedAlgorithmException;
use Symfony\Component\Encryption\Mac;

final class MacTest extends TestCase
{
    public function testGenerateKeyReturns32Bytes()
    {
        self::assertSame(32, \strlen((new Mac())->generateKey()));
    }

    public function testGenerateKeyRespectsRequestedLength()
    {
        self::assertSame(64, \strlen((new Mac())->generateKey(64)));
    }

    public function testGenerateKeyRejectsTooShortLength()
    {
        $this->expectException(InvalidArgumentException::class);

        (new Mac())->generateKey(8);
    }

    public function testSignMatchesKnownHmacVector()
    {
        $mac = new Mac();
        $key = str_repeat("\x0b", 20);

        self::assertSame(hash_hmac('sha256', 'Hi There', $key), $mac->sign('Hi There', $key));
    }

    public function testVerifyAcceptsValidTag()
    {
        $mac = new Mac();
        $key = $mac->generateKey();
        $tag = $mac->sign('message', $key);

        self::assertTrue($mac->verify($tag, 'message', $key));
    }

    public function testVerifyRejectsTamperedMessage()
    {
        $mac = new Mac();
        $key = $mac->generateKey();
        $tag = $mac->sign('message', $key);

        self::assertFalse($mac->verify($tag, 'tampered', $key));
    }

    public function testVerifyRejectsWrongKey()
    {
        $mac = new Mac();
        $tag = $mac->sign('message', $mac->generateKey());

        self::assertFalse($mac->verify($tag, 'message', $mac->generateKey()));
    }

    public function testVerifyRejectsMalformedTagWithoutThrowing()
    {
        $mac = new Mac();
        $key = $mac->generateKey();

        self::assertFalse($mac->verify('not-hex', 'message', $key));
    }

    public function testVerifyRejectsEmptyKeyWithoutThrowing()
    {
        $mac = new Mac();

        self::assertFalse($mac->verify(str_repeat('a', 64), 'message', ''));
    }

    public function testUnsupportedAlgorithmThrows()
    {
        $this->expectException(UnsupportedAlgorithmException::class);

        (new Mac())->sign('message', str_repeat('k', 32), 'md5');
    }

    public function testConstructorRejectsUnsupportedDefaultAlgorithm()
    {
        $this->expectException(UnsupportedAlgorithmException::class);

        new Mac('md5');
    }

    public function testVerifyReturnsFalseForUnsupportedAlgorithmWithoutThrowing()
    {
        $mac = new Mac();
        $key = $mac->generateKey();

        self::assertFalse($mac->verify(str_repeat('a', 64), 'message', $key, 'md5'));
    }
}
