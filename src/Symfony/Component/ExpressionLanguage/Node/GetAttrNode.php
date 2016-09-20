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

use Symfony\Component\Console\Exception\RuntimeException;
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
                    ->raw($this->nodes['attribute']->attributes['name'])
                ;
                break;

            case self::METHOD_CALL:
                $compiler
                    ->compile($this->nodes['node'])
                    ->raw('->')
                    ->raw($this->nodes['attribute']->attributes['name'])
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

    public function evaluate($functions, $values, $strict=true)
    {
        switch ($this->attributes['type']) {
            case self::PROPERTY_CALL:
                $obj = $this->nodes['node']->evaluate($functions, $values, $strict);
                if ($strict) {
                    if (!is_object($obj)) {
                        throw new \RuntimeException('Unable to get a property on a non-object.');
                    }
                } else if (!is_array($obj) && !is_object($obj)) {
                    throw new \RuntimeException('Unable to get a property/item on a non-object/non-array.');
                }

                $property = $this->nodes['attribute']->attributes['name'];

                if (is_array($obj)) {
                    return $obj[$property];
                }

                return $obj->$property;

            case self::METHOD_CALL:
                $obj = $this->nodes['node']->evaluate($functions, $values, $strict);
                if (!is_object($obj)) {
                    throw new \RuntimeException('Unable to get a property on a non-object.');
                }

                return call_user_func_array(array($obj, $this->nodes['attribute']->attributes['name']), $this->nodes['arguments']->evaluate($functions, $values, $strict));

            case self::ARRAY_CALL:
                $array = $this->nodes['node']->evaluate($functions, $values, $strict);
                if ($strict) {
                    if (!is_array($array) && !$array instanceof \ArrayAccess && $strict) {
                        throw new \RuntimeException('Unable to get an item on a non-array.');
                    }
                } else if (!is_object($array) && !is_array($array)) {
                    throw new \RuntimeException('Unable to get item/property on on-array/non-object.');
                }

                $item = $this->nodes['attribute']->evaluate($functions, $values, $strict);

                if (is_object($array)) {
                    return $array->$item;
                }
                return $array[$item];
        }
    }

    public function toArray()
    {
        switch ($this->attributes['type']) {
            case self::PROPERTY_CALL:
                return array($this->nodes['node'], '.', $this->nodes['attribute']);

            case self::METHOD_CALL:
                return array($this->nodes['node'], '.', $this->nodes['attribute'], '(', $this->nodes['arguments'], ')');

            case self::ARRAY_CALL:
                return array($this->nodes['node'], '[', $this->nodes['attribute'], ']');
        }
    }
}
