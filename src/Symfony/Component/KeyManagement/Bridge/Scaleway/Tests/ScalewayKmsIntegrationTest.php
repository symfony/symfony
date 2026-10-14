<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\KeyManagement\Bridge\Scaleway\Tests;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Symfony\Component\KeyManagement\Bridge\Scaleway\ScalewayKms;
use Symfony\Component\KeyManagement\Bridge\Scaleway\ScalewayKmsFactory;
use Symfony\Component\KeyManagement\Ciphertext;
use Symfony\Component\KeyManagement\Dsn;
use Symfony\Component\KeyManagement\Envelope;
use Symfony\Component\KeyManagement\EnvelopeEncrypter;
use Symfony\Component\KeyManagement\Exception\DecryptionFailedException;
use Symfony\Component\KeyManagement\Exception\KeyNotFoundException;
use Symfony\Component\KeyManagement\Exception\RuntimeException;

/**
 * Runs against a real Scaleway Key Manager key whose usage is `symmetric_encryption`.
 *
 * Set SCALEWAY_KMS_TEST_SECRET_KEY, SCALEWAY_KMS_TEST_REGION and SCALEWAY_KMS_TEST_KEY_ID to run it.
 * SCALEWAY_KMS_TEST_ASYMMETRIC_KEY_ID optionally names an `asymmetric_encryption` key for the AAD checks.
 */
#[Group('integration')]
#[Group('scaleway-integration')]
final class ScalewayKmsIntegrationTest extends TestCase
{
    private const string UNKNOWN_KEY_ID = '00000000-0000-4000-8000-000000000000';

    private ScalewayKms $kms;
    private string $keyId;

    protected function setUp(): void
    {
        $secretKey = getenv('SCALEWAY_KMS_TEST_SECRET_KEY');
        $region = getenv('SCALEWAY_KMS_TEST_REGION');
        $keyId = getenv('SCALEWAY_KMS_TEST_KEY_ID');

        if (!$secretKey || !$region || !$keyId) {
            $this->markTestSkipped('SCALEWAY_KMS_TEST_SECRET_KEY, SCALEWAY_KMS_TEST_REGION and SCALEWAY_KMS_TEST_KEY_ID must be set.');
        }

        $this->kms = $this->createKms($secretKey, $region);
        $this->keyId = $keyId;
    }

    public function testEncryptDecryptRoundTrip()
    {
        $plaintext = "binary \x00\xFF payload ".random_bytes(32);

        $ciphertext = $this->kms->encrypt($this->keyId, $plaintext);

        $this->assertSame($this->keyId, $ciphertext->keyId);
        $this->assertStringNotContainsString($plaintext, $ciphertext->blob);
        $this->assertSame($plaintext, $this->kms->decrypt($ciphertext));
    }

    public function testCiphertextSurvivesASerializationRoundTrip()
    {
        $ciphertext = $this->kms->encrypt($this->keyId, 'hello');

        $this->assertSame('hello', $this->kms->decrypt(new Ciphertext((string) $ciphertext, $this->keyId)));
    }

    public function testAadIsEnforced()
    {
        $ciphertext = $this->kms->encrypt($this->keyId, 'hello', 'tenant=42');

        $this->assertSame('hello', $this->kms->decrypt($ciphertext, 'tenant=42'));

        $this->expectException(DecryptionFailedException::class);
        $this->kms->decrypt($ciphertext, 'tenant=43');
    }

    public function testMissingAadOnDecryptFails()
    {
        $ciphertext = $this->kms->encrypt($this->keyId, 'hello', 'tenant=42');

        $this->expectException(DecryptionFailedException::class);
        $this->kms->decrypt($ciphertext);
    }

    public function testTamperedCiphertextFails()
    {
        $ciphertext = $this->kms->encrypt($this->keyId, 'hello');
        $bytes = base64_decode($ciphertext->blob, true);
        $bytes[-1] = \chr(\ord($bytes[-1]) ^ 1);

        $this->expectException(DecryptionFailedException::class);
        $this->kms->decrypt(new Ciphertext(base64_encode($bytes), $this->keyId));
    }

    public function testGeneratedDataKeyUnwrapsToTheSameBytes()
    {
        $dataKey = $this->kms->generateDataKey($this->keyId);
        $plaintext = $dataKey->use(static fn (string $key): string => $key);

        $this->assertSame(32, \strlen($plaintext));
        $this->assertSame($plaintext, $this->kms->unwrapDataKey($dataKey->wrapped)->use(static fn (string $key): string => $key));
    }

