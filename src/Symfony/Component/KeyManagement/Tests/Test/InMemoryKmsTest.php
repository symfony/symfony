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

    public function testCiphertextEmbedsTheInstanceTheKeyIdAndTheAadInPrefix()
    {
        $kms = new InMemoryKms('vault');

        $this->assertSame('encrypted/vault/app//hello', $kms->encrypt('app', 'hello')->blob);
        $this->assertSame('encrypted/vault/app/'.bin2hex('tenant=acme').'/hello', $kms->encrypt('app', 'hello', 'tenant=acme')->blob);
    }

    /**
     * Two instances are two providers, each with key material of its own: what one wrote, the
     * other cannot read. Without this, a test routing a ciphertext to the wrong client would pass.
     */
    public function testAnotherInstanceCannotDecrypt()
    {
        $ciphertext = new InMemoryKms()->encrypt('app', 'hello');

        $this->expectException(DecryptionFailedException::class);
        new InMemoryKms()->decrypt($ciphertext);
    }

    public function testTheNameIsRandomUnlessGiven()
    {
        $this->assertNotSame(new InMemoryKms()->name, new InMemoryKms()->name);
        $this->assertSame('vault', new InMemoryKms('vault')->name);
        $this->assertSame('hello', new InMemoryKms('vault')->decrypt(new InMemoryKms('vault')->encrypt('app', 'hello')), 'two instances given the same name stand for the same provider.');
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
