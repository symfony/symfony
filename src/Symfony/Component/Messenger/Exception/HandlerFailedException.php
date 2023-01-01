<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Exception;

use Symfony\Component\Messenger\Envelope;

class HandlerFailedException extends RuntimeException
{
    private array $exceptions;
    private Envelope $envelope;

    /**
     * @param \Throwable[] $exceptions
     */
    public function __construct(Envelope $envelope, array $exceptions)
    {
        $firstFailure = current($exceptions);

        $message = sprintf('Handling "%s" failed: ', $envelope->getMessage()::class);

        parent::__construct(
            $message.(1 === \count($exceptions)
                ? $firstFailure->getMessage()
                : sprintf('%d handlers failed. First failure is: %s', \count($exceptions), $firstFailure->getMessage())
            ),
            (int) $firstFailure->getCode(),
            $firstFailure
        );

        $this->envelope = $envelope;
        $this->exceptions = $exceptions;
    }

    public function getEnvelope(): Envelope
    {
        return $this->envelope;
    }

    /**
     * @return \Throwable[]
     */
    public function getNestedExceptions(): array
    {
        return $this->exceptions;
    }

    public function getNestedExceptionOfClass(string $exceptionClassName): array
    {
        return array_values(
            array_filter(
                $this->exceptions,
                fn ($exception) => is_a($exception, $exceptionClassName)
            )
        );
    }
}
