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
use Symfony\Component\Encryption\PasswordHasher;

final class PasswordHasherTest extends TestCase
{
    protected function setUp(): void
    {
        if (!\extension_loaded('sodium')) {
            self::markTestSkipped('ext-sodium is required for PasswordHasher.');
        }
    }

    public function testHashIsAnArgon2idVerifier()
    {
        $hash = (new PasswordHasher())->hash('correct horse battery staple');

        self::assertStringStartsWith('$argon2id$', $hash);
    }

    public function testVerifyAcceptsCorrectPassword()
    {
        $hasher = new PasswordHasher();
        $hash = $hasher->hash('s3cr3t');

        self::assertTrue($hasher->verify('s3cr3t', $hash));
    }

    public function testVerifyRejectsWrongPassword()
    {
        $hasher = new PasswordHasher();
        $hash = $hasher->hash('s3cr3t');

        self::assertFalse($hasher->verify('wrong', $hash));
    }

    public function testVerifyReturnsFalseForMalformedHash()
    {
        self::assertFalse((new PasswordHasher())->verify('s3cr3t', 'not-a-hash'));
    }

    public function testNeedsRehashIsFalseForFreshHashWithSameParameters()
    {
        $hasher = new PasswordHasher();
        $hash = $hasher->hash('s3cr3t');

        self::assertFalse($hasher->needsRehash($hash));
    }

    public function testNeedsRehashIsTrueWhenParametersStrengthen()
    {
        $weak = new PasswordHasher(
            PasswordHasher::OPSLIMIT_INTERACTIVE,
            PasswordHasher::MEMLIMIT_INTERACTIVE,
        );
        $strong = new PasswordHasher(
            PasswordHasher::OPSLIMIT_MODERATE,
            PasswordHasher::MEMLIMIT_MODERATE,
        );

        self::assertTrue($strong->needsRehash($weak->hash('s3cr3t')));
    }

    public function testNeedsRehashIsTrueForMalformedHash()
    {
        self::assertTrue((new PasswordHasher())->needsRehash('not-a-hash'));
    }
}
