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
 * @author Christian Sciberras <christian@sciberras.me>
 *
 * @internal
 */
class AnonFuncNode extends Node
{
    /**
     * 
     * @param NameNode[] $parameters
     * @param Node|null $body
     */
    public function __construct(array $parameters, Node $body = null)
    {
        parent::__construct(
            array(
                'parameters' => $parameters,
                'body' => $body,
            )
        );
    }

    public function compile(Compiler $compiler)
    {
        $arguments = [];
        
        foreach ($this->nodes['parameters'] as $parameterNode) {
            $arguments[] = $compiler->subcompile($parameterNode);
        }
        
        $compiler->raw(
            sprintf(
                'function (%s) { return %s; }',
                implode(', ', $arguments),
                $this->nodes['body'] ? $compiler->subcompile($this->nodes['body']) : 'null'
            )
        );
    }

    public function evaluate($functions, $values)
    {
        if (!$this->nodes['body']) {
            return function () {};
        }
        
        $paramNames = array();
        
        foreach ($this->nodes['parameters'] as $parameterNode) {
            $nodeData = $parameterNode->toArray();
            $paramNames[] = $nodeData[0];
        }
        
        return function () use($functions, $paramNames) {
            $passedValues = array_combine($paramNames, func_get_args());
            return $this->nodes['body']->evaluate($functions, $passedValues);
        };
    }

    public function toArray()
    {
        $array = array();

        foreach ($this->nodes['parameters'] as $node) {
            $array[] = ', ';
            $array[] = $node;
        }
        $array[0] = '(';
        $array[] = ') -> {';
        if ($this->nodes['body']) {
            $array[] = $this->nodes['body'];
        }
        $array[] = '}';

        return $array;
    }
}
