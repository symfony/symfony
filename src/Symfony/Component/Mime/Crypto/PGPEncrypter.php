<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mime\Crypto;

use Symfony\Component\Mime\Header\Headers;
use Symfony\Component\Mime\Header\MailboxListHeader;
use Symfony\Component\Mime\Helper\PGPSigningPreparer;
use Symfony\Component\Mime\Message;
use Symfony\Component\Mime\Part\Multipart\MixedPart;
use Symfony\Component\Mime\Part\Multipart\PGPEncryptedPart;
use Symfony\Component\Mime\Part\Multipart\PGPSignedPart;
use Symfony\Component\Mime\Part\PGPEncryptedInitializationPart;
use Symfony\Component\Mime\Part\PGPEncryptedMessagePart;
use Symfony\Component\Mime\Part\PGPKeyPart;
use Symfony\Component\Mime\Part\PGPSignaturePart;

/*
 * @author PuLLi <the@pulli.dev>
 */
class PGPEncrypter
{
    use PGPSigningPreparer;

    private \Crypt_GPG $gpg;

    private Headers $headers;

    private ?string $signingKey = null;

    public string $signature = '';

    public string $signed = '';

    /**
     * @throws \Crypt_GPG_FileException
     * @throws \PEAR_Exception
     */
    public function __construct(array $options = [])
    {
        $this->gpg = new \Crypt_GPG(
            array_merge(
                $options,
                [
                    'cipher-algo' => 'AES256',
                    'digest-algo' => 'SHA512',
                ]
            )
        );
    }

    public function signingKey(string $keyIdentifier): void
    {
        $this->signingKey = $keyIdentifier;
    }

    /**
     * @throws \Crypt_GPG_Exception
     * @throws \Crypt_GPG_KeyNotFoundException
     */
    public function encrypt(Message $message, bool $attachKey = false): Message
    {
        return $this->encryptWithOrWithoutSigning($message, false, null, $attachKey);
    }

    /**
     * @throws \Crypt_GPG_Exception
     * @throws \Crypt_GPG_KeyNotFoundException
     * @throws \Crypt_GPG_BadPassphraseException
     */
    public function encryptAndSign(Message $message, string $passphrase = null, bool $attachKey = false): Message
    {
        return $this->encryptWithOrWithoutSigning($message, true, $passphrase, $attachKey);
    }

    /**
     * @throws \Crypt_GPG_Exception
     * @throws \Crypt_GPG_KeyNotFoundException
     * @throws \Crypt_GPG_BadPassphraseException
     */
    private function encryptWithOrWithoutSigning(Message $message, bool $sign = false, string $passphrase = null, bool $attachKey = false): Message
    {
        $this->headers = $message->getHeaders();
        $body = $message->getBody();

        foreach ($this->getRecipients() as $recipient) {
            $this->gpg->addEncryptKey($recipient);
        }

        if ($attachKey) {
            $body = $this->attachPublicKey($message);
        }

        if ($sign) {
            $this->gpg->addSignKey($this->determineSigningKey(), $passphrase);
            $body = $this->gpg->encryptAndSign($body->toString());
        } else {
            $body = $this->gpg->encrypt($body->toString());
        }

        $part = new PGPEncryptedPart(
            new PGPEncryptedInitializationPart(),
            new PGPEncryptedMessagePart($body)
        );

        return new Message($this->headers, $part);
    }

    /**
     * @throws \Crypt_GPG_Exception
     * @throws \Crypt_GPG_KeyNotFoundException
     * @throws \Crypt_GPG_BadPassphraseException
     */
    public function sign(Message $message, string $passphrase = null, bool $attachKey = false): Message
    {
        $this->headers = $message->getHeaders();
        $body = $message->getBody()->toString();
        $messagePart = $message->getBody();

        if ($attachKey) {
            $mixed = $this->attachPublicKey($message);
            $body = $mixed->toString();
            $messagePart = $mixed;
        }
        // TODO: find a way to normalize Message body and pass it along to PGPSignedPart
        $body = $this->prepareMessageForSigning($messagePart, $body);
        $this->signed = $body;

        $this->gpg->addSignKey($this->determineSigningKey(), $passphrase);
        $signature = $this->gpg->sign($body, \Crypt_GPG::SIGN_MODE_DETACHED);
        $this->signature = $signature;
        $part = new PGPSignedPart(
            $messagePart,
            new PGPSignaturePart($signature)
        );

        return new Message($this->headers, $part);
    }

    /**
     * @throws \Crypt_GPG_Exception
     * @throws \Crypt_GPG_KeyNotFoundException
     */
    private function attachPublicKey(Message $message): MixedPart
    {
        $publicKey = $this->gpg->exportPublicKey($this->determineSigningKey());
        $key = new PGPKeyPart($publicKey);

        // TODO: find more elegant way than to create another MixedPart, if Message is already a MixedPart
        return new MixedPart($message->getBody(), $key);
    }

    private function getRecipients(): array
    {
        $recipients = [
            $this->getAddresses('to'),
            $this->getAddresses('cc'),
            $this->getAddresses('bcc'),
        ];

        return array_merge(...$recipients);
    }

    private function getFrom(): string
    {
        return $this->getAddresses('from')[0];
    }

    private function getAddresses(string $type): array
    {
        $addresses = [];
        $addressType = $this->headers->get($type);
        if ($addressType instanceof MailboxListHeader) {
            foreach ($addressType->getAddresses() as $address) {
                $addresses[] = $address->getAddress();
            }
        }

        return $addresses;
    }

    private function determineSigningKey(): string
    {
        return $this->signingKey ?? $this->getFrom();
    }
}
