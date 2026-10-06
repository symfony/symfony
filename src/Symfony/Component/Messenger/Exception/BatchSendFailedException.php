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
 * Tells which messages of a batch were sent when some of them could not be.
 *
 * @author Joppe De Cuyper <hello@joppe.dev>
 */
class BatchSendFailedException extends RuntimeException
{
    /**
     * @param array<Envelope>             $envelopes  The envelopes that did not fail, by their key in the batch
     * @param non-empty-array<\Throwable> $exceptions The exceptions that prevented sending the other envelopes, by their key in the batch
     */
    public function __construct(
        private array $envelopes,
        private array $exceptions,
    ) {
        $exception = $exceptions[array_key_first($exceptions)];
        $count = \count($exceptions);

        parent::__construct(\sprintf('Sending %d message%s of the batch failed: ', $count, 1 === $count ? '' : 's').$exception->getMessage(), 0, $exception);
    }

    /**
     * @return array<Envelope>
     */
    public function getEnvelopes(): array
    {
        return $this->envelopes;
    }

    /**
     * @return array<\Throwable>
     */
    public function getExceptions(): array
    {
        return $this->exceptions;
    }
}
