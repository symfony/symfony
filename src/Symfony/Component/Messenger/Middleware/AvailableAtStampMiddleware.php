<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Middleware;

use DateTime;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Stamp\AvailableAtStamp;
use Symfony\Component\Messenger\Stamp\DelayStamp;

/**
 * @author Antonio del Olmo García <adelolmog@gmail.com>
 */
class AvailableAtStampMiddleware implements MiddlewareInterface
{
    /**
     * {@inheritdoc}
     */
    public function handle(Envelope $envelope, StackInterface $stack): Envelope
    {
        $availableAtStamp = $envelope->last(AvailableAtStamp::class);

        if (null !== $availableAtStamp) {
            $availableAt = $availableAtStamp->getAvailableAt();
            $now = new DateTime();

            $delay = $availableAt->getTimestamp() - $now->getTimestamp();

            $envelope = $envelope->with(
                new DelayStamp($delay * 1000)
            );
        }

        return $stack->next()->handle($envelope, $stack);
    }
}
