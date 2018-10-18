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

use Symfony\Component\Messenger\Stamp\StampInterface;

/**
 * A message wrapped in an envelope with stamps (configurations, markers, ...).
 *
 * @author Maxime Steinhausser <maxime.steinhausser@gmail.com>
 */
final class Envelope
{
    private $stamps = array();
    private $message;

    /**
     * @param object $message
     */
    public function __construct($message, StampInterface ...$stamps)
    {
        $this->message = $message;

        foreach ($stamps as $stamp) {
            $this->stamps[\get_class($stamp)] = $stamp;
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
     * @return Envelope a new Envelope instance with additional stamp
     */
    public function with(StampInterface ...$stamps): self
    {
        $cloned = clone $this;

        foreach ($stamps as $stamp) {
            $cloned->stamps[\get_class($stamp)] = $stamp;
        }

        return $cloned;
    }

    public function withMessage($message): self
    {
        $cloned = clone $this;

        $cloned->message = $message;

        return $cloned;
    }

    public function get(string $stampFqcn): ?StampInterface
    {
        return $this->stamps[$stampFqcn] ?? null;
    }

    /**
     * @return StampInterface[] indexed by fqcn
     */
    public function all(): array
    {
        return $this->stamps;
    }

    /**
     * @return object The original message contained in the envelope
     */
    public function getMessage()
    {
        return $this->message;
    }

    /**
     * @param object $target
     *
     * @return Envelope|object The original message or the envelope if the target supports it
     *                         (i.e implements {@link EnvelopeAwareInterface}).
     */
    public function getMessageFor($target)
    {
        return $target instanceof EnvelopeAwareInterface ? $this : $this->message;
    }
}
