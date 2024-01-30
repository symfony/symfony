<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mime;

use Symfony\Component\Mime\Exception\LogicException;

/**
 * @author Fabien Potencier <fabien@symfony.com>
 */
class RawMessage
{
    private iterable|string $message;
    private bool $isGeneratorClosed;

    public function __construct(iterable|string $message)
    {
        $this->message = $message;
    }

    public function toString(): string
    {
        if (\is_string($this->message)) {
            return $this->message;
        }

        $message = '';
        foreach ($this->message as $chunk) {
            $message .= $chunk;
        }

        return $this->message = $message;
    }

    public function toIterable(): iterable
    {
        if ($this->isGeneratorClosed ?? false) {
            trigger_deprecation('symfony/mime', '6.4', 'Sending an email with a closed generator is deprecated and will throw in 7.0.');
            // throw new LogicException('Unable to send the email as its generator is already closed.');
        }

        if (\is_string($this->message)) {
            yield $this->message;

            return;
        }

        if ($this->message instanceof \Generator) {
            $message = '';
            foreach ($this->message as $chunk) {
                $message .= $chunk;
                yield $chunk;
            }
            $this->isGeneratorClosed = !$this->message->valid();
            $this->message = $message;

            return;
        }

        foreach ($this->message as $chunk) {
            yield $chunk;
        }
    }

    /**
     * @return void
     *
     * @throws LogicException if the message is not valid
     */
    public function ensureValidity()
    {
    }

    public function __serialize(): array
    {
        return [$this->toString()];
    }

    public function __unserialize(array $data): void
    {
        [$this->message] = $data;
    }
}
