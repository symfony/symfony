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

class HandlerFailedException extends RuntimeException implements WrappedExceptionsInterface, EnvelopeAwareExceptionInterface
{
    use WrappedExceptionsTrait;

    /**
     * @param \Throwable[] $exceptions The name of the handler should be given as key
     */
    public function __construct(
        private Envelope $envelope,
        array $exceptions,
    ) {
        $firstFailure = current($exceptions);

        $message = \sprintf('Handling "%s" failed: ', $envelope->getMessage()::class);

        parent::__construct(
            $message.(1 === \count($exceptions)
                ? $firstFailure->getMessage()
                : \sprintf('%d handlers failed. First failure is: %s', \count($exceptions), $firstFailure->getMessage())
            ),
            (int) $firstFailure->getCode(),
            $firstFailure
        );

        $this->exceptions = $exceptions;
    }

    public function getEnvelope(): Envelope
    {
        return $this->envelope;
    }
}
