<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Handler;

/**
 * @internal
 */
class BatchHandlerAdapter implements BatchHandlerInterface
{
    private readonly \Closure $handler;
    private readonly BatchStrategyInterface $batchStrategy;
    private \SplObjectStorage $ackMap;
    private ?object $lastMessage;

    public function __construct(callable $handler, BatchStrategyInterface $batchStrategy)
    {
        $this->batchStrategy = $batchStrategy;
        $this->handler = $handler(...);

        $this->ackMap = new \SplObjectStorage();
        $this->lastMessage = null;
    }

    public function __invoke(object $message, Acknowledger $ack = null): mixed
    {
        $this->lastMessage = $message;

        if (null === $ack) {
            $ack = new Acknowledger(get_debug_type($this));
            $this->ackMap[$message] = $ack;

            $this->flush(true);

            return $ack->getResult();
        }

        $this->ackMap[$message] = $ack;
        if (!$this->shouldFlush()) {
            return $this->ackMap->count();
        }

        $this->flush(true);

        return 0;
    }

    /**
     * {@inheritdoc}
     */
    public function flush(bool $force): void
    {
        if (!$this->lastMessage) {
            return;
        }

        $ackMap = $this->ackMap;
        $this->ackMap = new \SplObjectStorage();
        $this->lastMessage = null;

        $this->batchStrategy->beforeHandle();
        ($this->handler)(new Result($ackMap), ...\iterator_to_array($ackMap));
        $this->batchStrategy->afterHandle();
    }

    private function shouldFlush(): bool
    {
        return $this->lastMessage && $this->batchStrategy->shouldHandle($this->lastMessage);
    }
}
