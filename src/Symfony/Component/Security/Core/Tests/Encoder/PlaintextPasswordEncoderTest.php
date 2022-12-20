<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Core\Tests\Encoder;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Encoder\PlaintextPasswordEncoder;
use Symfony\Component\Security\Core\Exception\BadCredentialsException;

/**
 * @group legacy
 */
class PlaintextPasswordEncoderTest extends TestCase
{
    public function testIsPasswordValid()
    {
        $encoder = new PlaintextPasswordEncoder();

        self::assertTrue($encoder->isPasswordValid('foo', 'foo', ''));
        self::assertFalse($encoder->isPasswordValid('bar', 'foo', ''));
        self::assertFalse($encoder->isPasswordValid('FOO', 'foo', ''));

        $encoder = new PlaintextPasswordEncoder(true);

        self::assertTrue($encoder->isPasswordValid('foo', 'foo', ''));
        self::assertFalse($encoder->isPasswordValid('bar', 'foo', ''));
        self::assertTrue($encoder->isPasswordValid('FOO', 'foo', ''));
    }

    public function testEncodePassword()
    {
        $encoder = new PlaintextPasswordEncoder();

        self::assertSame('foo', $encoder->encodePassword('foo', ''));
    }

    public function testEncodePasswordLength()
    {
        self::expectException(BadCredentialsException::class);
        $encoder = new PlaintextPasswordEncoder();

        $encoder->encodePassword(str_repeat('a', 5000), 'salt');
    }

    public function testCheckPasswordLength()
    {
        $encoder = new PlaintextPasswordEncoder();

        self::assertFalse($encoder->isPasswordValid('encoded', str_repeat('a', 5000), 'salt'));
    }
}
