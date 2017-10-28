<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\ExpressionLanguage;

/**
 * Represents an already parsed expression.
 *
 * @author Fabien Potencier <fabien@symfony.com>
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
