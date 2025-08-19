<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Uid\Factory;

use Symfony\Component\Uid\Exception\LogicException;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Uid\UuidV1;
use Symfony\Component\Uid\UuidV3;
use Symfony\Component\Uid\UuidV4;
use Symfony\Component\Uid\UuidV5;
use Symfony\Component\Uid\UuidV6;
use Symfony\Component\Uid\UuidV7;
use Symfony\Component\Uid\UuidV8;

/**
 * A UUID factory that returns UUIDs from a predefined sequence, suitable for testing.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
final class MockUuidFactory implements UuidFactoryInterface
{
    private array $sequence = [];
    private int $index = 0;

    /**
     * @param iterable<string|Uuid> $sequence
     */
    public function __construct(iterable $sequence = [])
    {
        $this->setSequence($sequence);
    }

    /**
     * Sets the sequence of UUIDs to return.
     *
     * @param iterable<string|Uuid> $sequence
     */
    public function setSequence(iterable $sequence): void
    {
        $this->sequence = [];
        $this->index = 0;

        foreach ($sequence as $uuid) {
            if (is_string($uuid)) {
                $uuid = Uuid::fromString($uuid);
            }

            if (!$uuid instanceof Uuid) {
                throw new \InvalidArgumentException('Sequence must contain only strings or Uuid instances.');
            }

            $this->sequence[] = $uuid;
        }
    }

    /**
     * Resets the sequence to start from the beginning.
     */
    public function reset(): void
    {
        $this->index = 0;
    }

    public function v1(): UuidV1
    {
        return $this->getNext(UuidV1::class);
    }

    public function v3(): UuidV3
    {
        return $this->getNext(UuidV3::class);
    }

    public function v4(): UuidV4
    {
        return $this->getNext(UuidV4::class);
    }

    public function v5(): UuidV5
    {
        return $this->getNext(UuidV5::class);
    }

    public function v6(): UuidV6
    {
        return $this->getNext(UuidV6::class);
    }

    public function v7(): UuidV7
    {
        return $this->getNext(UuidV7::class);
    }

    public function v8(): UuidV8
    {
        return $this->getNext(UuidV8::class);
    }

    /**
     * @template T of Uuid
     * @param class-string<T> $expectedClass
     * @return T
     */
    private function getNext(string $expectedClass): Uuid
    {
        if (!isset($this->sequence[$this->index])) {
            throw new LogicException('No more UUIDs in sequence. You may need to add more UUIDs to the sequence or call reset() to start over.');
        }

        $uuid = $this->sequence[$this->index++];

        if (!$uuid instanceof $expectedClass) {
            throw new LogicException(sprintf('Expected UUID of type "%s", got "%s".', $expectedClass, get_class($uuid)));
        }

        return $uuid;
    }
}