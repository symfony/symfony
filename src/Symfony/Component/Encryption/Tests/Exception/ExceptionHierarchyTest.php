<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Encryption\Tests\Exception;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Encryption\Exception\CertificateException;
use Symfony\Component\Encryption\Exception\DecryptionException;
use Symfony\Component\Encryption\Exception\EncryptionException;
use Symfony\Component\Encryption\Exception\ExceptionInterface;
use Symfony\Component\Encryption\Exception\InvalidArgumentException;
use Symfony\Component\Encryption\Exception\InvalidKeyException;
use Symfony\Component\Encryption\Exception\NoEngineAvailableException;
use Symfony\Component\Encryption\Exception\UnsupportedAlgorithmException;

final class ExceptionHierarchyTest extends TestCase
{
    /**
     * @return iterable<string, array{class-string, class-string}>
     */
    public static function provideExceptions(): iterable
    {
        yield 'encryption' => [EncryptionException::class, \RuntimeException::class];
        yield 'decryption' => [DecryptionException::class, EncryptionException::class];
        yield 'invalid-argument' => [InvalidArgumentException::class, \InvalidArgumentException::class];
        yield 'invalid-key' => [InvalidKeyException::class, InvalidArgumentException::class];
        yield 'unsupported-algorithm' => [UnsupportedAlgorithmException::class, InvalidArgumentException::class];
        yield 'no-engine' => [NoEngineAvailableException::class, \RuntimeException::class];
        yield 'certificate' => [CertificateException::class, \RuntimeException::class];
    }

    /**
     * @param class-string $class
     * @param class-string $parent
     */
    #[DataProvider('provideExceptions')]
    public function testImplementsMarkerAndExtendsExpectedParent(string $class, string $parent)
    {
        $exception = new $class('boom');

        self::assertInstanceOf(ExceptionInterface::class, $exception);
        self::assertInstanceOf($parent, $exception);
        self::assertSame('boom', $exception->getMessage());
    }
}
