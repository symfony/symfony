<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\KeyManagement\Bridge\AwsKms\Tests;

use AsyncAws\Core\Test\ResultMockFactory;
use AsyncAws\Kms\Exception\IncorrectKeyException;
use AsyncAws\Kms\Exception\InvalidCiphertextException;
use AsyncAws\Kms\Exception\NotFoundException;
use AsyncAws\Kms\Input\DecryptRequest;
use AsyncAws\Kms\Input\EncryptRequest;
use AsyncAws\Kms\Input\GenerateDataKeyRequest;
use AsyncAws\Kms\KmsClient;
use AsyncAws\Kms\Result\DecryptResponse;
use AsyncAws\Kms\Result\EncryptResponse;
use AsyncAws\Kms\Result\GenerateDataKeyResponse;
use PHPUnit\Framework\TestCase;
use Symfony\Component\KeyManagement\Bridge\AwsKms\AwsKms;
use Symfony\Component\KeyManagement\Ciphertext;
use Symfony\Component\KeyManagement\Exception\DecryptionFailedException;
use Symfony\Component\KeyManagement\Exception\InvalidArgumentException;
use Symfony\Component\KeyManagement\Exception\KeyNotFoundException;
use Symfony\Component\KeyManagement\Exception\RuntimeException;
use Symfony\Component\KeyManagement\Exception\UnsupportedOperationException;

class AwsKmsTest extends TestCase
{
    public function testEncryptForwardsKeyIdAndPlaintextAndReturnsTheBlob()
    {
        $captured = null;
        $client = $this->createMock(KmsClient::class);
        $client->expects($this->once())
            ->method('encrypt')
            ->willReturnCallback(static function (EncryptRequest $request) use (&$captured): EncryptResponse {
                $captured = $request;

                return ResultMockFactory::create(EncryptResponse::class, [
                    'CiphertextBlob' => 'binary-blob',
                    'KeyId' => 'arn:aws:kms:eu-west-1:111:key/abc',
                ]);
            });

        $ciphertext = (new AwsKms($client))->encrypt('alias/app-key', 'hello');

        $this->assertSame('alias/app-key', $captured->getKeyId());
        $this->assertSame('hello', $captured->getPlaintext());
        $this->assertSame([], $captured->getEncryptionContext());

        $this->assertSame('alias/app-key', $ciphertext->keyId);
        $this->assertSame('binary-blob', $ciphertext->blob);
    }

    public function testEncryptForwardsAadAsBase64EncryptionContextEntry()
    {
        $captured = null;
        $client = $this->createStub(KmsClient::class);
        $client->method('encrypt')
            ->willReturnCallback(static function (EncryptRequest $request) use (&$captured): EncryptResponse {
                $captured = $request;

                return ResultMockFactory::create(EncryptResponse::class, [
                    'CiphertextBlob' => 'blob',
                    'KeyId' => 'app-key',
                ]);
            });

        (new AwsKms($client))->encrypt('app-key', 'hello', 'opaque-aad');

        $this->assertSame(['aad' => base64_encode('opaque-aad')], $captured->getEncryptionContext());
    }

    public function testEncryptOnNotFoundIsAKeyNotFound()
    {
        $client = $this->createStub(KmsClient::class);
        $client->method('encrypt')
            ->willThrowException(self::makeAwsException(NotFoundException::class));

        $this->expectException(KeyNotFoundException::class);
        (new AwsKms($client))->encrypt('alias/missing', 'hello');
    }

    public function testEncryptWrapsGenericHttpErrorsAsRuntimeException()
    {
        $client = $this->createStub(KmsClient::class);
        $client->method('encrypt')
            ->willThrowException(self::makeAwsException(\AsyncAws\Core\Exception\Http\ServerException::class, 'KMS internal'));

        $this->expectException(RuntimeException::class);
        (new AwsKms($client))->encrypt('alias/app-key', 'hello');
    }

    public function testDecryptForwardsKeyIdAndCiphertextAndReturnsPlaintext()
    {
        $captured = null;
        $client = $this->createStub(KmsClient::class);
        $client->method('decrypt')
            ->willReturnCallback(static function (DecryptRequest $request) use (&$captured): DecryptResponse {
                $captured = $request;

                return ResultMockFactory::create(DecryptResponse::class, [
                    'Plaintext' => 'hello',
                    'KeyId' => 'arn:aws:kms:eu-west-1:111:key/abc',
                ]);
            });

        $plaintext = (new AwsKms($client))->decrypt(new Ciphertext('binary-blob', 'alias/app-key'));

        $this->assertSame('alias/app-key', $captured->getKeyId());
        $this->assertSame('binary-blob', $captured->getCiphertextBlob());
        $this->assertSame('hello', $plaintext);
    }

