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
use Symfony\Component\Mime\Header\Headers;
use Symfony\Component\Mime\Message;
use Symfony\Component\Mime\Part\DataPart;
use Symfony\Component\Mime\Part\TextPart;

/**
 * @requires extension openssl
 */
class SMimeSignerTest extends SMimeTestCase
{
    public function testSignedMessage()
    {
        $message = new Message(
            (new Headers())
                ->addDateHeader('Date', new \DateTimeImmutable('2019-04-07 10:36:30', new \DateTimeZone('Europe/Paris')))
                ->addMailboxListHeader('From', ['fabien@symfony.com']),
            new TextPart('content')
        );

        $signer = new SMimeSigner($this->samplesDir.'sign.crt', $this->samplesDir.'sign.key');
        $signedMessage = $signer->sign($message);

        $this->assertMessageSignatureIsValid($signedMessage, $message);
    }

    public function testSignEncryptedMessage()
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

        $signer = new SMimeSigner($this->samplesDir.'sign.crt', $this->samplesDir.'sign.key');
        $signedMessage = $signer->sign($encryptedMessage);

        $this->assertMessageSignatureIsValid($signedMessage, $message);
    }

    public function testSignedMessageWithPassphrase()
    {
        $message = new Message(
            (new Headers())
                ->addDateHeader('Date', new \DateTimeImmutable('2019-04-07 10:36:30', new \DateTimeZone('Europe/Paris')))
                ->addMailboxListHeader('From', ['fabien@symfony.com']),
            new TextPart('content')
        );

        $signer = new SMimeSigner($this->samplesDir.'sign3.crt', $this->samplesDir.'sign3.key', 'symfony-rocks');
        $signedMessage = $signer->sign($message);

        $this->assertMessageSignatureIsValid($signedMessage, $message);
    }

    public function testProperSerialiable()
    {
        $message = (new Email())
            ->date(new \DateTimeImmutable('2019-04-07 10:36:30', new \DateTimeZone('Europe/Paris')))
            ->to('fabien@symfony.com')
            ->subject('Testing')
            ->from('noreply@example.com')
            ->text('El Barto was not here');

        $message->getHeaders()->addIdHeader('Message-ID', 'some@id');

        $signer = new SMimeSigner($this->samplesDir.'sign.crt', $this->samplesDir.'sign.key');
        $signedMessage = $signer->sign($message);

        $restoredMessage = unserialize(serialize($signedMessage));

        self::assertSame($this->iterableToString($signedMessage->toIterable()), $this->iterableToString($restoredMessage->toIterable()));
        self::assertSame($signedMessage->toString(), $restoredMessage->toString());

        $this->assertMessageSignatureIsValid($restoredMessage, $message);
    }

    public function testSignedMessageWithBcc()
    {
        $message = (new Email())
            ->date(new \DateTimeImmutable('2019-04-07 10:36:30', new \DateTimeZone('Europe/Paris')))
            ->to('fabien@symfony.com')
            ->addBcc('fabien@symfony.com', 's.stok@rollerscapes.net')
            ->subject('I am your sign of fear')
            ->from('noreply@example.com')
            ->text('El Barto was not here');

        $signer = new SMimeSigner($this->samplesDir.'sign.crt', $this->samplesDir.'sign.key');
        $signedMessage = $signer->sign($message);

        $this->assertMessageSignatureIsValid($signedMessage, $message);
    }

    public function testSignedMessageWithAttachments()
    {
        $message = new Email((new Headers())
            ->addDateHeader('Date', new \DateTimeImmutable('2019-04-07 10:36:30', new \DateTimeZone('Europe/Paris')))
            ->addMailboxListHeader('From', ['fabien@symfony.com'])
            ->addMailboxListHeader('To', ['fabien@symfony.com'])
        );
        $message->html('html content <img src="cid:test.gif">');
        $message->text('text content');
        $message->addPart(new DataPart(fopen(__DIR__.'/../Fixtures/mimetypes/test', 'r')));
        $message->addPart(new DataPart(fopen(__DIR__.'/../Fixtures/mimetypes/test.gif', 'r'), 'test.gif'));

        $signer = new SMimeSigner($this->samplesDir.'sign.crt', $this->samplesDir.'sign.key');

        $signedMessage = $signer->sign($message);
        $this->assertMessageSignatureIsValid($signedMessage, $message);
    }

    public function testSignedMessageExtraCerts()
    {
        $message = new Message(
            (new Headers())
                ->addDateHeader('Date', new \DateTimeImmutable('2019-04-07 10:36:30', new \DateTimeZone('Europe/Paris')))
                ->addMailboxListHeader('From', ['fabien@symfony.com']),
            new TextPart('content')
        );

        $signer = new SMimeSigner(
            $this->samplesDir.'sign.crt',
            $this->samplesDir.'sign.key',
            null,
            $this->samplesDir.'intermediate.crt',
            \PKCS7_DETACHED
        );
        $signedMessage = $signer->sign($message);

        $this->assertMessageSignatureIsValid($signedMessage, $message);
    }

    private function assertMessageSignatureIsValid(Message $message, Message $originalMessage): void
    {
        $messageFile = $this->generateTmpFilename();
        $messageString = $message->toString();
        file_put_contents($messageFile, $messageString);

        $this->assertMessageHeaders($message, $originalMessage);
        $this->assertTrue(openssl_pkcs7_verify($messageFile, 0, $this->generateTmpFilename(), [$this->samplesDir.'ca.crt']), sprintf('Verification of the message %s failed. Internal error "%s".', $messageFile, openssl_error_string()));

        if (!str_contains($messageString, 'enveloped-data')) {
            // Tamper to ensure it actually verified
            file_put_contents($messageFile, str_replace('Content-Transfer-Encoding: ', 'Content-Transfer-Encoding:  ', $messageString));
            $this->assertFalse(openssl_pkcs7_verify($messageFile, 0, $this->generateTmpFilename(), [$this->samplesDir.'ca.crt']), sprintf('Verification of the message failed. Internal error "%s".', openssl_error_string()));
        }
    }
}
