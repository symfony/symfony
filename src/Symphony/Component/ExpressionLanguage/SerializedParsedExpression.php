<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\ExpressionLanguage;

/**
 * Represents an already parsed expression.
 *
 * @author Fabien Potencier <fabien@symphony.com>
 */
class SerializedParsedExpression extends ParsedExpression
{
    private $nodes;

    /**
     * @param string $expression An expression
     * @param string $nodes      The serialized nodes for the expression
     */
    public function __construct(string $expression, string $nodes)
    {
        $this->expression = $expression;
        $this->nodes = $nodes;
    }

    public function getNodes()
    {
        return unserialize($this->nodes);
    }
}
