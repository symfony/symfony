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

use Symfony\Component\Mime\Exception\RuntimeException;
use Symfony\Component\Mime\Message;
use Symfony\Component\Mime\Part\AbstractPart;
use Symfony\Component\Mime\Part\Multipart\MixedPart;
use Symfony\Component\Mime\Part\Multipart\PgpSignedPart;
use Symfony\Component\Mime\Part\PgpKeyPart;
use Symfony\Component\Mime\Part\PgpSignaturePart;

/**
 * @author Florent Morselli <florent.morselli@spomky-labs.com>
 *
 * Original idea by PuLLi <the@pulli.dev>
 *
 * @experimental
 */
final class PgpSigner
{
    private PgpProcess $pgpProcess;

    private string $digestAlgorithm;

    /**
     * @param array{binary?: string, cipher_algorithm?: string, digest_algorithm?: string, temp_prefix?: string, timeout?: float|null} $options
     */
    public function __construct(
        private readonly string $signingKey,
        private readonly ?string $publicKey = null,
        #[\SensitiveParameter]
        private readonly ?string $secretKeyPassphrase = null,
        array $options = [],
    ) {
        $this->digestAlgorithm = $options['digest_algorithm'] ?? 'SHA512';
        $this->pgpProcess = PgpProcess::fromOptions($options);
    }

    public function sign(Message $message): Message
    {
        $body = $message->getBody();
        $body = $this->attachPublicKey($body);
        $data = $body->toString();
        $output = $this->pgpProcess->sign($data, $this->signingKey, $this->secretKeyPassphrase);

        $part = new PgpSignedPart(
            $this->digestAlgorithm,
            $body,
            new PgpSignaturePart($output)
        );

        return new Message($message->getHeaders(), $part);
    }

    private function attachPublicKey(AbstractPart $part): AbstractPart
    {
        if (null === $this->publicKey) {
            return $part;
        }

        $content = file_get_contents($this->publicKey);
        if (false === $content) {
            throw new RuntimeException(\sprintf('Unable to read public key file "%s".', $this->publicKey));
        }

        return new MixedPart($part, new PgpKeyPart($content));
    }
}
