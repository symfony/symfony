<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Doctrine\Messenger;

use Symfony\Component\Messenger\Envelope;

final class EntityMessagePreDispatchEvent
{
    private $entity;
    private $envelope;

    public function __construct(object $entity, Envelope $envelope)
    {
        $this->entity = $entity;
        $this->envelope = $envelope;
    }

    public function getEntity(): object
    {
        return $this->entity;
    }

    public function getEnvelope(): Envelope
    {
        return $this->envelope;
    }

    public function setEnvelope(Envelope $envelope): void
    {
        $this->envelope = $envelope;
    }
}
