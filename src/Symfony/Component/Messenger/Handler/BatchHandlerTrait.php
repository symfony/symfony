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

use Symfony\Component\Messenger\Exception\LogicException;

/**
 * @author Nicolas Grekas <p@tchwork.com>
 */
trait BatchHandlerTrait
{
    private $jobs = [];

    /**
     * {@inheritdoc}
     */
    public function flush(bool $force): void
    {
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
    private function handle(object $message, ?Acknowledger $ack)
    {
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
        return 10 <= \count($this->jobs);
    }

    /**
     * Completes the jobs in the list.
     *
     * @list<array{0: object, 1: Acknowledger}> $jobs A list of pairs of messages and their corresponding acknowledgers
     */
    private function process(array $jobs): void
    {
        throw new LogicException(sprintf('"%s" should implement abstract method "process()".', get_debug_type($this)));
    }
}
