<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mime\Tests\Crypto;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Crypto\PgpEncrypter;
use Symfony\Component\Mime\Crypto\PgpProcess;
use Symfony\Component\Mime\Crypto\PgpSigner;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Exception\RuntimeException;
use Symfony\Component\Mime\Message;
use Symfony\Component\Mime\Part\Multipart\PgpEncryptedPart;
use Symfony\Component\Mime\Part\PgpEncryptedInitializationPart;
use Symfony\Component\Mime\Part\PgpEncryptedMessagePart;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

class PgpEncrypterTest extends TestCase
{
    private const KEY_EMAIL_ADDRESS = 'pgp@pulli.dev';

    private const KEY_PASSWORD = 'test1234';

    protected function setUp(): void
    {
        if (!(new ExecutableFinder())->find('gpg')) {
            $this->markTestSkipped('The "gpg" binary is not available.');
        }
    }

    public function testPgpProcessCanEncryptCorrectly()
    {
        $process = new PgpProcess();
        $tester = new PgpTestingProcess();

        $output = $process->encrypt('Hello there!', [self::KEY_EMAIL_ADDRESS => __DIR__.'/../Fixtures/pgp_test_public_key.asc']);

        $decrypted = $tester->decrypt($output, __DIR__.'/../Fixtures/pgp_test_secret_key.asc', self::KEY_PASSWORD);
        $this->assertSame('Hello there!', $decrypted);
    }

