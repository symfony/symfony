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

use Symfony\Component\Mime\Crypto\SMimeEncrypter;
use Symfony\Component\Mime\Crypto\SMimeSigner;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Message;

/**
 * @requires extension openssl
 */
class SMimeEncrypterTest extends SMimeTestCase
{
    public function testEncryptMessage()
    {
        $message = (new Email())
            ->date(new \DateTimeImmutable('2019-04-07 10:36:30', new \DateTimeZone('Europe/Paris')))
            ->to('fabien@symfony.com')
            ->subject('Testing')
            ->from('noreply@example.com')
            ->text('El Barto was not here');

        $message->getHeaders()->addIdHeader('Message-ID', 'some@id');

        $encrypter = new SMimeEncrypter($this->samplesDir.'encrypt.crt');
        $encryptedMessage = $encrypter->encrypt($message);

        $this->assertMessageIsEncryptedProperly($encryptedMessage, $message);
    }

    public function testEncryptSignedMessage()
    {
        $message = (new Email())
            ->date(new \DateTimeImmutable('2019-04-07 10:36:30', new \DateTimeZone('Europe/Paris')))
            ->to('fabien@symfony.com')
            ->bcc('luna@symfony.com')
            ->subject('Testing')
            ->from('noreply@example.com')
            ->text('El Barto was not here');

        $message->getHeaders()->addIdHeader('Message-ID', 'some@id');

        $signer = new SMimeSigner($this->samplesDir.'sign.crt', $this->samplesDir.'sign.key');
        $signedMessage = $signer->sign($message);

        $encrypter = new SMimeEncrypter($this->samplesDir.'encrypt.crt');
        $encryptedMessage = $encrypter->encrypt($signedMessage);

        $this->assertMessageIsEncryptedProperly($encryptedMessage, $signedMessage);
    }

    public function testEncryptMessageWithMultipleCerts()
    {
        $message = (new Email())
            ->date(new \DateTimeImmutable('2019-04-07 10:36:30', new \DateTimeZone('Europe/Paris')))
            ->to('fabien@symfony.com')
            ->subject('Testing')
            ->from('noreply@example.com')
            ->text('El Barto was not here');

        $message2 = (new Email())
            ->date(new \DateTimeImmutable('2019-04-07 10:36:30', new \DateTimeZone('Europe/Paris')))
            ->to('luna@symfony.com')
            ->subject('Testing')
            ->from('noreply@example.com')
            ->text('El Barto was not here');

        $message->getHeaders()->addIdHeader('Message-ID', 'some@id');
        $message2->getHeaders()->addIdHeader('Message-ID', 'some@id2');

        $encrypter = new SMimeEncrypter(['fabien@symfony.com' => $this->samplesDir.'encrypt.crt', 'luna@symfony.com' => $this->samplesDir.'encrypt2.crt']);

        $this->assertMessageIsEncryptedProperly($encrypter->encrypt($message), $message);
        $this->assertMessageIsEncryptedProperly($encrypter->encrypt($message2), $message2);
    }

    private function assertMessageIsEncryptedProperly(Message $message, Message $originalMessage): void
    {
        $messageFile = $this->generateTmpFilename();
        file_put_contents($messageFile, $messageString = $message->toString());

        // Ensure the proper line-ending is used for compatibility with the RFC
        $this->assertStringContainsString("\n\r", $messageString);
        $this->assertStringNotContainsString("\n\n", $messageString);

        $outputFile = $this->generateTmpFilename();

        $this->assertMessageHeaders($message, $originalMessage);
        $this->assertTrue(
            openssl_pkcs7_decrypt(
                $messageFile,
                $outputFile,
                'file://'.$this->samplesDir.'encrypt.crt',
                'file://'.$this->samplesDir.'encrypt.key'
            ),
            sprintf('Decryption of the message failed. Internal error "%s".', openssl_error_string())
        );
        $this->assertEquals(str_replace("\r", '', $originalMessage->toString()), str_replace("\r", '', file_get_contents($outputFile)));
    }
}
