<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\KeyManagement\Bridge\Kmip\Tests\Fixtures;

/**
 * Checks that secret arguments in exception traces are replaced by sensitive parameter values.
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

        self::assertTrue($redacted, 'No trace argument was redacted.');
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

        self::fail('The call was expected to throw.');
    }
}
