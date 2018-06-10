<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger;

/**
 * A message wrapped in an envelope with items (configurations, markers, ...).
 *
 * @author Maxime Steinhausser <maxime.steinhausser@gmail.com>
 *
 * @experimental in 4.1
 */
final class Envelope
{
    private $items = array();
    private $message;

    /**
     * @param object                  $message
     * @param EnvelopeItemInterface[] $items
     */
    public function __construct($message, array $items = array())
    {
        $this->message = $message;
        foreach ($items as $item) {
            $this->items[\get_class($item)] = $item;
        }
    }

    /**
     * Wrap a message into an envelope if not already wrapped.
     *
     * @param Envelope|object $message
     */
    public static function wrap($message): self
    {
        return $message instanceof self ? $message : new self($message);
    }

    /**
     * @return Envelope a new Envelope instance with additional item
     */
    public function with(EnvelopeItemInterface $item): self
    {
        $cloned = clone $this;

        $cloned->items[\get_class($item)] = $item;

        return $cloned;
    }

    public function withMessage($message): self
    {
        $cloned = clone $this;

        $cloned->message = $message;

        return $cloned;
    }

    public function get(string $itemFqcn): ?EnvelopeItemInterface
    {
        return $this->items[$itemFqcn] ?? null;
    }

    /**
     * @return EnvelopeItemInterface[] indexed by fqcn
     */
    public function all(): array
    {
        return $this->items;
    }

    /**
     * @return object The original message contained in the envelope
     */
    public function getMessage()
    {
        return $this->message;
    }
}
