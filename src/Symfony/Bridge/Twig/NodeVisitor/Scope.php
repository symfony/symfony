<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Twig\NodeVisitor;

/**
 * @author Jean-François Simon <jeanfrancois.simon@sensiolabs.com>
 */
class Scope
{
    private array $data = [];
    private bool $left = false;

    public function __construct(
        private ?self $parent = null,
    ) {
    }

    /**
     * Opens a new child scope.
     */
    public function enter(): self
    {
        return new self($this);
    }

    /**
     * Closes current scope and returns parent one.
     */
    public function leave(): ?self
    {
        $this->left = true;

        return $this->parent;
    }

    /**
     * Stores data into current scope.
     *
     * @return $this
     *
     * @throws \LogicException
     */
    public function set(string $key, mixed $value): static
    {
        if ($this->left) {
            throw new \LogicException('Left scope is not mutable.');
        }

        $this->data[$key] = $value;

        return $this;
    }

    /**
     * Tests if a data is visible from current scope.
     */
    public function has(string $key): bool
    {
        if (\array_key_exists($key, $this->data)) {
            return true;
        }

        if (null === $this->parent) {
            return false;
        }

        return $this->parent->has($key);
    }

    /**
     * Returns data visible from current scope.
     */
    public function get(string $key, mixed $default = null): mixed
    {
        if (\array_key_exists($key, $this->data)) {
            return $this->data[$key];
        }

        if (null === $this->parent) {
            return $default;
        }

        return $this->parent->get($key, $default);
    }
}
