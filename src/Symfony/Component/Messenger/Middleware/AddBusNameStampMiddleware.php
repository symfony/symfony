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

use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Stamp\BusNameStamp;

/**
 * Adds the BusNameStamp to the bus.
 *
 * @author Ryan Weaver <ryan@symfonycasts.com>
 */
class AddBusNameStampMiddleware implements MiddlewareInterface
{
    public function __construct(
        private string $busName,
    ) {
    }

    public function handle(Envelope $envelope, StackInterface $stack): Envelope
    {
        if (null === $envelope->last(BusNameStamp::class)) {
            $envelope = $envelope->with(new BusNameStamp($this->busName));
        }

        return $stack->next()->handle($envelope, $stack);
    }
}
