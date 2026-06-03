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
 * @internal
 */
trait EnvelopeAwareExceptionTrait
{
    private ?Envelope $envelope = null;

    public function getEnvelope(): ?Envelope
    {
        return $this->envelope;
    }
}
