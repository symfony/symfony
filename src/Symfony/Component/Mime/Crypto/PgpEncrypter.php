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

use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Exception\KeyNotFoundException;
use Symfony\Component\Mime\Header\MailboxListHeader;
use Symfony\Component\Mime\Message;
use Symfony\Component\Mime\Part\Multipart\PgpEncryptedPart;
use Symfony\Component\Mime\Part\PgpEncryptedInitializationPart;
use Symfony\Component\Mime\Part\PgpEncryptedMessagePart;

/**
 * @author Florent Morselli <florent.morselli@spomky-labs.com>
 *
 * Original idea by PuLLi <the@pulli.dev>
 *
 * @experimental
 */
final class PgpEncrypter
{
    private PgpProcess $pgpProcess;

    private readonly bool $hideRecipients;

    /**
     * @param array{binary?: string, cipher_algorithm?: string, temp_prefix?: string, timeout?: float|null, hide_recipients?: bool} $options By default only the Bcc recipients' key IDs are hidden; set "hide_recipients" to true to hide every recipient's key ID
     */
    public function __construct(array $options = [])
    {
        $this->hideRecipients = $options['hide_recipients'] ?? false;
        $this->pgpProcess = PgpProcess::fromOptions($options);
    }

    /**
     * @param array<string, string> $recipientKeys The recipients' public keys, indexed by email address and pointing to the file path of the key
     */
    public function encrypt(Message $message, array $recipientKeys): Message
    {
        if (!$recipientKeys) {
            throw new KeyNotFoundException('No recipient keys found.');
        }

        $data = $message->getBody()->toString();

        $output = $this->pgpProcess->encrypt($data, $recipientKeys, $this->resolveHiddenRecipients($message, $recipientKeys));

        $part = new PgpEncryptedPart(
            new PgpEncryptedInitializationPart(),
            new PgpEncryptedMessagePart($output)
        );

        return new Message($message->getHeaders(), $part);
    }

    /**
     * @param array<string, string> $recipientKeys
     *
     * @return string[]
     */
    private function resolveHiddenRecipients(Message $message, array $recipientKeys): array
    {
        if ($this->hideRecipients) {
            return array_keys($recipientKeys);
        }

        $bccHeader = $message->getHeaders()->get('Bcc');
        if (!$bccHeader instanceof MailboxListHeader) {
            return [];
        }

        $bcc = array_map(static fn (Address $address) => $address->getAddress(), $bccHeader->getAddresses());

        return array_values(array_intersect(array_keys($recipientKeys), $bcc));
    }
}
