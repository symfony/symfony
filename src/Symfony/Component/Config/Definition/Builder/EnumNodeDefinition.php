<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Config\Definition\Builder;

use Symfony\Component\Config\Definition\EnumNode;

/**
 * Enum Node Definition.
 *
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 */
class EnumNodeDefinition extends ScalarNodeDefinition
{
    private array $values;
    private string $enumFqcn;

    /**
     * @return $this
     */
    public function values(array $values): static
    {
        if (!$values) {
            throw new \InvalidArgumentException('->values() must be called with at least one value.');
        }

        $this->values = $values;

        return $this;
    }

    /**
     * @param class-string<\UnitEnum> $enumFqcn
     *
     * @return $this
     */
    public function enumFqcn(string $enumFqcn): static
    {
        if (!enum_exists($enumFqcn)) {
            throw new \InvalidArgumentException(\sprintf('The enum class "%s" does not exist.', $enumFqcn));
        }

        $this->enumFqcn = $enumFqcn;

        return $this;
    }

    /**
     * Instantiate a Node.
     *
     * @throws \RuntimeException
     */
    protected function instantiateNode(): EnumNode
    {
        if (!isset($this->values) && !isset($this->enumFqcn)) {
            throw new \RuntimeException('You must call either ->values() or ->enumFqcn() on enum nodes.');
        }

        if (isset($this->values) && isset($this->enumFqcn)) {
            throw new \RuntimeException('You must call either ->values() or ->enumFqcn() on enum nodes but not both.');
        }

        return new EnumNode($this->name, $this->parent, $this->values ?? [], $this->pathSeparator, $this->enumFqcn ?? null);
    }
}
