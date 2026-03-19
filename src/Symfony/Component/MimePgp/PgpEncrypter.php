<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\MimePgp;

use Symfony\Component\Mime\Message;
use Symfony\Component\MimePgp\Exception\KeyNotFoundException;
use Symfony\Component\MimePgp\Mime\Part\Multipart\PgpEncryptedPart;
use Symfony\Component\MimePgp\Mime\Part\PgpEncryptedInitializationPart;
use Symfony\Component\MimePgp\Mime\Part\PgpEncryptedMessagePart;

/**
 * @author PuLLi <the@pulli.dev>
 *
 * @experimental
 */
final class PgpEncrypter
{
    use PgpMimeTrait;

    private PgpProcess $pgpProcess;

    /**
     * @param array<string, string>                                                                                                    $recipientKeys
     * @param array{binary?: string, cipher_algorithm?: string, digest_algorithm?: string, temp_prefix?: string, timeout?: float|null} $options
     */
    public function __construct(
        private readonly array $recipientKeys,
        array $options = [],
    )
    {
        $this->pgpProcess = new PgpProcess(
            $options['binary'] ?? 'gpg',
            $options['temp_prefix'] ?? 'GPGMIME',
            $options['cipher_algorithm'] ?? 'AES256',
            'SHA512',
            $options['timeout'] ?? 60,
        );
    }

    public function encrypt(Message $message): Message
    {
        if (!$this->recipientKeys) {
            throw new KeyNotFoundException('No recipient keys found.');
        }

        $body = $message->getBody();
        $data = $this->iteratorToString($body->toIterable());

        $output = $this->pgpProcess->encrypt($data, $this->recipientKeys);

        $part = new PgpEncryptedPart(
            new PgpEncryptedInitializationPart(),
            new PgpEncryptedMessagePart($output)
        );

        return new Message($message->getHeaders(), $part);
    }
}
