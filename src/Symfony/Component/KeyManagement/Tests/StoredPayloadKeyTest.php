<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\KeyManagement\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use Symfony\Component\KeyManagement\Ciphertext;
use Symfony\Component\KeyManagement\DataKey;
use Symfony\Component\KeyManagement\DataKeyHandle;
use Symfony\Component\KeyManagement\DataKeyStoreInterface;
use Symfony\Component\KeyManagement\Envelope;
use Symfony\Component\KeyManagement\Exception\DecryptionFailedException;
use Symfony\Component\KeyManagement\Exception\InvalidArgumentException;
use Symfony\Component\KeyManagement\StoredEnvelopeEncrypter;
use Symfony\Component\KeyManagement\Test\InMemoryDataKeyStore;
use Symfony\Component\KeyManagement\Tests\Fixtures\RedactedTraceAssertionsTrait;

#[RequiresPhpExtension('openssl')]
class StoredPayloadKeyTest extends TestCase
{
    use RedactedTraceAssertionsTrait;

    private const string ROOT_KEY = '000102030405060708090a0b0c0d0e0f101112131415161718191a1b1c1d1e1f';
    private const string FIRST_FRAME = '0200097265666572656e6365202122232425262728292a2b2c2d2e2f303132333435363738393a3b3c3d3e3f000102030405060708090a0b894570b2b4abf51b8801ef6884df42b9eaa2451a78673e026ede6c';
    private const string SECOND_FRAME = '0200097265666572656e6365404142434445464748494a4b4c4d4e4f505152535455565758595a5b5c5d5e5f000102030405060708090a0b9bd54e1e8a2f2f3977496e2d515b5ba6bae34848416ce26fd3bc2a';

    public static function provideCiphertextsWithTheSameIv(): iterable
    {
        yield 'first salt' => [self::FIRST_FRAME];
        yield 'second salt' => [self::SECOND_FRAME];
    }

    #[DataProvider('provideCiphertextsWithTheSameIv')]
    public function testDecryptsIndependentVectorsWithTheSameIvAndDifferentSalts(string $frame)
    {
        $envelope = Envelope::fromBytes(hex2bin($frame));
        try {
            $plaintext = $this->encrypter()->decrypt($envelope, 'context');
        } catch (DecryptionFailedException) {
            $this->fail('The stored payload must decrypt with its HKDF-derived key.');
        }

        $this->assertSame("hello\0world", $plaintext);
        $this->assertSame(hex2bin('000102030405060708090a0b'), $envelope->iv);
        $this->assertSame($frame, bin2hex((string) $envelope));
    }

    public function testTheStoredFrameCarriesItsSaltBeforeTheIv()
    {
        $envelope = Envelope::referencing(
            'reference',
            hex2bin('000102030405060708090a0b'),
            hex2bin('894570b2b4abf51b8801ef6884df42b9'),
            hex2bin('eaa2451a78673e026ede6c'),
            hex2bin('202122232425262728292a2b2c2d2e2f303132333435363738393a3b3c3d3e3f'),
        );

        $this->assertSame(self::FIRST_FRAME, bin2hex((string) $envelope));
    }

    #[TestWith([''])]
    #[TestWith(["hello\0world"])]
    #[TestWith(["\0\xFF\x80"])]
    public function testEncryptUsesADerivedKeyAndAuthenticatesTheSalt(string $plaintext)
    {
        $encrypter = $this->encrypter();
        $envelope = $encrypter->encrypt('scope', $plaintext, 'context');
        $bytes = (string) $envelope;

        $this->assertSame(1 + 2 + 9 + 32 + 12 + 16 + \strlen($plaintext), \strlen($bytes));
        $salt = substr($bytes, 1 + 2 + 9, 32);
        $this->assertSame($salt, $envelope->salt);
        $derived = hash_hkdf('sha256', hex2bin(self::ROOT_KEY), 32, 'symfony/key-management/stored-envelope/v1', $salt);
        $aad = "\x02".pack('n', 9).'reference'.pack('n', 32).$salt.'context';

        $this->assertSame($plaintext, openssl_decrypt($envelope->ciphertext, 'aes-256-gcm', $derived, \OPENSSL_RAW_DATA, $envelope->iv, $envelope->tag, $aad));
        $this->assertFalse(openssl_decrypt($envelope->ciphertext, 'aes-256-gcm', hex2bin(self::ROOT_KEY), \OPENSSL_RAW_DATA, $envelope->iv, $envelope->tag, $aad));
        $this->assertSame($plaintext, $encrypter->decrypt(Envelope::fromBytes($bytes), 'context'));
    }

