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
use Symfony\Component\Mailer\Exception\RuntimeException;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Crypto\SMimeEncrypter;
use Symfony\Component\Mime\Message;

/**
 * Encrypts messages using S/MIME.
 *
 * @author Elías Fernández
 */
final class SmimeEncryptedMessageListener implements EventSubscriberInterface
{
    public const PRIORITY = -200;

    /**
     * Send the message unencrypted when at least one recipient has no certificate. This is the default and preserves the historical behavior.
     *
     * Using this behavior is deprecated since Symfony 8.2 and will throw in 9.0; use "fail", "encrypt" or "skip" instead.
     * The constant itself is kept so that the value can still be compared against.
     */
    public const ON_MISSING_CERTIFICATE_SEND_UNENCRYPTED = 'send_unencrypted';

    /**
     * Throw a RuntimeException as soon as a recipient has no certificate.
     */
    public const ON_MISSING_CERTIFICATE_FAIL = 'fail';

    /**
     * Encrypt for the recipients that have a certificate; the others still receive the (unreadable) message.
     */
    public const ON_MISSING_CERTIFICATE_ENCRYPT = 'encrypt';

    /**
     * Encrypt for the recipients that have a certificate and drop the others from the envelope.
     */
    public const ON_MISSING_CERTIFICATE_SKIP = 'skip';

    /**
     * @param self::ON_MISSING_CERTIFICATE_* $onMissingCertificate
     */
    public function __construct(
        private readonly SmimeCertificateRepositoryInterface $smimeRepository,
        private readonly ?int $cipher = null,
        private readonly string $onMissingCertificate = self::ON_MISSING_CERTIFICATE_SEND_UNENCRYPTED,
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
        if (!$headers->has('X-SMime-Encrypt')) {
            return;
        }
        $onMissingCertificate = $this->resolveOnMissingCertificate($headers->get('X-SMime-Encrypt')?->getBodyAsString());

        $envelope = $event->getEnvelope();
        $certificatePaths = [];
        $missingRecipients = [];
        foreach ($envelope->getRecipients() as $recipient) {
            $certificatePath = $this->smimeRepository->findCertificatePathFor($recipient->getAddress());
            if (null === $certificatePath) {
                $missingRecipients[] = $recipient->getAddress();

                continue;
            }
            $certificatePaths[$recipient->getAddress()] = $certificatePath;
        }

        if ($missingRecipients) {
            if (self::ON_MISSING_CERTIFICATE_FAIL === $onMissingCertificate) {
                throw new RuntimeException(\sprintf('No S/MIME certificate found for recipient(s) "%s".', implode('", "', $missingRecipients)));
            }

            $this->logger?->warning('Some recipients have no S/MIME certificate.', ['recipients' => $missingRecipients, 'on_missing_certificate' => $onMissingCertificate]);

            if (self::ON_MISSING_CERTIFICATE_SEND_UNENCRYPTED === $onMissingCertificate) {
                trigger_deprecation('symfony/mailer', '8.2', 'Sending an S/MIME message unencrypted because a recipient has no certificate is deprecated and will throw in 9.0; set the "on_missing_certificate" option (or the "X-SMime-Encrypt" header) to "fail", "encrypt" or "skip".');

                $headers->remove('X-SMime-Encrypt');

                return;
            }

            if (self::ON_MISSING_CERTIFICATE_SKIP === $onMissingCertificate) {
                $keptRecipients = array_values(array_filter(
                    $envelope->getRecipients(),
                    static fn (Address $recipient) => !\in_array($recipient->getAddress(), $missingRecipients, true),
                ));
                if (!$keptRecipients) {
                    throw new RuntimeException('No S/MIME certificate found for any recipient.');
                }
                $envelope->setRecipients($keptRecipients);
            }
        }

        if (!$certificatePaths) {
            if (self::ON_MISSING_CERTIFICATE_SEND_UNENCRYPTED === $onMissingCertificate) {
                $headers->remove('X-SMime-Encrypt');

                return;
            }

            throw new RuntimeException('No S/MIME certificate found for any recipient.');
        }

        if ($this->encryptForSender) {
            $sender = $envelope->getSender();
            if (null !== $senderCertificatePath = $this->smimeRepository->findCertificatePathFor($sender->getAddress())) {
                $certificatePaths[$sender->getAddress()] = $senderCertificatePath;
            }
        }

        $headers->remove('X-SMime-Encrypt');

        $encrypter = new SMimeEncrypter(array_values($certificatePaths), $this->cipher);

        $event->setMessage($encrypter->encrypt($message));
    }

    /**
     * @return self::ON_MISSING_CERTIFICATE_*
     */
    private function resolveOnMissingCertificate(?string $headerValue): string
    {
        return \in_array($headerValue, [self::ON_MISSING_CERTIFICATE_FAIL, self::ON_MISSING_CERTIFICATE_ENCRYPT, self::ON_MISSING_CERTIFICATE_SKIP], true)
            ? $headerValue
            : $this->onMissingCertificate;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            MessageEvent::class => ['onMessage', self::PRIORITY],
        ];
    }
}
