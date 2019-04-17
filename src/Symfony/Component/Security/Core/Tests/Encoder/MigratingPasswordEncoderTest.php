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

class MigratingPasswordEncoderTest extends TestCase
{
    public function testValidation()
    {
        $bestEncoder = new NativePasswordEncoder(4, 12000, 4);

        $extraEncoder = $this->getMockBuilder(TestPasswordEncoderInterface::class)->getMock();
        $extraEncoder->expects($this->never())->method('encodePassword');
        $extraEncoder->expects($this->never())->method('isPasswordValid');
        $extraEncoder->expects($this->never())->method('needsRehash');

        $encoder = new MigratingPasswordEncoder($bestEncoder, $extraEncoder);

        $this->assertTrue($encoder->needsRehash('foo'));

        $hash = $encoder->encodePassword('foo', 'salt');
        $this->assertFalse($encoder->needsRehash($hash));

        $this->assertTrue($encoder->isPasswordValid($hash, 'foo', 'salt'));
        $this->assertFalse($encoder->isPasswordValid($hash, 'bar', 'salt'));
    }

    public function testFallback()
    {
        $bestEncoder = new NativePasswordEncoder(4, 12000, 4);

        $extraEncoder1 = $this->getMockBuilder(TestPasswordEncoderInterface::class)->getMock();
        $extraEncoder1->expects($this->any())
            ->method('isPasswordValid')
            ->with('abc', 'foo', 'salt')
            ->willReturn(true);

        $encoder = new MigratingPasswordEncoder($bestEncoder, $extraEncoder1);

        $this->assertTrue($encoder->isPasswordValid('abc', 'foo', 'salt'));

        $extraEncoder2 = $this->getMockBuilder(TestPasswordEncoderInterface::class)->getMock();
        $extraEncoder2->expects($this->any())
            ->method('isPasswordValid')
            ->willReturn(false);

        $encoder = new MigratingPasswordEncoder($bestEncoder, $extraEncoder2);

        $this->assertFalse($encoder->isPasswordValid('abc', 'foo', 'salt'));

        $encoder = new MigratingPasswordEncoder($bestEncoder, $extraEncoder2, $extraEncoder1);

        $this->assertTrue($encoder->isPasswordValid('abc', 'foo', 'salt'));
    }
}