    public function testEachPayloadGetsItsOwnSaltWhileSharingTheStoredKey()
    {
        $store = new InMemoryDataKeyStore();
        $encrypter = new StoredEnvelopeEncrypter($store);
        $first = $encrypter->encrypt('scope', 'hello');
        $second = $encrypter->encrypt('scope', 'hello');

        $this->assertSame(32, \strlen($first->salt ?? ''));
        $this->assertSame(32, \strlen($second->salt ?? ''));
        $this->assertNotSame($first->salt, $second->salt);
        $this->assertSame($first->reference, $second->reference);
        $this->assertCount(1, iterator_to_array($store->all(), false));
        $this->assertSame('hello', $encrypter->decrypt($first));
        $this->assertSame('hello', $encrypter->decrypt($second));
    }

    #[TestWith([0])]
    #[TestWith([31])]
    #[TestWith([33])]
    public function testSaltOfTheWrongLengthIsRejected(int $length)
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Envelope "salt" must be 32 bytes long');
        Envelope::referencing('reference', str_repeat('I', 12), str_repeat('T', 16), 'ciphertext', str_repeat('S', $length));
    }

    #[TestWith([43])]
    #[TestWith([55])]
    #[TestWith([71])]
    public function testTruncatedSaltIvAndTagAreRejected(int $length)
    {
        $this->expectException(InvalidArgumentException::class);
        Envelope::fromBytes(substr(hex2bin(self::FIRST_FRAME), 0, $length));
    }

    public function testTamperingWithTheSaltIsRejected()
    {
        $bytes = hex2bin(self::FIRST_FRAME);
        $bytes = substr_replace($bytes, $bytes[12] ^ "\x01", 12, 1);

        $this->expectException(DecryptionFailedException::class);
        $this->encrypter()->decrypt(Envelope::fromBytes($bytes), 'context');
    }

    public function testRootAndDerivedKeysStayOutOfExceptionTraces()
    {
        $encrypter = $this->encrypter();
        $envelope = $encrypter->encrypt('scope', 'hello', 'context');
        $bytes = (string) $envelope;
        $this->assertSame(1 + 2 + 9 + 32 + 12 + 16 + 5, \strlen($bytes));
        $salt = substr($bytes, 12, 32);
        $derived = hash_hkdf('sha256', hex2bin(self::ROOT_KEY), 32, 'symfony/key-management/stored-envelope/v1', $salt);
        $bytes = substr_replace($bytes, $bytes[-1] ^ "\x01", -1, 1);

        $trace = self::traceOf(static fn () => $encrypter->decrypt(Envelope::fromBytes($bytes), 'context'));

        self::assertRedacted(hex2bin(self::ROOT_KEY), $trace);
        self::assertRedacted($derived, $trace);
    }

    private function encrypter(): StoredEnvelopeEncrypter
    {
        $handle = new DataKeyHandle('reference', new DataKey(hex2bin(self::ROOT_KEY), new Ciphertext('wrapped', 'master')));
        $store = $this->createStub(DataKeyStoreInterface::class);
        $store->method('current')->willReturn($handle);
        $store->method('get')->willReturn($handle);

        return new StoredEnvelopeEncrypter($store);
    }
}
