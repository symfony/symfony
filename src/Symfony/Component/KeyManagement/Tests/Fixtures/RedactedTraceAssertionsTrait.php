<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\KeyManagement\Tests\Fixtures;

/**
 * Asserts that a secret does not travel in clear in the arguments a stack trace records.
 *
 * Plaintexts and data keys travel as arguments, and PHP records the arguments of every frame: an
 * exception thrown by a backend carries them into the logs, and into whatever renders a trace.
 * `#[\SensitiveParameter]` replaces them with a placeholder, and this is what checks that it does.
 *
 * The check only means something while the engine collects those arguments, which
 * `zend.exception_ignore_args` turns off, so the trace is captured with the setting forced and
 * something must have been redacted for the assertion to hold.
 */
trait RedactedTraceAssertionsTrait
{
    /**
     * @param list<array<string, mixed>> $frames
     */
    private static function assertRedacted(string $secret, array $frames): void
    {
        $redacted = false;

        foreach ($frames as $frame) {
            foreach ($frame['args'] ?? [] as $position => $argument) {
                $redacted = $redacted || $argument instanceof \SensitiveParameterValue;
                self::assertNotSame($secret, $argument, \sprintf('argument #%d of %s%s%s() carries the secret in clear.', $position, $frame['class'] ?? '', $frame['type'] ?? '', $frame['function']));
            }
        }

        self::assertTrue($redacted, 'nothing was redacted at all, so the assertion above would hold whatever the parameters are declared like.');
    }

    /**
     * @param list<array<string, mixed>> $trace
     *
     * @return array<string, mixed>
     */
    private static function frameOf(string $class, string $function, array $trace): array
    {
        foreach ($trace as $frame) {
            if ($class === ($frame['class'] ?? null) && $function === $frame['function']) {
                return $frame;
            }
        }

        self::fail(\sprintf('No frame of %s::%s() in the stack trace.', $class, $function));
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function traceOf(\Closure $call): array
    {
        $ignoreArguments = (string) ini_get('zend.exception_ignore_args');
        ini_set('zend.exception_ignore_args', '0');

        try {
            $call();
        } catch (\Throwable $e) {
            return $e->getTrace();
        } finally {
            ini_set('zend.exception_ignore_args', $ignoreArguments);
        }

        self::fail('The call was expected to throw, so that there is a stack trace to look at.');
    }
}
