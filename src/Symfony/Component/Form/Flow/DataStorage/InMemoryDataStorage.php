<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Flow\DataStorage;

/**
 * @author Yonel Ceruto <open@yceruto.dev>
 */
class InMemoryDataStorage implements DataStorageInterface
{
    private array $memory = [];

    public function __construct(
        private readonly string $key,
    ) {
    }

    public function save(object|array $data): void
    {
        $this->memory[$this->key] = $data;
    }

    public function load(object|array|null $default = null): object|array|null
    {
        return $this->memory[$this->key] ?? $default;
    }

    public function clear(): void
    {
        unset($this->memory[$this->key]);
    }
}
