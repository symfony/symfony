<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Debug;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Stamp\ProfilerTokenStamp;
use Symfony\Component\Messenger\Stamp\RedeliveryStamp;

/**
 * A virtual request standing for a message consumed by a worker, to profile how it is handled.
 *
 * @internal
 */
final class WorkerMessageRequest extends Request
{
    public const HANDLED = 0;
    public const RETRIED = 1;
    public const REJECTED = 2;
    public const PENDING = 3;

    public int $result = self::PENDING;
    public ?\Throwable $error = null;

    // read from the received envelope, since handling it can restamp it, e.g. when an outbox relays it
    private readonly ?string $originToken;
    private readonly int $attempt;

    public function __construct(
        public Envelope $envelope,
        public readonly string $transportName,
    ) {
        $this->originToken = $envelope->last(ProfilerTokenStamp::class)?->getToken();
        $this->attempt = 1 + RedeliveryStamp::getRetryCountFromEnvelope($envelope);

        parent::__construct(
            attributes: [
                '_controller' => $envelope->getMessage()::class,
                '_virtual_type' => 'worker_message',
            ],
            server: ['REQUEST_TIME_FLOAT' => microtime(true)] + $_SERVER,
        );
    }

    public function handled(Envelope $envelope): void
    {
        $this->envelope = $envelope;
        $this->result = self::HANDLED;
        $this->error = null;
    }

    public function failed(Envelope $envelope, \Throwable $error, bool $willRetry): void
    {
        $this->envelope = $envelope;
        $this->result = $willRetry ? self::RETRIED : self::REJECTED;
        $this->error = $error;
    }

    public function getOriginToken(): ?string
    {
        return $this->originToken;
    }

    public function getAttempt(): int
    {
        return $this->attempt;
    }

    // Methods below allow to populate a profile, thus enable search and filtering
    public function getUri(): string
    {
        return $this->envelope->getMessage()::class;
    }

    public function getMethod(): string
    {
        return $this->transportName;
    }

    public function getResponse(): Response
    {
        return new class($this->result) extends Response {
            public function __construct(int $result)
            {
                parent::__construct();

                // getStatusCode() is final and setStatusCode() rejects a result code
                $this->statusCode = $result;
            }
        };
    }

    public function getClientIp(): string
    {
        return $this->getOriginToken() ?? '';
    }
}