    public function testDecryptOnNotFoundIsMaskedAsDecryptionFailure()
    {
        $client = $this->createStub(KmsClient::class);
        $client->method('decrypt')
            ->willThrowException(self::makeAwsException(NotFoundException::class));

        $this->expectException(DecryptionFailedException::class);
        (new AwsKms($client))->decrypt(new Ciphertext('blob', 'alias/missing'));
    }

    public function testDecryptOnInvalidCiphertextIsADecryptionFailure()
    {
        $client = $this->createStub(KmsClient::class);
        $client->method('decrypt')
            ->willThrowException(self::makeAwsException(InvalidCiphertextException::class));

        $this->expectException(DecryptionFailedException::class);
        (new AwsKms($client))->decrypt(new Ciphertext('tampered', 'alias/app-key'));
    }

    public function testDecryptOnIncorrectKeyIsADecryptionFailure()
    {
        $client = $this->createStub(KmsClient::class);
        $client->method('decrypt')
            ->willThrowException(self::makeAwsException(IncorrectKeyException::class));

        $this->expectException(DecryptionFailedException::class);
        (new AwsKms($client))->decrypt(new Ciphertext('blob-from-other-key', 'alias/app-key'));
    }

    public function testDecryptDoesNotMaskGenericServerErrors()
    {
        $client = $this->createStub(KmsClient::class);
        $client->method('decrypt')
            ->willThrowException(self::makeAwsException(\AsyncAws\Core\Exception\Http\ServerException::class, 'KMS internal'));

        $this->expectException(RuntimeException::class);
        (new AwsKms($client))->decrypt(new Ciphertext('blob', 'alias/app-key'));
    }

    public function testGenerateDataKeyReturnsBothPlaintextAndWrapped()
    {
        $captured = null;
        $client = $this->createStub(KmsClient::class);
        $client->method('generateDataKey')
            ->willReturnCallback(static function (GenerateDataKeyRequest $request) use (&$captured): GenerateDataKeyResponse {
                $captured = $request;

                return ResultMockFactory::create(GenerateDataKeyResponse::class, [
                    'CiphertextBlob' => 'wrapped-dek',
                    'Plaintext' => str_repeat("\xAA", 32),
                    'KeyId' => 'arn:aws:kms:eu-west-1:111:key/abc',
                ]);
            });

        $dataKey = (new AwsKms($client))->generateDataKey('alias/app-key', 32);

        $this->assertSame('alias/app-key', $captured->getKeyId());
        $this->assertSame(32, $captured->getNumberOfBytes());
        $this->assertSame('alias/app-key', $dataKey->wrapped->keyId);
        $this->assertSame('wrapped-dek', $dataKey->wrapped->blob);
        $this->assertSame(str_repeat("\xAA", 32), $dataKey->use(static fn (string $p): string => $p));
    }

    public function testGenerateDataKeyRejectsTooShortLength()
    {
        $client = $this->createStub(KmsClient::class);
        $client->method('generateDataKey')->willThrowException(new \LogicException('AWS must not be reached for a length no other backend accepts.'));

        $this->expectException(InvalidArgumentException::class);
        (new AwsKms($client))->generateDataKey('alias/app-key', 8);
    }

    public function testGenerateDataKeyOnNotFoundIsAKeyNotFound()
    {
        $client = $this->createStub(KmsClient::class);
        $client->method('generateDataKey')
            ->willThrowException(self::makeAwsException(NotFoundException::class));

        $this->expectException(KeyNotFoundException::class);
        (new AwsKms($client))->generateDataKey('alias/missing');
    }

    public function testDeterministicModeIsRejected()
    {
        $client = $this->createStub(KmsClient::class);

        $this->expectException(UnsupportedOperationException::class);
        (new AwsKms($client))->encrypt('alias/app-key', 'hello', deterministic: true);
    }

    public function testUnwrapDataKeyRoutesThroughDecrypt()
    {
        $client = $this->createStub(KmsClient::class);
        $client->method('decrypt')
            ->willReturn(ResultMockFactory::create(DecryptResponse::class, [
                'Plaintext' => str_repeat("\xCC", 32),
                'KeyId' => 'alias/app-key',
            ]));

        $dataKey = (new AwsKms($client))->unwrapDataKey(new Ciphertext('wrapped-dek', 'alias/app-key'));

        $this->assertSame(str_repeat("\xCC", 32), $dataKey->use(static fn (string $p): string => $p));
        $this->assertSame('wrapped-dek', $dataKey->wrapped->blob);
    }

    /**
     * @template T of \Throwable
     *
     * @param class-string<T> $class
     *
     * @return T
     */
    private static function makeAwsException(string $class, string $message = ''): \Throwable
    {
        $reflection = new \ReflectionClass($class);
        $exception = $reflection->newInstanceWithoutConstructor();

        if ('' !== $message) {
            $messageProperty = (new \ReflectionClass(\Exception::class))->getProperty('message');
            $messageProperty->setValue($exception, $message);
        }

        return $exception;
    }
}