    public function testKeyPathIsNeverParsedAsAGpgOption()
    {
        $process = new PgpProcess();

        try {
            $process->encrypt('Hello there!', [self::KEY_EMAIL_ADDRESS => '--version']);
            $this->fail('Importing the key should have failed.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('--import', $e->getMessage());
        }
    }

    public function testKeyFileWithSeveralKeysIsRejected()
    {
        $keyFile = tempnam(sys_get_temp_dir(), 'pgp');
        file_put_contents($keyFile, file_get_contents(__DIR__.'/../Fixtures/pgp_test_public_key.asc').file_get_contents(__DIR__.'/../Fixtures/pgp_two_user_ids_public_key.asc'));

        try {
            (new PgpProcess())->encrypt('Hello there!', [self::KEY_EMAIL_ADDRESS => $keyFile]);
            $this->fail('A key file with several keys should have been rejected.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('must hold exactly one key, 2 found', $e->getMessage());
        } finally {
            unlink($keyFile);
        }
    }

    public function testKeyWithoutAUserIdForTheRecipientIsRejected()
    {
        try {
            (new PgpProcess())->encrypt('Hello there!', ['someone@example.com' => __DIR__.'/../Fixtures/pgp_test_public_key.asc']);
            $this->fail('A key without a user ID for the recipient should have been rejected.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('has no user ID for "someone@example.com"', $e->getMessage());
        }
    }

    public function testRevokedUserIdIsNotAUserIdForTheRecipient()
    {
        try {
            (new PgpProcess())->encrypt('Hello there!', ['revoked@example.com' => __DIR__.'/../Fixtures/pgp_user_id_comment_and_revoked_public_key.asc']);
            $this->fail('A key whose user ID for the recipient is revoked should have been rejected.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('has no user ID for "revoked@example.com"', $e->getMessage());
        }
    }

    public function testUserIdWithACommentAfterTheAddressMatchesTheRecipient()
    {
        $output = (new PgpProcess())->encrypt('Hello there!', ['comment@example.com' => __DIR__.'/../Fixtures/pgp_user_id_comment_and_revoked_public_key.asc']);

        $this->assertStringContainsString('-----BEGIN PGP MESSAGE-----', $output);
    }

    public function testEachRecipientIsEncryptedToTheKeyOfItsOwnFile()
    {
        // This newer key also carries the "pgp@pulli.dev" user ID.
        $recipientKeys = [
            'mallory@example.com' => __DIR__.'/../Fixtures/pgp_two_user_ids_public_key.asc',
            self::KEY_EMAIL_ADDRESS => __DIR__.'/../Fixtures/pgp_test_public_key.asc',
        ];
        $email = (new Email())
            ->from(new Address(self::KEY_EMAIL_ADDRESS, 'PuLLi'))
            ->to(new Address('mallory@example.com'))
            ->bcc(new Address(self::KEY_EMAIL_ADDRESS, 'PuLLi'))
            ->text('Secret content')
            ->subject('PGP Mail');

        $encrypted = (new PgpEncrypter())->encrypt($email, $recipientKeys);
        $messagePart = $encrypted->getBody()->getParts()[1]->toString();

        $tester = new PgpTestingProcess();
        $decrypted = $tester->decrypt($messagePart, __DIR__.'/../Fixtures/pgp_test_secret_key.asc', self::KEY_PASSWORD);
        $this->assertStringContainsString('Secret content', $decrypted);
    }

    public function testBccRecipientSharingAToRecipientKeyLeavesItsKeyIdVisible()
    {
        $recipientKeys = [
            'mallory@example.com' => __DIR__.'/../Fixtures/pgp_two_user_ids_public_key.asc',
            self::KEY_EMAIL_ADDRESS => __DIR__.'/../Fixtures/pgp_two_user_ids_public_key.asc',
        ];
        $email = (new Email())
            ->from(new Address(self::KEY_EMAIL_ADDRESS, 'PuLLi'))
            ->to(new Address('mallory@example.com'))
            ->bcc(new Address(self::KEY_EMAIL_ADDRESS, 'PuLLi'))
            ->text('Secret content')
            ->subject('PGP Mail');

        $encrypted = (new PgpEncrypter())->encrypt($email, $recipientKeys);
        $messagePart = $encrypted->getBody()->getParts()[1]->toString();

        $this->assertStringNotContainsString('keyid 0000000000000000', $this->listPackets($messagePart), 'A Bcc recipient must not change how a To recipient key ID is shown.');
    }

    public function testTimeoutThrowsAMimeException()
    {
        if ('\\' === \DIRECTORY_SEPARATOR) {
            $this->markTestSkipped('This test uses a shell script as the gpg binary.');
        }

        $binary = tempnam(sys_get_temp_dir(), 'gpg');
        file_put_contents($binary, "#!/bin/sh\nexec sleep 10\n");
        chmod($binary, 0o755);
        $process = PgpProcess::fromOptions(['binary' => $binary, 'timeout' => 0.1]);

        try {
            $process->encrypt('Hello there!', [self::KEY_EMAIL_ADDRESS => __DIR__.'/../Fixtures/pgp_test_public_key.asc']);
            $this->fail('The gpg process should have timed out.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('exceeded the timeout', $e->getMessage());
        } finally {
            unlink($binary);
        }
    }

    public function testEncrypting()
    {
        $recipientKeys = [
            self::KEY_EMAIL_ADDRESS => __DIR__.'/../Fixtures/pgp_test_public_key.asc',
        ];
        $encrypter = new PgpEncrypter();

        $email = (new Email())
            ->from(new Address(static::KEY_EMAIL_ADDRESS, 'PuLLi'))
            ->to(new Address(static::KEY_EMAIL_ADDRESS, 'PuLLi'))
            ->text("Hello there!\n\nHow are you?")
            ->subject('PGP Mail');

        $encrypted = $encrypter->encrypt($email, $recipientKeys);

        $this->checkEncryptedMessage($encrypted);

        $encryptedString = $encrypted->toString();

        $this->assertStringContainsString('-----BEGIN PGP MESSAGE-----', $encryptedString, 'PGP message begin is missing.');
        $this->assertStringContainsString('-----END PGP MESSAGE-----', $encryptedString, 'PGP message end is missing.');

        [$initiliazationPart, $encryptedMessagePart] = $encrypted->getBody()->getParts();
        static::assertInstanceOf(PgpEncryptedInitializationPart::class, $initiliazationPart);
        static::assertInstanceOf(PgpEncryptedMessagePart::class, $encryptedMessagePart);

        $tester = new PgpTestingProcess();
        $result = $tester->decrypt($encryptedMessagePart->toString(), __DIR__.'/../Fixtures/pgp_test_secret_key.asc', self::KEY_PASSWORD);
        $this->assertStringContainsString('Hello there!', $result, 'Unable to decrypt message.');
    }

    public function testEncryptingAndSigning()
    {
        $recipientKeys = [
            self::KEY_EMAIL_ADDRESS => __DIR__.'/../Fixtures/pgp_test_public_key.asc',
        ];
        $encrypter = new PgpEncrypter();

        $email = (new Email())
            ->from(new Address(static::KEY_EMAIL_ADDRESS, 'PuLLi'))
            ->to(new Address(static::KEY_EMAIL_ADDRESS, 'PuLLi'))
            ->text("Hello there!\n\nHow are you?")
            ->subject('PGP Mail');

        $encrypted = $encrypter->encrypt($email, $recipientKeys);

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
        $result = $tester->decrypt($encryptedMessagePart->toString(), __DIR__.'/../Fixtures/pgp_test_secret_key.asc', self::KEY_PASSWORD);
        $this->assertStringContainsString('Hello there!', $result, 'Signature is not valid.');
    }

    public function testToRecipientKeyIdIsVisibleByDefault()
    {
        $recipientKeys = [
            self::KEY_EMAIL_ADDRESS => __DIR__.'/../Fixtures/pgp_test_public_key.asc',
        ];
        $email = (new Email())
            ->from(new Address(self::KEY_EMAIL_ADDRESS, 'PuLLi'))
            ->to(new Address(self::KEY_EMAIL_ADDRESS, 'PuLLi'))
            ->text('Secret content')
            ->subject('PGP Mail');

        $encrypted = (new PgpEncrypter())->encrypt($email, $recipientKeys);
        $messagePart = $encrypted->getBody()->getParts()[1]->toString();

        $this->assertStringNotContainsString('keyid 0000000000000000', $this->listPackets($messagePart), 'A To recipient key ID should be visible by default.');
    }

    public function testBccRecipientKeyIdIsHiddenByDefault()
    {
        $recipientKeys = [
            self::KEY_EMAIL_ADDRESS => __DIR__.'/../Fixtures/pgp_test_public_key.asc',
        ];
        $email = (new Email())
            ->from(new Address(self::KEY_EMAIL_ADDRESS, 'PuLLi'))
            ->to(new Address('someone-else@example.com'))
            ->bcc(new Address(self::KEY_EMAIL_ADDRESS, 'PuLLi'))
            ->text('Secret content')
            ->subject('PGP Mail');

        $encrypted = (new PgpEncrypter())->encrypt($email, $recipientKeys);
        $messagePart = $encrypted->getBody()->getParts()[1]->toString();

        $this->assertStringContainsString('keyid 0000000000000000', $this->listPackets($messagePart), 'A Bcc recipient key ID must be hidden.');

        $tester = new PgpTestingProcess();
        $decrypted = $tester->decrypt($messagePart, __DIR__.'/../Fixtures/pgp_test_secret_key.asc', self::KEY_PASSWORD);
        $this->assertStringContainsString('Secret content', $decrypted, 'The message with a hidden Bcc recipient is not decryptable.');
    }

    public function testBccRecipientKeyIdIsHiddenWhenTheMessageWasSignedFirst()
    {
        $recipientKeys = [
            self::KEY_EMAIL_ADDRESS => __DIR__.'/../Fixtures/pgp_test_public_key.asc',
        ];
        $email = (new Email())
            ->from(new Address(self::KEY_EMAIL_ADDRESS, 'PuLLi'))
            ->to(new Address('someone-else@example.com'))
            ->bcc(new Address(self::KEY_EMAIL_ADDRESS, 'PuLLi'))
            ->text('Secret content')
            ->subject('PGP Mail');

        $signer = new PgpSigner(
            __DIR__.'/../Fixtures/pgp_test_secret_key.asc',
            __DIR__.'/../Fixtures/pgp_test_public_key.asc',
            self::KEY_PASSWORD
        );
        $signed = $signer->sign($email);

        $encrypted = (new PgpEncrypter())->encrypt($signed, $recipientKeys);
        $messagePart = $encrypted->getBody()->getParts()[1]->toString();

        $this->assertStringContainsString('keyid 0000000000000000', $this->listPackets($messagePart), 'A Bcc recipient key ID must be hidden on a signed message too.');
    }

    public function testHideRecipientsOptionHidesToRecipients()
    {
        $recipientKeys = [
            self::KEY_EMAIL_ADDRESS => __DIR__.'/../Fixtures/pgp_test_public_key.asc',
        ];
        $email = (new Email())
            ->from(new Address(self::KEY_EMAIL_ADDRESS, 'PuLLi'))
            ->to(new Address(self::KEY_EMAIL_ADDRESS, 'PuLLi'))
            ->text('Secret content')
            ->subject('PGP Mail');

        $encrypted = (new PgpEncrypter(['hide_recipients' => true]))->encrypt($email, $recipientKeys);
        $messagePart = $encrypted->getBody()->getParts()[1]->toString();

        $this->assertStringContainsString('keyid 0000000000000000', $this->listPackets($messagePart), 'With hide_recipients, a To recipient key ID must be hidden.');
    }

    public function testEncryptingAPreviouslySignedMessage()
    {
        $recipientKeys = [
            self::KEY_EMAIL_ADDRESS => __DIR__.'/../Fixtures/pgp_test_public_key.asc',
        ];

        $email = (new Email())
            ->from(new Address(self::KEY_EMAIL_ADDRESS, 'PuLLi'))
            ->to(new Address(self::KEY_EMAIL_ADDRESS, 'PuLLi'))
            ->text("Hello there!\n\nHow are you?")
            ->subject('PGP Mail');

        $signer = new PgpSigner(
            __DIR__.'/../Fixtures/pgp_test_secret_key.asc',
            __DIR__.'/../Fixtures/pgp_test_public_key.asc',
            self::KEY_PASSWORD,
        );
        $signed = $signer->sign($email);

        $encrypter = new PgpEncrypter();
        $encrypted = $encrypter->encrypt($signed, $recipientKeys);

        $this->checkEncryptedMessage($encrypted);

        [$initializationPart, $encryptedMessagePart] = $encrypted->getBody()->getParts();
        static::assertInstanceOf(PgpEncryptedInitializationPart::class, $initializationPart);
        static::assertInstanceOf(PgpEncryptedMessagePart::class, $encryptedMessagePart);

        $tester = new PgpTestingProcess();
        $decrypted = $tester->decrypt($encryptedMessagePart->toString(), __DIR__.'/../Fixtures/pgp_test_secret_key.asc', self::KEY_PASSWORD);

        $this->assertStringContainsString('multipart/signed', $decrypted, 'The decrypted message is not a signed message.');
        $this->assertStringContainsString('application/pgp-signature', $decrypted, 'The decrypted message does not contain a PGP signature part.');
        $this->assertStringContainsString('-----BEGIN PGP SIGNATURE-----', $decrypted, 'The decrypted message does not contain a PGP signature.');
        $this->assertStringContainsString('Hello there!', $decrypted, 'The decrypted message does not contain the original content.');
    }

    private function checkEncryptedMessage(Message $message): void
    {
        $body = $message->getBody();

        $this->assertInstanceOf(PgpEncryptedPart::class, $body, 'Message body is not encrypted.');

        [$initializationPart, $messagePart] = $body->getParts();

        $this->assertInstanceOf(PgpEncryptedInitializationPart::class, $initializationPart, 'Is not a PGP Initialization part.');
        $this->assertInstanceOf(PgpEncryptedMessagePart::class, $messagePart, 'Is not a PGP Message part.');
    }

    private function listPackets(string $armored): string
    {
        $process = new Process(['gpg', '--list-packets']);
        $process->setInput($armored);
        $process->run();

        return $process->getOutput().$process->getErrorOutput();
    }
}
