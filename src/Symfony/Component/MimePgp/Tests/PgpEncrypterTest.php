<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\MimePgp\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Message;
use Symfony\Component\MimePgp\Mime\Part\Multipart\PgpEncryptedPart;
use Symfony\Component\MimePgp\Mime\Part\PgpEncryptedInitializationPart;
use Symfony\Component\MimePgp\Mime\Part\PgpEncryptedMessagePart;
use Symfony\Component\MimePgp\PgpEncrypter;
use Symfony\Component\MimePgp\PgpProcess;

class PgpEncrypterTest extends TestCase
{
    private const KEY_EMAIL_ADDRESS = 'pgp@pulli.dev';

    private const KEY_PASSWORD = 'test1234';

    public function testPgpProcessCanEncryptCorrectly()
    {
        // Given
        $process = new PgpProcess();
        $tester = new PgpTestingProcess();

        // When
        $output = $process->encrypt('Hello there!', [self::KEY_EMAIL_ADDRESS => __DIR__.'/_data/pgp_test_public_key.asc']);

        // Then
        $decrypted = $tester->decrypt($output, __DIR__.'/_data/pgp_test_secret_key.asc', self::KEY_PASSWORD);
        $this->assertSame('Hello there!', $decrypted);
    }

    public function testEncrypting()
    {
        // Given
        $encrypter = new PgpEncrypter([
            self::KEY_EMAIL_ADDRESS => __DIR__.'/_data/pgp_test_public_key.asc',
        ]);

        $email = (new Email())
            ->from(new Address(static::KEY_EMAIL_ADDRESS, 'PuLLi'))
            ->to(new Address(static::KEY_EMAIL_ADDRESS, 'PuLLi'))
            ->text("Hello there!\n\nHow are you?")
            ->subject('PGP Mail');

        // When
        $encrypted = $encrypter->encrypt($email);

        // Then
        $this->checkEncryptedMessage($encrypted);

        $encryptedString = $encrypted->toString();

        $this->assertStringContainsString('-----BEGIN PGP MESSAGE-----', $encryptedString, 'PGP message begin is missing.');
        $this->assertStringContainsString('-----END PGP MESSAGE-----', $encryptedString, 'PGP message end is missing.');

        [$initiliazationPart, $encryptedMessagePart] = $encrypted->getBody()->getParts();
        static::assertInstanceOf(PgpEncryptedInitializationPart::class, $initiliazationPart);
        static::assertInstanceOf(PgpEncryptedMessagePart::class, $encryptedMessagePart);

        $tester = new PgpTestingProcess();
        $result = $tester->decrypt($encryptedMessagePart->toString(), __DIR__.'/_data/pgp_test_secret_key.asc', self::KEY_PASSWORD);
        $this->assertStringContainsString('Hello there!', $result, 'Unable to decrypt message.');
    }

    public function testEncryptingAndSigning()
    {
        $encrypter = new PgpEncrypter([
            self::KEY_EMAIL_ADDRESS => __DIR__.'/_data/pgp_test_public_key.asc',
        ]);

        $email = (new Email())
            ->from(new Address(static::KEY_EMAIL_ADDRESS, 'PuLLi'))
            ->to(new Address(static::KEY_EMAIL_ADDRESS, 'PuLLi'))
            ->text("Hello there!\n\nHow are you?")
            ->subject('PGP Mail');

        // When
        $encrypted = $encrypter->encrypt($email);

        // Then
        $this->checkEncryptedMessage($encrypted);

        $encryptedMessageString = $encrypted->toString();

        $this->assertStringContainsString('-----BEGIN PGP MESSAGE-----', $encryptedMessageString, 'PGP message begin is missing.');
        $this->assertStringContainsString('-----END PGP MESSAGE-----', $encryptedMessageString, 'PGP message end is missing.');
        $this->assertStringNotContainsString('-----BEGIN PGP SIGNATURE-----', $encryptedMessageString, 'PGP Signature begin is present.');
        $this->assertStringNotContainsString('-----END PGP SIGNATURE-----', $encryptedMessageString, 'PGP Signature end is present.');

        [$initiliazationPart, $encryptedMessagePart] = $encrypted->getBody()->getParts();
        static::assertInstanceOf(PgpEncryptedInitializationPart::class, $initiliazationPart);
        static::assertInstanceOf(PgpEncryptedMessagePart::class, $encryptedMessagePart);

        $tester = new PgpTestingProcess();
        $result = $tester->decrypt($encryptedMessagePart->toString(), __DIR__.'/_data/pgp_test_secret_key.asc', self::KEY_PASSWORD);
        $this->assertStringContainsString('Hello there!', $result, 'Signature is not valid.');
    }

    private function checkEncryptedMessage(Message $message): void
    {
        $body = $message->getBody();

        $this->assertInstanceOf(PgpEncryptedPart::class, $body, 'Message body is not encrypted.');

        [$initializationPart, $messagePart] = $body->getParts();

        $this->assertInstanceOf(PgpEncryptedInitializationPart::class, $initializationPart, 'Is not a PGP Initialization part.');
        $this->assertInstanceOf(PgpEncryptedMessagePart::class, $messagePart, 'Is not a PGP Message part.');
    }

    private function normalize(string $part): string
    {
        return str_replace("\n", "\r\n", str_replace(["\r\n", "\r"], "\n", $part));
    }
}
