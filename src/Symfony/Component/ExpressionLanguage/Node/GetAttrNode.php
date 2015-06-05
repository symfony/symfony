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
 * @author Fabien Potencier <fabien@symfony.com>
 *
 * @internal
 */
class GetAttrNode extends Node
{
    const PROPERTY_CALL = 1;
    const METHOD_CALL = 2;
    const ARRAY_CALL = 3;

    public function __construct(Node $node, Node $attribute, ArrayNode $arguments, $type)
    {
        parent::__construct(
            array('node' => $node, 'attribute' => $attribute, 'arguments' => $arguments),
            array('type' => $type)
        );
    }

    public function compile(Compiler $compiler)
    {
        switch ($this->attributes['type']) {
            case self::PROPERTY_CALL:
                $compiler
                    ->compile($this->nodes['node'])
                    ->raw('->')
                    ->raw($this->nodes['attribute']->attributes['value'])
                ;
                break;

            case self::METHOD_CALL:
                $compiler
                    ->compile($this->nodes['node'])
                    ->raw('->')
                    ->raw($this->nodes['attribute']->attributes['value'])
                    ->raw('(')
                    ->compile($this->nodes['arguments'])
                    ->raw(')')
                ;
                break;

            case self::ARRAY_CALL:
                $compiler
                    ->compile($this->nodes['node'])
                    ->raw('[')
                    ->compile($this->nodes['attribute'])->raw(']')
                ;
                break;
        }
    }

    public function evaluate($functions, $values)
    {
        switch ($this->attributes['type']) {
            case self::PROPERTY_CALL:
                $obj = $this->nodes['node']->evaluate($functions, $values);
                if (!is_object($obj)) {
                    throw new \RuntimeException('Unable to get a property on a non-object.');
                }

                $property = $this->nodes['attribute']->attributes['value'];

                if (!property_exists($obj, $property) && !method_exists($obj, '__get')) {
                    throw new \RuntimeException(sprintf(
                        'Object of class %s does not support property %s.',
                        get_class($obj),
                        $property
                    ));
                }

                return $obj->$property;

            case self::METHOD_CALL:
                $obj = $this->nodes['node']->evaluate($functions, $values);
                if (!is_object($obj)) {
                    throw new \RuntimeException('Unable to get a property on a non-object.');
                }

                $method = $this->nodes['attribute']->attributes['value'];

                if (!method_exists($obj, $method) && !method_exists($obj, '__call')) {
                    throw new \RuntimeException(sprintf(
                        'Object of class %s does not support call of method %s.',
                        get_class($obj),
                        $method
                    ));
                }

                return call_user_func_array(array($obj, $method), $this->nodes['arguments']->evaluate($functions, $values));

            case self::ARRAY_CALL:
                $array = $this->nodes['node']->evaluate($functions, $values);
                if (!is_array($array) && !$array instanceof \ArrayAccess) {
                    throw new \RuntimeException('Unable to get an item on a non-array.');
                }

                $index = $this->nodes['attribute']->evaluate($functions, $values);
                if (!array_key_exists($index, $array)) {
                    throw new \RuntimeException(sprintf(
                        'Unable to access requested index %s in array %s.',
                        $index,
                        $this->nodes['node']->attributes['name']
                    ));
                }

                return $array[$index];
        }
    }
}
