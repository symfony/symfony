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
use Symfony\Component\KeyManagement\DataKeyGeneratorInterface;
use Symfony\Component\KeyManagement\DataKeyStoreInterface;
use Symfony\Component\KeyManagement\DecrypterInterface;
use Symfony\Component\KeyManagement\EncrypterInterface;
use Symfony\Component\KeyManagement\EnvelopeDecrypterInterface;
use Symfony\Component\KeyManagement\EnvelopeEncrypterInterface;

/**
 * Plumbing shared by the debug decorators: where a call is reported, and which frame of the
 * application made it.
 *
 * @author Florent Morselli <florent.morselli@spomky-labs.com>
 *
 * @internal
 */
trait TracesCalls
{
    private readonly KeyManagementDataCollector $collector;

    /**
     * Name the decorated service is configured under.
     */
    private readonly string $tracedService;

    /**
     * Class of the decorated object, so that the panel names the backend a call went to.
     */
    private readonly string $tracedBackend;

    /**
     * @param array<string, mixed> $call Fields specific to the operation, which win over the ones set here
     */
    protected function record(string $layer, string $operation, float $start, array $call = []): void
    {
        $this->collector->collectCall($call + [
            'layer' => $layer,
            'operation' => $operation,
            'service' => $this->tracedService,
            'backend' => $this->tracedBackend,
            'start' => $start,
            'time' => microtime(true) - $start,
            'caller' => self::caller(),
        ]);
    }

    /**
     * @return array{class: class-string<\Throwable>, message: string}
     */
    protected static function describe(\Throwable $error): array
    {
        return ['class' => $error::class, 'message' => $error->getMessage()];
    }

    /**
     * Walks out of the component to report the frame that asked for the operation: an envelope
     * encrypter asking its KMS for a data key is not what the reader is after, the code that
     * called the encrypter is.
     *
     * @return array{name: string, file: string, line: int}|null
     */
    private static function caller(): ?array
    {
        $trace = debug_backtrace(\DEBUG_BACKTRACE_IGNORE_ARGS, 12);
        $frame = null;

        for ($i = 1, $count = \count($trace); $i < $count; ++$i) {
            if (!isset($trace[$i]['file'], $trace[$i]['line'])) {
                continue;
            }

            $frame ??= $trace[$i];

            if (!self::isEncryptionPath($trace[$i + 1]['class'] ?? null)) {
                $frame = $trace[$i];

                break;
            }
        }

        if (null === $frame) {
            return null;
        }

        $name = str_replace('\\', '/', $frame['file']);

        return [
            'name' => substr($name, strrpos($name, '/') + 1),
            'file' => $frame['file'],
            'line' => $frame['line'],
        ];
    }

    /**
     * Whether `$class` is a piece of the encryption path rather than the code that asked for it.
     * Recognizing it by contract rather than by namespace covers the decorators an application
     * writes of its own.
     */
    private static function isEncryptionPath(?string $class): bool
    {
        if (null === $class) {
            return false;
        }

        foreach ([EncrypterInterface::class, DecrypterInterface::class, DataKeyGeneratorInterface::class, EnvelopeEncrypterInterface::class, EnvelopeDecrypterInterface::class, DataKeyStoreInterface::class] as $interface) {
            if (is_a($class, $interface, true)) {
                return true;
            }
        }

        return false;
    }
}
