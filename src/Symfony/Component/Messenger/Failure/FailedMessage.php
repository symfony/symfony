<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Failure;

use Symfony\Component\Messenger\Envelope;

class FailedMessage
{
    private $id;
    private $envelope;
    private $exception;
    private $transportName;
    private $failedAt;

    public function __construct($id, Envelope $envelope, \Throwable $exception, string $transportName, \DateTimeInterface $failedAt)
    {
        $this->id = $id;
        $this->envelope = $envelope;
        $this->exception = $exception;
        $this->transportName = $transportName;
        $this->failedAt = $failedAt;
    }

    /**
     * Some unique identifier for this failed message within the storage.
     *
     * @return mixed
     */
    public function getId()
    {
        return $this->id;
    }

    public function getEnvelope(): Envelope
    {
        return $this->envelope;
    }

    public function getException(): \Throwable
    {
        return $this->exception;
    }

    public function getFailedAt(): \DateTimeInterface
    {
        return $this->failedAt;
    }

    public function getTransportName(): string
    {
        return $this->transportName;
    }
}
