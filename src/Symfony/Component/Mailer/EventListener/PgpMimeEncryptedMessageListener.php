<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mailer\EventListener;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Mailer\Event\MessageEvent;
use Symfony\Component\Mime\Message;
use Symfony\Component\MimePgp\Exception\KeyNotFoundException;
use Symfony\Component\MimePgp\PgpEncrypter;

/**
 * Encrypts messages using PGP/MIME.
 *
 * @author Florent Morselli <florent.morselli@spomky-labs.com>
 */
final class PgpMimeEncryptedMessageListener implements EventSubscriberInterface
{
    public function __construct(
        private readonly PgpPublicKeyRepositoryInterface $pgpRepository,
        private readonly string $binary = 'gpg',
        private readonly string $cipherAlgorithm = 'AES256',
        private readonly ?float $timeout = 60,
        private readonly bool $failOnMissingKey = true,
    ) {
    }

    public function onMessage(MessageEvent $event): void
    {
        $message = $event->getMessage();
        if (!$message instanceof Message) {
            return;
        }
        if (!$message->getHeaders()->has('X-Pgp-Encrypt')) {
            return;
        }
        $message->getHeaders()->remove('X-Pgp-Encrypt');
        $publicKeys = [];
        foreach ($event->getEnvelope()->getRecipients() as $recipient) {
            $certificatePath = $this->pgpRepository->findPublicKeyPathFor($recipient->getAddress());
            if (null === $certificatePath) {
                if ($this->failOnMissingKey) {
                    throw new KeyNotFoundException(\sprintf('No PGP public key found for recipient "%s".', $recipient->getAddress()));
                }

                return;
            }
            $publicKeys[$recipient->getAddress()] = $certificatePath;
        }

        $sender = $event->getEnvelope()->getSender();
        $senderCertificatePath = $this->pgpRepository->findPublicKeyPathFor($sender->getAddress());
        if (null !== $senderCertificatePath) {
            $publicKeys[$sender->getAddress()] = $senderCertificatePath;
        }

        if (0 === \count($publicKeys)) {
            return;
        }

        $encrypter = new PgpEncrypter($publicKeys, ['binary' => $this->binary, 'cipher_algorithm' => $this->cipherAlgorithm, 'timeout' => $this->timeout]);

        $event->setMessage($encrypter->encrypt($message));
    }

    public static function getSubscribedEvents(): array
    {
        return [
            MessageEvent::class => ['onMessage', -128],
        ];
    }
}
