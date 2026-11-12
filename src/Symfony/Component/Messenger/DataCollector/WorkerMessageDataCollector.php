<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\DataCollector;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\DataCollector\DataCollector;
use Symfony\Component\Messenger\Debug\WorkerMessageRequest;
use Symfony\Component\VarDumper\Cloner\Data;

/**
 * Collects the message consumed by a worker run with the "--profile" option.
 *
 * @internal
 */
final class WorkerMessageDataCollector extends DataCollector
{
    public function collect(Request $request, Response $response, ?\Throwable $exception = null): void
    {
        if (!$request instanceof WorkerMessageRequest) {
            return;
        }

        $envelope = $request->envelope;

        $this->data = [
            'message' => $this->cloneVar($envelope->getMessage()),
            'stamps' => $this->cloneVar(array_merge([], ...array_values($envelope->all()))),
            'transport' => $request->transportName,
            'attempt' => $request->getAttempt(),
            'origin_token' => $request->getOriginToken(),
        ];
    }

    public function getName(): string
    {
        return 'worker_message';
    }

    public function getMessage(): Data
    {
        return $this->data['message'];
    }

    public function getStamps(): Data
    {
        return $this->data['stamps'];
    }

    public function getTransport(): string
    {
        return $this->data['transport'];
    }

    public function getAttempt(): int
    {
        return $this->data['attempt'];
    }

    public function getOriginToken(): ?string
    {
        return $this->data['origin_token'];
    }
}
