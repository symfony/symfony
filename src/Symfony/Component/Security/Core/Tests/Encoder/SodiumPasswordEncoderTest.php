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
use Symfony\Component\Security\Core\Exception\BadCredentialsException;

/**
 * @group legacy
 */
class SodiumPasswordEncoderTest extends TestCase
{
    protected function setUp(): void
    {
        if (!SodiumPasswordEncoder::isSupported()) {
            self::markTestSkipped('Libsodium is not available.');
        }
    }

    public function testValidation()
    {
        $encoder = new SodiumPasswordEncoder();
        $result = $encoder->encodePassword('password', null);
        self::assertTrue($encoder->isPasswordValid($result, 'password', null));
        self::assertFalse($encoder->isPasswordValid($result, 'anotherPassword', null));
        self::assertFalse($encoder->isPasswordValid($result, '', null));
    }

    public function testBCryptValidation()
    {
        $encoder = new SodiumPasswordEncoder();
        self::assertTrue($encoder->isPasswordValid('$2y$04$M8GDODMoGQLQRpkYCdoJh.lbiZPee3SZI32RcYK49XYTolDGwoRMm', 'abc', null));
    }

    public function testNonArgonValidation()
    {
        $encoder = new SodiumPasswordEncoder();
        self::assertTrue($encoder->isPasswordValid('$5$abcdefgh$ZLdkj8mkc2XVSrPVjskDAgZPGjtj1VGVaa1aUkrMTU/', 'password', null));
        self::assertFalse($encoder->isPasswordValid('$5$abcdefgh$ZLdkj8mkc2XVSrPVjskDAgZPGjtj1VGVaa1aUkrMTU/', 'anotherPassword', null));
        self::assertTrue($encoder->isPasswordValid('$6$abcdefgh$yVfUwsw5T.JApa8POvClA1pQ5peiq97DUNyXCZN5IrF.BMSkiaLQ5kvpuEm/VQ1Tvh/KV2TcaWh8qinoW5dhA1', 'password', null));
        self::assertFalse($encoder->isPasswordValid('$6$abcdefgh$yVfUwsw5T.JApa8POvClA1pQ5peiq97DUNyXCZN5IrF.BMSkiaLQ5kvpuEm/VQ1Tvh/KV2TcaWh8qinoW5dhA1', 'anotherPassword', null));
    }

    public function testEncodePasswordLength()
    {
        self::expectException(BadCredentialsException::class);
        $encoder = new SodiumPasswordEncoder();
        $encoder->encodePassword(str_repeat('a', 4097), 'salt');
    }

    public function testCheckPasswordLength()
    {
        $encoder = new SodiumPasswordEncoder();
        $result = $encoder->encodePassword(str_repeat('a', 4096), null);
        self::assertFalse($encoder->isPasswordValid($result, str_repeat('a', 4097), null));
        self::assertTrue($encoder->isPasswordValid($result, str_repeat('a', 4096), null));
    }

    public function testUserProvidedSaltIsNotUsed()
    {
        $encoder = new SodiumPasswordEncoder();
        $result = $encoder->encodePassword('password', 'salt');
        self::assertTrue($encoder->isPasswordValid($result, 'password', 'anotherSalt'));
    }

    public function testNeedsRehash()
    {
        $encoder = new SodiumPasswordEncoder(4, 11000);

        self::assertTrue($encoder->needsRehash('dummyhash'));

        $hash = $encoder->encodePassword('foo', 'salt');
        self::assertFalse($encoder->needsRehash($hash));

        $encoder = new SodiumPasswordEncoder(5, 11000);
        self::assertTrue($encoder->needsRehash($hash));
    }
}
