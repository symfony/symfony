<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Middleware\Stamper;

use Symfony\Component\Messenger\Envelope;

/**
 * Classes used by EnvelopeStamperMiddleware that can add stamps to envelope.
 *
 * @author Ryan Weaver <ryan@symfonycasts.com>
 */
interface EnvelopeStamperInterface
{
    /**
     * Apply new stamps and return the new envelope.
     */
    public function stampEnvelope(Envelope $envelope): Envelope;
}
