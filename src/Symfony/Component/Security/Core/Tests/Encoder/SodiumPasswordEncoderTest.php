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
use Symfony\Component\Security\Core\Encoder\SodiumPasswordEncoder;

class SodiumPasswordEncoderTest extends TestCase
{
    protected function setUp(): void
    {
        if (!SodiumPasswordEncoder::isSupported()) {
            $this->markTestSkipped('Libsodium is not available.');
        }
    }

    public function testValidation()
    {
        $encoder = new SodiumPasswordEncoder();
        $result = $encoder->encodePassword('password', null);
        $this->assertTrue($encoder->isPasswordValid($result, 'password', null));
        $this->assertFalse($encoder->isPasswordValid($result, 'anotherPassword', null));
    }

    public function testBCryptValidation()
    {
        $encoder = new SodiumPasswordEncoder();
        $this->assertTrue($encoder->isPasswordValid('$2y$04$M8GDODMoGQLQRpkYCdoJh.lbiZPee3SZI32RcYK49XYTolDGwoRMm', 'abc', null));
    }

    public function testEncodePasswordLength()
    {
        $this->expectException('Symfony\Component\Security\Core\Exception\BadCredentialsException');
        $encoder = new SodiumPasswordEncoder();
        $encoder->encodePassword(str_repeat('a', 4097), 'salt');
    }

    public function testCheckPasswordLength()
    {
        $encoder = new SodiumPasswordEncoder();
        $result = $encoder->encodePassword(str_repeat('a', 4096), null);
        $this->assertFalse($encoder->isPasswordValid($result, str_repeat('a', 4097), null));
        $this->assertTrue($encoder->isPasswordValid($result, str_repeat('a', 4096), null));
    }

    public function testUserProvidedSaltIsNotUsed()
    {
        $encoder = new SodiumPasswordEncoder();
        $result = $encoder->encodePassword('password', 'salt');
        $this->assertTrue($encoder->isPasswordValid($result, 'password', 'anotherSalt'));
    }

    public function testNeedsRehash()
    {
        $encoder = new SodiumPasswordEncoder(4, 11000);

        $this->assertTrue($encoder->needsRehash('dummyhash'));

        $hash = $encoder->encodePassword('foo', 'salt');
        $this->assertFalse($encoder->needsRehash($hash));

        $encoder = new SodiumPasswordEncoder(5, 11000);
        $this->assertTrue($encoder->needsRehash($hash));
    }
}
