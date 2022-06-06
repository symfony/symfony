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
    /**
     * @var array<string, list<StampInterface>>
     */
    private $stamps = [];
    private $message;

    /**
     * @param StampInterface[] $stamps
     */
    public function __construct(object $message, array $stamps = [])
    {
        $this->message = $message;

        foreach ($stamps as $stamp) {
            $this->stamps[\get_class($stamp)][] = $stamp;
        }
    }

    /**
     * Makes sure the message is in an Envelope and adds the given stamps.
     *
     * @param object|Envelope  $message
     * @param StampInterface[] $stamps
     */
    public static function wrap(object $message, array $stamps = []): self
    {
        $envelope = $message instanceof self ? $message : new self($message);

        return $envelope->with(...$stamps);
    }

    /**
     * @return static A new Envelope instance with additional stamp
     */
    public function with(StampInterface ...$stamps): self
    {
        $cloned = clone $this;

        foreach ($stamps as $stamp) {
            $cloned->stamps[\get_class($stamp)][] = $stamp;
        }

        return $cloned;
    }

    /**
     * @return static A new Envelope instance without any stamps of the given class
     */
    public function withoutAll(string $stampFqcn): self
    {
        $cloned = clone $this;

        unset($cloned->stamps[$this->resolveAlias($stampFqcn)]);

        return $cloned;
    }

    /**
     * Removes all stamps that implement the given type.
     */
    public function withoutStampsOfType(string $type): self
    {
        $cloned = clone $this;
        $type = $this->resolveAlias($type);

        foreach ($cloned->stamps as $class => $stamps) {
            if ($class === $type || is_subclass_of($class, $type)) {
                unset($cloned->stamps[$class]);
            }
        }

        return $cloned;
    }

    public function last(string $stampFqcn): ?StampInterface
    {
        return isset($this->stamps[$stampFqcn = $this->resolveAlias($stampFqcn)]) ? end($this->stamps[$stampFqcn]) : null;
    }

    /**
     * @return StampInterface[]|StampInterface[][] The stamps for the specified FQCN, or all stamps by their class name
     */
    public function all(string $stampFqcn = null): array
    {
        if (null !== $stampFqcn) {
            return $this->stamps[$this->resolveAlias($stampFqcn)] ?? [];
        }

        return $this->stamps;
    }

    /**
     * @return object The original message contained in the envelope
     */
    public function getMessage(): object
    {
        return $this->message;
    }

    /**
     * BC to be removed in 6.0.
     */
    private function resolveAlias(string $fqcn): string
    {
        static $resolved;

        return $resolved[$fqcn] ?? ($resolved[$fqcn] = class_exists($fqcn) ? (new \ReflectionClass($fqcn))->getName() : $fqcn);
    }
}
