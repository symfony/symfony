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
use Symfony\Component\Security\Core\Encoder\MigratingPasswordEncoder;
use Symfony\Component\Security\Core\Encoder\NativePasswordEncoder;

/**
 * @group legacy
 */
class MigratingPasswordEncoderTest extends TestCase
{
    public function testValidation()
    {
        $bestEncoder = new NativePasswordEncoder(4, 12000, 4);

        $extraEncoder = self::createMock(TestPasswordEncoderInterface::class);
        $extraEncoder->expects(self::never())->method('encodePassword');
        $extraEncoder->expects(self::never())->method('isPasswordValid');
        $extraEncoder->expects(self::never())->method('needsRehash');

        $encoder = new MigratingPasswordEncoder($bestEncoder, $extraEncoder);

        self::assertTrue($encoder->needsRehash('foo'));

        $hash = $encoder->encodePassword('foo', 'salt');
        self::assertFalse($encoder->needsRehash($hash));

        self::assertTrue($encoder->isPasswordValid($hash, 'foo', 'salt'));
        self::assertFalse($encoder->isPasswordValid($hash, 'bar', 'salt'));
    }

    public function testFallback()
    {
        $bestEncoder = new NativePasswordEncoder(4, 12000, 4);

        $extraEncoder1 = self::createMock(TestPasswordEncoderInterface::class);
        $extraEncoder1->expects(self::any())
            ->method('isPasswordValid')
            ->with('abc', 'foo', 'salt')
            ->willReturn(true);

        $encoder = new MigratingPasswordEncoder($bestEncoder, $extraEncoder1);

        self::assertTrue($encoder->isPasswordValid('abc', 'foo', 'salt'));

        $extraEncoder2 = self::createMock(TestPasswordEncoderInterface::class);
        $extraEncoder2->expects(self::any())
            ->method('isPasswordValid')
            ->willReturn(false);

        $encoder = new MigratingPasswordEncoder($bestEncoder, $extraEncoder2);

        self::assertFalse($encoder->isPasswordValid('abc', 'foo', 'salt'));

        $encoder = new MigratingPasswordEncoder($bestEncoder, $extraEncoder2, $extraEncoder1);

        self::assertTrue($encoder->isPasswordValid('abc', 'foo', 'salt'));
    }
}
