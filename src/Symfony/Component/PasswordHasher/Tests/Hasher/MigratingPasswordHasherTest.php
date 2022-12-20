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

        $extraHasher = self::createMock(PasswordHasherInterface::class);
        $extraHasher->expects(self::never())->method('hash');
        $extraHasher->expects(self::never())->method('verify');
        $extraHasher->expects(self::never())->method('needsRehash');

        $hasher = new MigratingPasswordHasher($bestHasher, $extraHasher);

        self::assertTrue($hasher->needsRehash('foo'));

        $hash = $hasher->hash('foo', 'salt');
        self::assertFalse($hasher->needsRehash($hash));

        self::assertTrue($hasher->verify($hash, 'foo', 'salt'));
        self::assertFalse($hasher->verify($hash, 'bar', 'salt'));
    }

    public function testFallback()
    {
        $bestHasher = new NativePasswordHasher(4, 12000, 4);

        $extraHasher1 = self::createMock(PasswordHasherInterface::class);
        $extraHasher1->expects(self::any())
            ->method('verify')
            ->with('abc', 'foo', 'salt')
            ->willReturn(true);

        $hasher = new MigratingPasswordHasher($bestHasher, $extraHasher1);

        self::assertTrue($hasher->verify('abc', 'foo', 'salt'));

        $extraHasher2 = self::createMock(PasswordHasherInterface::class);
        $extraHasher2->expects(self::any())
            ->method('verify')
            ->willReturn(false);

        $hasher = new MigratingPasswordHasher($bestHasher, $extraHasher2);

        self::assertFalse($hasher->verify('abc', 'foo', 'salt'));

        $hasher = new MigratingPasswordHasher($bestHasher, $extraHasher2, $extraHasher1);

        self::assertTrue($hasher->verify('abc', 'foo', 'salt'));
    }
}
