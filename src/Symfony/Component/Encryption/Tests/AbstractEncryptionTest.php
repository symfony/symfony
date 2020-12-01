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
use Symfony\Component\Encryption\Ciphertext;
use Symfony\Component\Encryption\EncryptionInterface;
use Symfony\Component\Encryption\Exception\DecryptionException;
use Symfony\Component\Encryption\Exception\MalformedCipherException;
use Symfony\Component\Encryption\Exception\SignatureVerificationRequiredException;
use Symfony\Component\Encryption\Exception\UnsupportedAlgorithmException;
use Symfony\Component\Encryption\KeyInterface;

/**
 * @author Tobias Nyholm <tobias.nyholm@gmail.com>
 */
abstract class AbstractEncryptionTest extends TestCase
{
    public function testGenerateKey()
    {
        $cipher = $this->getEncryption();
        $key = $cipher->generateKey('s3cr3t');

        $message = 'input';
        $ciphertext = $cipher->encrypt($message, $key);

        $key2 = $cipher->generateKey('s3cr3t');
        $this->assertSame($message, $cipher->decrypt($ciphertext, $key2));
    }

    public function testEncrypt()
    {
        $cipher = $this->getEncryption();
        $key = $cipher->generateKey();

        $ciphertext = $cipher->encrypt('', $key);
        $this->assertNotEmpty($ciphertext);
        $this->assertTrue(\strlen($ciphertext) > 10);
        $this->assertNotEquals('input', $cipher->encrypt('input', $key));

        $input = 'random_string';
        $key2 = $cipher->generateKey();
        $this->assertNotEquals($cipher->encrypt($input, $key), $cipher->encrypt($input, $key2));
    }

    public function testDecryptSymmetric()
    {
        $cipher = $this->getEncryption();
        $key = $cipher->generateKey();

        $this->assertSame($input = '', $cipher->decrypt($cipher->encrypt($input, $key), $key));
        $this->assertSame($input = 'foobar', $cipher->decrypt($cipher->encrypt($input, $key), $key));
    }

    public function testDecryptionThrowsOnMalformedCipher()
    {
        $cipher = $this->getEncryption();
        $key = $cipher->generateKey();
        $this->expectException(MalformedCipherException::class);
        $cipher->decrypt('foo', $key);
    }

    public function testDecryptionThrowsOnUnsupportedAlgorithm()
    {
        $cipher = $this->getEncryption();
        $key = $cipher->generateKey();

        $this->expectException(UnsupportedAlgorithmException::class);
        $cipher->decrypt(Ciphertext::create('foo', 'bar', 'baz')->getString(), $key);
    }

    public function testEncryptFor()
    {
        $cipher = $this->getEncryption();
        $bobKey = $cipher->generateKey();
        $bobPublic = $bobKey->extractPublicKey();

        $ciphertext = $cipher->encryptFor('', $bobPublic);
        $this->assertNotEmpty($ciphertext);
        $this->assertTrue(\strlen($ciphertext) > 10);
        $this->assertNotEquals('input', $cipher->encryptFor('input', $bobPublic));

        $message = 'the cake is a lie';
        $ciphertext = $cipher->encryptFor($message, $bobPublic);
        $this->assertSame($message, $cipher->decrypt($ciphertext, $bobKey));
        $this->assertSame($message, $cipher->decrypt($ciphertext, $this->createPrivateKey($bobKey)));
    }

    public function testEncryptForAndSign()
    {
        $cipher = $this->getEncryption();
        $aliceKey = $cipher->generateKey();
        $bobKey = $cipher->generateKey();

        $ciphertext = $cipher->encryptForAndSign('', $bobKey->extractPublicKey(), $aliceKey);
        $this->assertNotEmpty($ciphertext);
        $this->assertTrue(\strlen($ciphertext) > 10);

        $message = 'the cake is a lie';
        $ciphertext = $cipher->encryptForAndSign($message, $bobKey->extractPublicKey(), $aliceKey);
        $this->assertSame($message, $cipher->decrypt($ciphertext, $bobKey, $aliceKey->extractPublicKey()));
    }

    /**
     * Bob wants to be sure that Alice sent the message, but Alice never signed it.
     */
    public function testDecryptUnableToVerifySender()
    {
        $cipher = $this->getEncryption();
        $aliceKey = $cipher->generateKey();
        $bobKey = $cipher->generateKey();

        $ciphertext = $cipher->encryptFor($input = 'input', $bobKey->extractPublicKey());
        $this->expectException(DecryptionException::class);
        $this->assertSame($input, $cipher->decrypt($ciphertext, $bobKey, $aliceKey->extractPublicKey()));
    }

    /**
     * Alice signs the message but Bob never verifies it.
     */
    public function testDecryptIgnoreToVerifySender()
    {
        $cipher = $this->getEncryption();
        $aliceKey = $cipher->generateKey();
        $bobKey = $cipher->generateKey();

        $ciphertext = $cipher->encryptForAndSign($input = 'input', $bobKey->extractPublicKey(), $aliceKey);
        $this->expectException(SignatureVerificationRequiredException::class);
        $this->assertSame($input, $cipher->decrypt($ciphertext, $this->createPrivateKey($bobKey)));
    }

    /**
     * Bob receives a message he thinks is from Alice, but it was sent by Eve.
     */
    public function testDecryptThrowsExceptionOnWrongPublicKey()
    {
        $cipher = $this->getEncryption();
        $aliceKey = $cipher->generateKey();
        $bobKey = $cipher->generateKey();
        $eveKey = $cipher->generateKey();

        $ciphertext = $cipher->encryptForAndSign('input', $bobKey, $eveKey);
        $this->expectException(DecryptionException::class);
        $cipher->decrypt($ciphertext, $bobKey, $aliceKey->extractPublicKey());
    }

    /**
     * Alice sends a message to Bob, but Eve is trying to read it.
     */
    public function testDecryptThrowsExceptionOnWrongPrivateKey()
    {
        $cipher = $this->getEncryption();
        $aliceKey = $cipher->generateKey();
        $bobKey = $cipher->generateKey();
        $eveKey = $cipher->generateKey();

        $ciphertext = $cipher->encryptForAndSign('input', $bobKey, $aliceKey);
        $this->expectException(DecryptionException::class);
        $cipher->decrypt($ciphertext, $eveKey, $aliceKey->extractPublicKey());
    }

    abstract protected function getEncryption(): EncryptionInterface;

    abstract protected function createPrivateKey(KeyInterface $key): KeyInterface;
}
