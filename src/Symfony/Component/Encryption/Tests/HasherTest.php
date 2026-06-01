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
use Symfony\Component\Encryption\Encoding;
use Symfony\Component\Encryption\Exception\UnsupportedAlgorithmException;
use Symfony\Component\Encryption\Hasher;

final class HasherTest extends TestCase
{
    public function testKnownSha256Vector(): void
    {
        $hasher = new Hasher();

        // Well-known SHA-256 of "abc".
        self::assertSame(
            'ba7816bf8f01cfea414140de5dae2223b00361a396177a9cb410ff61f20015ad',
            $hasher->hash('abc'),
        );
    }

    public function testDefaultAlgorithmIsSha256(): void
    {
        $hasher = new Hasher();

        self::assertSame($hasher->hash('abc', 'sha256'), $hasher->hash('abc'));
    }

    public function testSelectableAlgorithm(): void
    {
        $hasher = new Hasher();

        self::assertSame(hash('sha512', 'abc'), $hasher->hash('abc', 'sha512'));
    }

    public function testBase64Output(): void
    {
        $hasher = new Hasher();

        self::assertSame(
            Encoding::toBase64(hash('sha256', 'abc', true)),
            $hasher->hashBase64('abc'),
        );
    }

    public function testRawOutput(): void
    {
        $hasher = new Hasher();

        self::assertSame(hash('sha256', 'abc', true), $hasher->raw('abc'));
    }

    public function testUnsupportedAlgorithmThrows(): void
    {
        $hasher = new Hasher();

        $this->expectException(UnsupportedAlgorithmException::class);

        $hasher->hash('abc', 'md5');
    }

    public function testConstructorRejectsUnsupportedDefaultAlgorithm(): void
    {
        $this->expectException(UnsupportedAlgorithmException::class);

        new Hasher('md5');
    }
}
