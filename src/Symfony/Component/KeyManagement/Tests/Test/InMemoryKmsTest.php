<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\KeyManagement\Tests\Test;

use PHPUnit\Framework\TestCase;
use Symfony\Component\KeyManagement\Ciphertext;
use Symfony\Component\KeyManagement\Exception\DecryptionFailedException;
use Symfony\Component\KeyManagement\Test\InMemoryKms;

class InMemoryKmsTest extends TestCase
{
    public function testRoundTrip()
    {
        $kms = new InMemoryKms();

        $ciphertext = $kms->encrypt('app', 'hello');
        $this->assertSame('hello', $kms->decrypt($ciphertext));
    }

    public function testCiphertextEmbedsKeyIdAndAadInPrefix()
    {
        $kms = new InMemoryKms();

        $this->assertSame('encrypted/app//hello', $kms->encrypt('app', 'hello')->blob);
        $this->assertSame('encrypted/app/'.bin2hex('tenant=acme').'/hello', $kms->encrypt('app', 'hello', 'tenant=acme')->blob);
    }

    public function testAadRoundTrip()
    {
        $kms = new InMemoryKms();

        $ciphertext = $kms->encrypt('app', 'hello', 'tenant=acme');
        $this->assertSame('hello', $kms->decrypt($ciphertext, 'tenant=acme'));
    }

    public function testDecryptingWithMismatchedAadFails()
    {
        $kms = new InMemoryKms();
        $ciphertext = $kms->encrypt('app', 'hello', 'tenant=acme');

        $this->expectException(DecryptionFailedException::class);
        $kms->decrypt($ciphertext, 'tenant=globex');
    }

    public function testDecryptingWithMissingAadFails()
    {
        $kms = new InMemoryKms();
        $ciphertext = $kms->encrypt('app', 'hello', 'tenant=acme');

        $this->expectException(DecryptionFailedException::class);
        $kms->decrypt($ciphertext);
    }

    public function testDecryptingPlaintextFails()
    {
        $kms = new InMemoryKms();

        $this->expectException(DecryptionFailedException::class);
        $kms->decrypt(new Ciphertext('hello', 'app'));
    }

    public function testDecryptingUnderWrongKeyIdFails()
    {
        $kms = new InMemoryKms();
        $ciphertext = $kms->encrypt('app', 'hello');

        $rerouted = new Ciphertext($ciphertext->blob, 'other-key');

        $this->expectException(DecryptionFailedException::class);
        $kms->decrypt($rerouted);
    }

    public function testKeyIdsContainingSlashesRoundTrip()
    {
        $kms = new InMemoryKms();

        $ciphertext = $kms->encrypt('tenant-a/master', 'hello');
        $this->assertSame('hello', $kms->decrypt($ciphertext));
    }

    public function testDataKeyRoundTripUsesEncryptUnderTheHood()
    {
        $kms = new InMemoryKms();
        $dataKey = $kms->generateDataKey('app', 32);

        $expected = $dataKey->use(static fn (string $p): string => $p);

        $this->assertSame($expected, $kms->unwrapDataKey($dataKey->wrapped)->use(static fn (string $p): string => $p));
    }
}
