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

/**
 * When handling queued messages from {@link DispatchAfterCurrentBusMiddleware},
 * some handlers caused an exception. This exception contains all those handler exceptions.
 *
 * @author Tobias Nyholm <tobias.nyholm@gmail.com>
 */
class DelayedMessageHandlingException extends RuntimeException implements WrappedExceptionsInterface, EnvelopeAwareExceptionInterface
{
    use EnvelopeAwareExceptionTrait;
    use WrappedExceptionsTrait;

    public function __construct(
        private array $exceptions,
        ?Envelope $envelope = null,
    ) {
        $this->envelope = $envelope;

        $exceptionMessages = implode(", \n", array_map(
            fn (\Throwable $e) => $e::class.': '.$e->getMessage(),
            $exceptions
        ));

        if (1 === \count($exceptions)) {
            $message = sprintf("A delayed message handler threw an exception: \n\n%s", $exceptionMessages);
        } else {
            $message = sprintf("Some delayed message handlers threw an exception: \n\n%s", $exceptionMessages);
        }

        parent::__construct($message, 0, $exceptions[array_key_first($exceptions)]);
    }
}
