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

use Symphony\Component\ExpressionLanguage\Node\Node;

/**
 * Represents an already parsed expression.
 *
 * @author Fabien Potencier <fabien@symphony.com>
 */
class ParsedExpression extends Expression
{
    private $nodes;

    /**
     * @param string $expression An expression
     * @param Node   $nodes      A Node representing the expression
     */
    public function __construct(string $expression, Node $nodes)
    {
        parent::__construct($expression);

        $this->nodes = $nodes;
    }

    public function getNodes()
    {
        return $this->nodes;
    }
}
