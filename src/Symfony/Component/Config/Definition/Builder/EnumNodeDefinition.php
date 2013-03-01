<?php

namespace Symfony\Component\Config\Definition\Builder;

use Symfony\Component\Config\Definition\EnumNode;
use Symfony\Component\Config\Definition\Builder\ScalarNodeDefinition;

/**
 * Enum Node Definition.
 *
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 */
class EnumNodeDefinition extends ScalarNodeDefinition
{
    private $values;

    public function values(array $values)
    {
        $values = array_unique($values);

        if (count($values) <= 1) {
            throw new \InvalidArgumentException('->values() must be called with at least two distinct values.');
        }

        $this->values = $values;

        return $this;
    }

    /**
     * Instantiate a Node
     *
     * @return EnumNode The node
     */
    protected function instantiateNode()
    {
        if (null === $this->values) {
            throw new \RuntimeException('You must call ->values() on enum nodes.');
        }

        return new EnumNode($this->name, $this->parent, $this->values);
    }
}
