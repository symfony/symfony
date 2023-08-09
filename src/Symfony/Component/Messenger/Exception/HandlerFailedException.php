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

class HandlerFailedException extends RuntimeException implements WrappedExceptionsInterface
{
    use WrappedExceptionsTrait;

    private Envelope $envelope;

    /**
     * @param \Throwable[] $exceptions The name of the handler should be given as key
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
     * @deprecated since Symfony 6.4, use {@see self::getWrappedExceptions()} instead
     *
     * @return \Throwable[]
     */
    public function getNestedExceptions(): array
    {
        trigger_deprecation('symfony/messenger', '6.4', 'The "%s()" method is deprecated, use "%s::getWrappedExceptions()" instead.', __METHOD__, self::class);

        return array_values($this->exceptions);
    }

    /**
     * @deprecated since Symfony 6.4, use {@see self::getWrappedExceptions()} instead
     */
    public function getNestedExceptionOfClass(string $exceptionClassName): array
    {
        trigger_deprecation('symfony/messenger', '6.4', 'The "%s()" method is deprecated, use "%s::getWrappedExceptions()" instead.', __METHOD__, self::class);

        return array_values(
            array_filter(
                $this->exceptions,
                fn ($exception) => is_a($exception, $exceptionClassName)
            )
        );
    }
}
