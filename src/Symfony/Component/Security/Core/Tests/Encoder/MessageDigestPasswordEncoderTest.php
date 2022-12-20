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
use Symfony\Component\Security\Core\Encoder\MessageDigestPasswordEncoder;
use Symfony\Component\Security\Core\Exception\BadCredentialsException;
use Symfony\Component\Security\Core\Tests\Encoder\Fixtures\MyMessageDigestPasswordEncoder;

/**
 * @group legacy
 */
class MessageDigestPasswordEncoderTest extends TestCase
{
    public function testIsPasswordValid()
    {
        $encoder = new MessageDigestPasswordEncoder('sha256', false, 1);

        self::assertTrue($encoder->isPasswordValid(hash('sha256', 'password'), 'password', ''));
    }

    public function testEncodePassword()
    {
        $encoder = new MessageDigestPasswordEncoder('sha256', false, 1);
        self::assertSame(hash('sha256', 'password'), $encoder->encodePassword('password', ''));

        $encoder = new MessageDigestPasswordEncoder('sha256', true, 1);
        self::assertSame(base64_encode(hash('sha256', 'password', true)), $encoder->encodePassword('password', ''));

        $encoder = new MessageDigestPasswordEncoder('sha256', false, 2);
        self::assertSame(hash('sha256', hash('sha256', 'password', true).'password'), $encoder->encodePassword('password', ''));
    }

    public function testEncodePasswordAlgorithmDoesNotExist()
    {
        self::expectException(\LogicException::class);
        $encoder = new MessageDigestPasswordEncoder('foobar');
        $encoder->encodePassword('password', '');
    }

    public function testEncodePasswordLength()
    {
        self::expectException(BadCredentialsException::class);
        $encoder = new MessageDigestPasswordEncoder();

        $encoder->encodePassword(str_repeat('a', 5000), 'salt');
    }

    public function testCheckPasswordLength()
    {
        $encoder = new MessageDigestPasswordEncoder();

        self::assertFalse($encoder->isPasswordValid('encoded', str_repeat('a', 5000), 'salt'));
    }

    public function testCustomEncoder()
    {
        $encoder = new MyMessageDigestPasswordEncoder();
        $encodedPassword = $encoder->encodePassword('p4ssw0rd', 's417');

        self::assertSame(base64_encode(hash('sha512', '{"password":"p4ssw0rd","salt":"s417"}', true)), $encodedPassword);
        self::assertTrue($encoder->isPasswordValid($encodedPassword, 'p4ssw0rd', 's417'));
    }
}
