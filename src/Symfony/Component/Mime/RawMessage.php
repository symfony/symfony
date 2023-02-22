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
    private $message;

    public function __construct(iterable|string $message)
    {
        $this->message = $message;
    }

    public function toString(): string
    {
        if (\is_string($this->message)) {
            return $this->message;
        }
        if ($this->message instanceof \Traversable) {
            $this->message = iterator_to_array($this->message, false);
        }

        return $this->message = implode('', $this->message);
    }

    public function toIterable(): iterable
    {
        if (\is_string($this->message)) {
            yield $this->message;

            return;
        }

        $message = '';
        foreach ($this->message as $chunk) {
            $message .= $chunk;
            yield $chunk;
        }
        $this->message = $message;
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
