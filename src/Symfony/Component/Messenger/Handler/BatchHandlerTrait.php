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
 * @author Nicolas Grekas <p@tchwork.com>
 */
trait BatchHandlerTrait
{
    private array $jobs = [];
    private ?int $lastMessageAt = null;

    public function flush(bool $force): void
    {
        if (!$force && !$this->shouldFlush()) {
            return;
        }

        if ($jobs = $this->jobs) {
            $this->jobs = [];
            $this->process($jobs);
        }
    }

    /**
     * @param Acknowledger|null $ack The function to call to ack/nack the $message.
     *                               The message should be handled synchronously when null.
     *
     * @return mixed The number of pending messages in the batch if $ack is not null,
     *               the result from handling the message otherwise
     */
    private function handle(object $message, ?Acknowledger $ack): mixed
    {
        $this->lastMessageAt = time();

        if (null === $ack) {
            $ack = new Acknowledger(get_debug_type($this));
            $this->jobs[] = [$message, $ack];
            $this->flush(true);

            return $ack->getResult();
        }

        $this->jobs[] = [$message, $ack];
        if (!$this->shouldFlush()) {
            return \count($this->jobs);
        }

        $this->flush(true);

        return 0;
    }

    private function shouldFlush(): bool
    {
        if ($this->getBatchSize() <= \count($this->jobs)) {
            return true;
        }

        $idleTimeout = $this->getIdleTimeout();
        if (null !== $idleTimeout && null !== $this->lastMessageAt) {
            return (time() - $this->lastMessageAt) >= $idleTimeout;
        }

        return false;
    }

    /**
     * Completes the jobs in the list.
     *
     * @param list<array{0: object, 1: Acknowledger}> $jobs A list of pairs of messages and their corresponding acknowledgers
     */
    abstract private function process(array $jobs): void;

    private function getBatchSize(): int
    {
        return 10;
    }

    /**
     * @return int|null The idle timeout in seconds
     */
    private function getIdleTimeout(): ?int
    {
        return 1;
    }
}
