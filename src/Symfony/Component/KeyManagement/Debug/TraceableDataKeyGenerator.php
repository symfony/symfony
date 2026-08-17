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
use Symfony\Component\KeyManagement\DataKey;
use Symfony\Component\KeyManagement\DataKeyGeneratorInterface;
use Symfony\Component\KeyManagement\DecrypterInterface;
use Symfony\Component\KeyManagement\EncrypterInterface;

/**
 * {@see TraceableKms} over a backend that also mints data keys.
 *
 * The plaintext data key is left alone: it is neither read nor held here, so wrapping a backend
 * does not extend the life of what {@see DataKey} wipes as soon as its consumer returns.
 *
 * @author Florent Morselli <florent.morselli@spomky-labs.com>
 *
 * @experimental
 */
final class TraceableDataKeyGenerator extends TraceableKms implements DataKeyGeneratorInterface
{
    public function __construct(
        private readonly EncrypterInterface&DecrypterInterface&DataKeyGeneratorInterface $generator,
        KeyManagementDataCollector $collector,
        string $name,
    ) {
        parent::__construct($generator, $collector, $name);
    }

    public function generateDataKey(string $keyId, int $length = 32, string $aad = ''): DataKey
    {
        $start = microtime(true);
        $call = [
            'key' => $keyId,
            'aad' => $aad,
            'length' => $length,
        ];

        try {
            $dataKey = $this->generator->generateDataKey($keyId, $length, $aad);
            $call['out'] = \strlen($dataKey->wrapped->blob);

            return $dataKey;
        } catch (\Throwable $e) {
            $call['error'] = self::describe($e);

            throw $e;
        } finally {
            $this->record(KeyManagementDataCollector::LAYER_KMS, 'generate_data_key', $start, $call);
        }
    }

    public function unwrapDataKey(Ciphertext $wrapped, string $aad = ''): DataKey
    {
        $start = microtime(true);
        $call = [
            'key' => $wrapped->keyId,
            'aad' => $aad,
            'in' => \strlen($wrapped->blob),
        ];

        try {
            return $this->generator->unwrapDataKey($wrapped, $aad);
        } catch (\Throwable $e) {
            $call['error'] = self::describe($e);

            throw $e;
        } finally {
            $this->record(KeyManagementDataCollector::LAYER_KMS, 'unwrap_data_key', $start, $call);
        }
    }
}
