<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\PasswordHasher\Tests\Hasher;

use PHPUnit\Framework\TestCase;
use Symfony\Component\PasswordHasher\Hasher\MigratingPasswordHasher;
use Symfony\Component\PasswordHasher\Hasher\NativePasswordHasher;
use Symfony\Component\PasswordHasher\PasswordHasherInterface;

class MigratingPasswordHasherTest extends TestCase
{
    public function testValidation()
    {
        $bestHasher = new NativePasswordHasher(4, 12000, 4);

        $extraHasher = $this->createMock(PasswordHasherInterface::class);
        $extraHasher->expects($this->never())->method('hash');
        $extraHasher->expects($this->never())->method('verify');
        $extraHasher->expects($this->never())->method('needsRehash');

        $hasher = new MigratingPasswordHasher($bestHasher, $extraHasher);

        $this->assertTrue($hasher->needsRehash('foo'));

        $hash = $hasher->hash('foo', 'salt');
        $this->assertFalse($hasher->needsRehash($hash));

        $this->assertTrue($hasher->verify($hash, 'foo', 'salt'));
        $this->assertFalse($hasher->verify($hash, 'bar', 'salt'));
    }

    public function testFallback()
    {
        $bestHasher = new NativePasswordHasher(4, 12000, 4);

        $extraHasher1 = $this->createMock(PasswordHasherInterface::class);
        $extraHasher1->expects($this->any())
            ->method('verify')
            ->with('abc', 'foo', 'salt')
            ->willReturn(true);

        $hasher = new MigratingPasswordHasher($bestHasher, $extraHasher1);

        $this->assertTrue($hasher->verify('abc', 'foo', 'salt'));

        $extraHasher2 = $this->createMock(PasswordHasherInterface::class);
        $extraHasher2->expects($this->any())
            ->method('verify')
            ->willReturn(false);

        $hasher = new MigratingPasswordHasher($bestHasher, $extraHasher2);

        $this->assertFalse($hasher->verify('abc', 'foo', 'salt'));

        $hasher = new MigratingPasswordHasher($bestHasher, $extraHasher2, $extraHasher1);

        $this->assertTrue($hasher->verify('abc', 'foo', 'salt'));
    }
}
