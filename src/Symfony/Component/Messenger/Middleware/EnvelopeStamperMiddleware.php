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
use Symfony\Component\Messenger\Middleware\Stamper\EnvelopeStamperInterface;

/**
 * @author Ryan Weaver <ryan@symfonycasts.com>
 */
class EnvelopeStamperMiddleware implements MiddlewareInterface
{
    private $envelopeStampers;

    /**
     * @param EnvelopeStamperInterface[] $envelopeStampers
     */
    public function __construct(iterable $envelopeStampers)
    {
        $this->envelopeStampers = $envelopeStampers;
    }

    public function handle(Envelope $envelope, StackInterface $stack): Envelope
    {
        foreach ($this->envelopeStampers as $envelopeStamper) {
            $envelope = $envelopeStamper->stampEnvelope($envelope);
        }

        return $stack->next()->handle($envelope, $stack);
    }
}
