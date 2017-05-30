<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\TwigBundle\Node;

use Twig\Compiler;
use Twig\Node\Expression\AbstractExpression;
use Twig\Node\Node;

/**
 * Represents a render node.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 *
 * @deprecated since version 2.2, to be removed in 3.0.
 */
class RenderNode extends Node
{
    public function __construct(AbstractExpression $expr, AbstractExpression $options, $lineno, $tag = null)
    {
        parent::__construct(array('expr' => $expr, 'options' => $options), array(), $lineno, $tag);
    }

    public function compile(Compiler $compiler)
    {
        $compiler
            ->addDebugInfo($this)
            ->write("echo \$this->env->getExtension('Symfony\Bundle\TwigBundle\Extension\ActionsExtension')->renderUri(")
            ->subcompile($this->getNode('expr'))
            ->raw(', ')
            ->subcompile($this->getNode('options'))
            ->raw(");\n")
        ;
    }
}
