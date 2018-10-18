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
        if (!\is_object($message)) {
            throw new \TypeError(sprintf('Invalid argument provided to "%s()": expected object but got %s.', __METHOD__, \gettype($message)));
        }
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
    public static function wrap($message, string $name = null): self
    {
        $envelope = $message instanceof self ? clone $message : new self($message);
        if (null !== $name) {
            return $envelope->with(new MessageConfiguration($name));
        }
        unset($envelope->items[MessageConfiguration::class]);

        return $envelope;
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

    public function getMessageName(): ?string
    {
        $config = $this->items[MessageConfiguration::class] ?? null;

        return $config ? $config->getName() : null;
    }
}
