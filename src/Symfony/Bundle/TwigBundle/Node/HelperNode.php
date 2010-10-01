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
class HelperNode extends \Twig_Node
{
    public function __construct($helper, $method, \Twig_Node_Expression_Array $values, $lineno, $tag = null)
    {
        parent::__construct(array('values' => $values), array('helper' => $helper, 'method' => $method), $lineno, $tag);
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
            ->raw("\$this->env->getExtension(")
            ->string('symfony.helpers')
            ->raw(")->getContainer()->get(")
            ->string($this['helper'])
            ->raw(")->")
            ->raw($this['method'])
            ->raw("(")
        ;

        foreach ($this->values as $i => $value) {
            $compiler->subcompile($value);
            if ($i !== count($this->values) - 1) {
                $compiler->raw(', ');
            }
        }

        $compiler->raw(")");
    }
}
