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

use Symfony\Component\ExpressionLanguage\Node\Node;

/**
 * Represents an already serialized parsed expression.
 *
 * The serialized form passed to the constructor MUST come from a trusted source:
 * by contract, callers are expected to serialize their own ParsedExpression
 * instances and to keep the resulting bytes under their control. This class
 * does NOT validate the unserialize() allow-list and will instantiate any
 * class referenced by the payload. Pass attacker-controlled bytes here at
 * your peril.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class SerializedParsedExpression extends ParsedExpression
{
    /**
     * @param string $expression An expression
     * @param string $nodes      The serialized nodes for the expression
     */
    public function __construct(
        string $expression,
        private string $nodes,
    ) {
        $this->expression = $expression;
    }

    public function getNodes(): Node
    {
        return unserialize($this->nodes);
    }
}
