<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Twig\Node;

use Twig\Compiler;
use Twig\Node\Expression\AbstractExpression;
use Twig\Node\Node;

/**
 * @author Fabien Potencier <fabien@symfony.com>
 *
 * @final since Symfony 4.4
 */
class TransDefaultDomainNode extends Node
{
    public function __construct(AbstractExpression $expr, int $lineno = 0, string $tag = null)
    {
        parent::__construct(['expr' => $expr], [], $lineno, $tag);
    }

    /**
     * @return void
     */
    public function compile(Compiler $compiler)
    {
        // noop as this node is just a marker for TranslationDefaultDomainNodeVisitor
    }
}
