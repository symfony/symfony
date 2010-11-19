<?php

namespace Symfony\Bundle\TwigBundle\Node;

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * Represents an include node.
 *
 * @author Fabien Potencier <fabien.potencier@symfony-project.com>
 */
class IncludeNode extends \Twig_Node
{
    public function __construct(\Twig_Node_Expression $expr, \Twig_Node_Expression $variables = null, $only = false, $lineno, $tag = null)
    {
        parent::__construct(array('expr' => $expr, 'variables' => $variables), array('only' => (Boolean) $only), $lineno, $tag);
    }

    /**
     * Compiles the node to PHP.
     *
     * @param \Twig_Compiler A Twig_Compiler instance
     */
    public function compile($compiler)
    {
        $compiler
            ->addDebugInfo($this)
            ->write('echo $this->env->getExtension(\'templating\')->getTemplating()->render(')
            ->subcompile($this->getNode('expr'))
            ->raw(', ')
        ;

        if (false === $this->getAttribute('only')) {
            if (null === $this->getNode('variables')) {
                $compiler->raw('$context');
            } else {
                $compiler
                    ->raw('array_merge($context, ')
                    ->subcompile($this->getNode('variables'))
                    ->raw(')')
                ;
            }
        } else {
            if (null === $this->getNode('variables')) {
                $compiler->raw('array()');
            } else {
                $compiler->subcompile($this->getNode('variables'));
            }
        }

        $compiler->raw(");\n");
    }
}
