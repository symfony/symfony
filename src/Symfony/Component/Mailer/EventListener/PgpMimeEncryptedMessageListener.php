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

use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Mailer\Event\MessageEvent;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Exception\KeyNotFoundException;
use Symfony\Component\Mime\Message;

/**
 * Encrypts messages using PGP/MIME.
 *
 * @author Florent Morselli <florent.morselli@spomky-labs.com>
 *
 * @experimental
 */
final class PgpMimeEncryptedMessageListener implements EventSubscriberInterface
{
    public const PRIORITY = -200;

    /**
     * Throw a KeyNotFoundException as soon as a recipient has no public key.
     */
    public const ON_MISSING_KEY_FAIL = 'fail';

    /**
     * Encrypt for the recipients that have a key; the others still receive the (unreadable) message.
     */
    public const ON_MISSING_KEY_ENCRYPT = 'encrypt';

    /**
     * Encrypt for the recipients that have a key and drop the others from the envelope.
     */
    public const ON_MISSING_KEY_SKIP = 'skip';

    /**
     * @param \Closure(Message, array<string, string>): Message $encrypter
     * @param self::ON_MISSING_KEY_*                            $onMissingKey
     */
    public function __construct(
        private readonly PgpPublicKeyRepositoryInterface $pgpRepository,
        private readonly \Closure $encrypter,
        private readonly string $onMissingKey = self::ON_MISSING_KEY_FAIL,
        private readonly bool $encryptForSender = false,
        private readonly ?LoggerInterface $logger = null,
    ) {
    }

    public function onMessage(MessageEvent $event): void
    {
        $message = $event->getMessage();
        if (!$message instanceof Message) {
            return;
        }
        $headers = $message->getHeaders();
        if (!$headers->has('X-Pgp-Encrypt')) {
            return;
        }
        $onMissingKey = $this->resolveOnMissingKey($headers->get('X-Pgp-Encrypt')?->getBodyAsString());

        $envelope = $event->getEnvelope();
        $publicKeys = [];
        $missingRecipients = [];
        foreach ($envelope->getRecipients() as $recipient) {
            $publicKeyPath = $this->pgpRepository->findPublicKeyPathFor($recipient->getAddress());
            if (null === $publicKeyPath) {
                $missingRecipients[] = $recipient->getAddress();

                continue;
            }
            $publicKeys[$recipient->getAddress()] = $publicKeyPath;
        }

        if ($missingRecipients) {
            if (self::ON_MISSING_KEY_FAIL === $onMissingKey) {
                throw new KeyNotFoundException(\sprintf('No PGP public key found for recipient(s) "%s".', implode('", "', $missingRecipients)));
            }

            $this->logger?->warning('Some recipients have no PGP public key.', ['recipients' => $missingRecipients, 'on_missing_key' => $onMissingKey]);

            if (self::ON_MISSING_KEY_SKIP === $onMissingKey) {
                $keptRecipients = array_values(array_filter(
                    $envelope->getRecipients(),
                    static fn (Address $recipient) => !\in_array($recipient->getAddress(), $missingRecipients, true),
                ));
                if (!$keptRecipients) {
                    throw new KeyNotFoundException('No PGP public key found for any recipient.');
                }
                $envelope->setRecipients($keptRecipients);
            }
        }

        if (!$publicKeys) {
            throw new KeyNotFoundException('No PGP public key found for any recipient.');
        }

        if ($this->encryptForSender) {
            $sender = $envelope->getSender();
            if (null !== $senderPublicKeyPath = $this->pgpRepository->findPublicKeyPathFor($sender->getAddress())) {
                $publicKeys[$sender->getAddress()] = $senderPublicKeyPath;
            }
        }

        $headers->remove('X-Pgp-Encrypt');

        $event->setMessage(($this->encrypter)($message, $publicKeys));
    }

    /**
     * @return self::ON_MISSING_KEY_*
     */
    private function resolveOnMissingKey(?string $headerValue): string
    {
        return \in_array($headerValue, [self::ON_MISSING_KEY_FAIL, self::ON_MISSING_KEY_ENCRYPT, self::ON_MISSING_KEY_SKIP], true)
            ? $headerValue
            : $this->onMissingKey;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            MessageEvent::class => ['onMessage', self::PRIORITY],
        ];
    }
}
