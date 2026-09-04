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

use Symfony\Component\KeyManagement\Ciphertext;
use Symfony\Component\KeyManagement\DataCollector\KeyManagementDataCollector;
use Symfony\Component\KeyManagement\DataKeyGeneratorInterface;
use Symfony\Component\KeyManagement\DecrypterInterface;
use Symfony\Component\KeyManagement\EncrypterInterface;

/**
 * Reports to a {@see KeyManagementDataCollector} every call made to a KMS client.
 *
 * A KMS call is a network round trip on every bridge but the local ones, so counting them is what
 * the profiler panel is for. Sizes are recorded, contents are not: the plaintext and the
 * ciphertext only ever reach `strlen()`.
 *
 * Capability is not something a decorator may invent. {@see DataKeyGeneratorInterface} is detected
 * with `instanceof` by the console commands and by the Doctrine data key store, so a decorator
 * claiming it over a backend that declines it would turn a clean "this client cannot generate data
 * keys" into a failure at call time. Hence two classes and {@see wrap()} to pick between them,
 * rather than one class implementing everything.
 *
 * @author Florent Morselli <florent.morselli@spomky-labs.com>
 *
 * @experimental
 */
class TraceableKms implements EncrypterInterface, DecrypterInterface
{
    use TracesCalls;

    public function __construct(
        private readonly EncrypterInterface&DecrypterInterface $kms,
        KeyManagementDataCollector $collector,
        string $name,
    ) {
        $this->collector = $collector;
        $this->tracedService = $name;
        $this->tracedBackend = get_debug_type($kms);
    }

    /**
     * Wraps `$kms` in the decorator that mirrors what it can do, so that the capabilities the
     * application detects are the ones the backend actually has.
     */
    public static function wrap(EncrypterInterface&DecrypterInterface $kms, KeyManagementDataCollector $collector, string $name): self
    {
        return $kms instanceof DataKeyGeneratorInterface
            ? new TraceableDataKeyGenerator($kms, $collector, $name)
            : new self($kms, $collector, $name);
    }

    /**
     * The decorated client, for whoever needs to look past the decorator: an application reporting
     * which backend it talks to reads a class name, and would otherwise read this one.
     */
    public function getKms(): EncrypterInterface&DecrypterInterface
    {
        return $this->kms;
    }

    public function encrypt(string $keyId, #[\SensitiveParameter] string $plaintext, string $aad = '', bool $deterministic = false): Ciphertext
    {
        $start = microtime(true);
        $call = [
            'key' => $keyId,
            'aad' => $aad,
            'deterministic' => $deterministic,
            'in' => \strlen($plaintext),
        ];

        try {
            $ciphertext = $this->kms->encrypt($keyId, $plaintext, $aad, $deterministic);
            $call['out'] = \strlen($ciphertext->blob);

            return $ciphertext;
        } catch (\Throwable $e) {
            $call['error'] = self::describe($e);

            throw $e;
        } finally {
            $this->record(KeyManagementDataCollector::LAYER_KMS, 'encrypt', $start, $call);
        }
    }

    public function decrypt(Ciphertext $ciphertext, string $aad = ''): string
    {
        $start = microtime(true);
        $call = [
            'key' => $ciphertext->keyId,
            'aad' => $aad,
            'in' => \strlen($ciphertext->blob),
        ];

        try {
            $plaintext = $this->kms->decrypt($ciphertext, $aad);
            $call['out'] = \strlen($plaintext);

            return $plaintext;
        } catch (\Throwable $e) {
            $call['error'] = self::describe($e);

            throw $e;
        } finally {
            $this->record(KeyManagementDataCollector::LAYER_KMS, 'decrypt', $start, $call);
        }
    }
}