    public function testGeneratedDataKeyWithAadIsBoundToIt()
    {
        $dataKey = $this->kms->generateDataKey($this->keyId, 32, 'column=email');
        $plaintext = $dataKey->use(static fn (string $key): string => $key);

        $this->assertSame($plaintext, $this->kms->unwrapDataKey($dataKey->wrapped, 'column=email')->use(static fn (string $key): string => $key));

        $this->expectException(DecryptionFailedException::class);
        $this->kms->unwrapDataKey($dataKey->wrapped, 'column=phone');
    }

    public function testEnvelopeEncryptionRoundTripThroughTheFactory()
    {
        $kms = (new ScalewayKmsFactory())->create(Dsn::fromString(\sprintf(
            'scaleway-kms://%s@default?region=%s',
            rawurlencode(getenv('SCALEWAY_KMS_TEST_SECRET_KEY')),
            getenv('SCALEWAY_KMS_TEST_REGION'),
        )));
        $envelopes = new EnvelopeEncrypter($kms);
        $payload = random_bytes(200_000);

        $envelope = $envelopes->encrypt($this->keyId, $payload, 'file=report.pdf');

        $this->assertSame($payload, $envelopes->decrypt(Envelope::fromBytes((string) $envelope), 'file=report.pdf'));
    }

    public function testUnknownKeyOnEncryptIsReportedAsKeyNotFound()
    {
        $this->expectException(KeyNotFoundException::class);
        $this->kms->encrypt(self::UNKNOWN_KEY_ID, 'hello');
    }

    public function testUnknownKeyOnDecryptIsMaskedAsDecryptionFailure()
    {
        $ciphertext = $this->kms->encrypt($this->keyId, 'hello');

        $this->expectException(DecryptionFailedException::class);
        $this->kms->decrypt(new Ciphertext($ciphertext->blob, self::UNKNOWN_KEY_ID));
    }

    public function testCiphertextFromAnotherRegionFailsToDecrypt()
    {
        $region = getenv('SCALEWAY_KMS_TEST_REGION');
        $ciphertext = $this->kms->encrypt($this->keyId, 'hello');
        $kms = $this->createKms(getenv('SCALEWAY_KMS_TEST_SECRET_KEY'), 'fr-par' === $region ? 'nl-ams' : 'fr-par');

        $this->expectException(DecryptionFailedException::class);
        $kms->decrypt($ciphertext);
    }

    public function testAsymmetricKeyRefusesAadInsteadOfIgnoringIt()
    {
        if (!$asymmetricKeyId = getenv('SCALEWAY_KMS_TEST_ASYMMETRIC_KEY_ID')) {
            $this->markTestSkipped('SCALEWAY_KMS_TEST_ASYMMETRIC_KEY_ID must be set to the id of an "asymmetric_encryption" key.');
        }

        $this->assertSame('hello', $this->kms->decrypt($this->kms->encrypt($asymmetricKeyId, 'hello')));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/HTTP 412.*asymmetric encryption does not support associated data/');
        $this->kms->encrypt($asymmetricKeyId, 'hello', 'tenant=42');
    }

    public function testAsymmetricKeyGeneratesDataKeysOfAnyLength()
    {
        if (!$asymmetricKeyId = getenv('SCALEWAY_KMS_TEST_ASYMMETRIC_KEY_ID')) {
            $this->markTestSkipped('SCALEWAY_KMS_TEST_ASYMMETRIC_KEY_ID must be set to the id of an "asymmetric_encryption" key.');
        }

        foreach ([16, 32, 64] as $length) {
            $dataKey = $this->kms->generateDataKey($asymmetricKeyId, $length);
            $plaintext = $dataKey->use(static fn (string $key): string => $key);

            $this->assertSame($length, \strlen($plaintext));
            $this->assertSame($plaintext, $this->kms->unwrapDataKey($dataKey->wrapped)->use(static fn (string $key): string => $key));
        }
    }

    public function testInvalidSecretKeyIsNotMaskedOnDecrypt()
    {
        $ciphertext = $this->kms->encrypt($this->keyId, 'hello');
        $kms = $this->createKms('00000000-0000-4000-8000-000000000000', getenv('SCALEWAY_KMS_TEST_REGION'));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/HTTP 40[13]/');
        $kms->decrypt($ciphertext);
    }

    private function createKms(string $secretKey, string $region): ScalewayKms
    {
        return (new ScalewayKmsFactory())->create(Dsn::fromString(\sprintf('scaleway-kms://%s@default?region=%s', rawurlencode($secretKey), $region)));
    }
}
