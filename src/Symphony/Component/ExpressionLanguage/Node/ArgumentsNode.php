<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\ExpressionLanguage\Node;

use Symphony\Component\ExpressionLanguage\Compiler;

/**
 * @author Fabien Potencier <fabien@symphony.com>
 *
 * @internal
 */
class ArgumentsNode extends ArrayNode
{
    public function compile(Compiler $compiler)
    {
        $this->compileArguments($compiler, false);
    }

    public function toArray()
    {
        $array = array();

        foreach ($this->getKeyValuePairs() as $pair) {
            $array[] = $pair['value'];
            $array[] = ', ';
        }
        array_pop($array);

        return $array;
    }
}
