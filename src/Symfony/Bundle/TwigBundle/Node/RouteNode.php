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
 * 
 *
 * @author Fabien Potencier <fabien.potencier@symfony-project.com>
 */
class RouteNode extends \Twig_Node
{
    public function __construct(\Twig_NodeInterface $route, \Twig_Node_Expression $attributes = null, $absolute, $lineno, $tag = null)
    {
        parent::__construct(array('route' => $route, 'route_attributes' => $attributes), array('absolute' => $absolute), $lineno, $tag);
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
            ->write('echo $this->env->getExtension(\'templating\')->getContainer()->get(\'router\')->generate(')
            ->subcompile($this->getNode('route'))
            ->raw(', ')
        ;

        $attr = $this->getNode('route_attributes');
        if ($attr) {
            $compiler->subcompile($attr);
        } else {
            $compiler->raw('array()');
        }
        $compiler->raw(', ')
            ->raw($this->getAttribute('absolute') ? 'true' : 'false')
            ->raw(");")
        ;
    }
}
