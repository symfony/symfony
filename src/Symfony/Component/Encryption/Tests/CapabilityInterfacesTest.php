<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Symfony\Component\Encryption\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Encryption\Hasher;
use Symfony\Component\Encryption\HasherInterface;
use Symfony\Component\Encryption\Mac;
use Symfony\Component\Encryption\MacInterface;
use Symfony\Component\Encryption\PasswordHasher;
use Symfony\Component\Encryption\PasswordHasherInterface;

final class CapabilityInterfacesTest extends TestCase
{
    public function testHasherImplementsInterface(): void
    {
        self::assertInstanceOf(HasherInterface::class, new Hasher());
    }

    public function testMacImplementsInterface(): void
    {
        self::assertInstanceOf(MacInterface::class, new Mac());
    }

    public function testPasswordHasherImplementsInterface(): void
    {
        if (!\extension_loaded('sodium')) {
            self::markTestSkipped('ext-sodium is required.');
        }

        self::assertInstanceOf(PasswordHasherInterface::class, new PasswordHasher());
    }
}
