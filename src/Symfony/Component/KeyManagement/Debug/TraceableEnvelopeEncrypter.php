<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\KeyManagement\Debug;

use Symfony\Component\KeyManagement\DataCollector\KeyManagementDataCollector;
use Symfony\Component\KeyManagement\Envelope;
use Symfony\Component\KeyManagement\EnvelopeDecrypterInterface;
use Symfony\Component\KeyManagement\EnvelopeEncrypterInterface;
use Symfony\Component\KeyManagement\StoredFormat;

/**
 * Reports to a {@see KeyManagementDataCollector} every envelope an application writes or reads.
 *
 * An envelope call is where the two regimes become visible side by side: the format tells whether
 * the payload carries its own wrapped data key or refers to a stored one, and the KMS calls the
 * collector nests under it tell what that cost. A stored envelope encrypting ten payloads under
 * one data key reports ten envelope calls and a single KMS call.
 *
 * @author Florent Morselli <florent.morselli@spomky-labs.com>
 *
 * @experimental
 */
final class TraceableEnvelopeEncrypter implements EnvelopeEncrypterInterface, EnvelopeDecrypterInterface
{
    use TracesCalls;

    public function __construct(
        private readonly EnvelopeEncrypterInterface&EnvelopeDecrypterInterface $encrypter,
        KeyManagementDataCollector $collector,
        string $name,
    ) {
        $this->collector = $collector;
        $this->tracedService = $name;
        $this->tracedBackend = get_debug_type($encrypter);
    }

    /**
     * The decorated encrypter, for whoever needs to look past the decorator.
     */
    public function getEncrypter(): EnvelopeEncrypterInterface&EnvelopeDecrypterInterface
    {
        return $this->encrypter;
    }

    public function encrypt(string $key, #[\SensitiveParameter] string $plaintext, string $aad = ''): Envelope
    {
        $start = microtime(true);
        $call = [
            'key' => $key,
            'aad' => $aad,
            'in' => \strlen($plaintext),
        ];

        try {
            $envelope = $this->encrypter->encrypt($key, $plaintext, $aad);
            $call['out'] = \strlen($envelope->ciphertext);
            $call['format'] = self::format($envelope);
            $call['reference'] = $envelope->reference;

            return $envelope;
        } catch (\Throwable $e) {
            $call['error'] = self::describe($e);

            throw $e;
        } finally {
            $this->record(KeyManagementDataCollector::LAYER_ENVELOPE, 'encrypt', $start, $call);
        }
    }

    public function decrypt(Envelope $envelope, string $aad = ''): string
    {
        $start = microtime(true);
        $call = [
            'key' => $envelope->keyId,
            'reference' => $envelope->reference,
            'format' => self::format($envelope),
            'aad' => $aad,
            'in' => \strlen($envelope->ciphertext),
        ];

        try {
            $plaintext = $this->encrypter->decrypt($envelope, $aad);
            $call['out'] = \strlen($plaintext);

            return $plaintext;
        } catch (\Throwable $e) {
            $call['error'] = self::describe($e);

            throw $e;
        } finally {
            $this->record(KeyManagementDataCollector::LAYER_ENVELOPE, 'decrypt', $start, $call);
        }
    }

    private static function format(Envelope $envelope): string
    {
        return $envelope->format instanceof StoredFormat ? 'stored' : 'self-contained';
    }
}
