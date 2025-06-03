<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Stamp;

use Symfony\Component\Lock\Key;
use Symfony\Component\Messenger\Exception\LogicException;

final class DeduplicateStamp implements StampInterface
{
    private Key $key;

    public function __construct(
        private string $resource,
        private ?float $ttl = 300.0,
        private bool $onlyDeduplicateInQueue = false,
    ) {
        if (!class_exists(Key::class)) {
            throw new LogicException(\sprintf('You cannot use the "%s" as the Lock component is not installed. Try running "composer require symfony/lock".', self::class));
        }
    }

    public function onlyDeduplicateInQueue(): bool
    {
        return $this->onlyDeduplicateInQueue;
    }

    public function getKey(): Key
    {
        return $this->key ??= new Key($this->resource);
    }

    public function getTtl(): ?float
    {
        return $this->ttl;
    }

    public function __serialize(): array
    {
        return [$this->resource, $this->ttl, $this->onlyDeduplicateInQueue];
    }

    public function __unserialize(array $data): void
    {
        [$this->resource, $this->ttl, $this->onlyDeduplicateInQueue] = $data;
    }
}
