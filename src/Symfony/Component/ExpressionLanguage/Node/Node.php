<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\ExpressionLanguage\Node;

use Symfony\Component\ExpressionLanguage\Compiler;

/**
 * Represents a node in the AST.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class Node
{
    public $nodes = array();
    public $attributes = array();

    /**
     * Constructor.
     *
     * @param array $nodes      An array of nodes
     * @param array $attributes An array of attributes
     */
    public function __construct(array $nodes = array(), array $attributes = array())
    {
        $this->nodes = $nodes;
        $this->attributes = $attributes;
    }

    public function __toString()
    {
        $attributes = array();
        foreach ($this->attributes as $name => $value) {
            $attributes[] = sprintf('%s: %s', $name, str_replace("\n", '', var_export($value, true)));
        }

        $repr = array(str_replace('Symfony\Component\ExpressionLanguage\Node\\', '', get_class($this)).'('.implode(', ', $attributes));

        if (count($this->nodes)) {
            foreach ($this->nodes as $node) {
                foreach (explode("\n", (string) $node) as $line) {
                    $repr[] = '    '.$line;
                }
            }

            $repr[] = ')';
        } else {
            $repr[0] .= ')';
        }

        return implode("\n", $repr);
    }

    public function compile(Compiler $compiler)
    {
        foreach ($this->nodes as $node) {
            $node->compile($compiler);
        }
    }

    public function evaluate($functions, $values)
    {
        $results = array();
        foreach ($this->nodes as $node) {
            $results[] = $node->evaluate($functions, $values);
        }

        return $results;
    }

    public function toArray()
    {
        throw new \BadMethodCallException(sprintf('Dumping a "%s" instance is not supported yet.', get_class($this)));
    }

    public function dump()
    {
        $dump = '';

        foreach ($this->toArray() as $v) {
            $dump .= is_scalar($v) ? $v : $v->dump();
        }

        return $dump;
    }

    protected function dumpString($value)
    {
        return sprintf('"%s"', addcslashes($value, "\0\t\"\\"));
    }

    protected function isHash(array $value)
    {
        $expectedKey = 0;

        foreach ($value as $key => $val) {
            if ($key !== $expectedKey++) {
                return true;
            }
        }

        return false;
    }
}
