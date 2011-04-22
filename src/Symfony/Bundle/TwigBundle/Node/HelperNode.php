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

/**
 *
 *
 * @author Fabien Potencier <fabien@symfony.com>
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
    public function compile(\Twig_Compiler $compiler)
    {
        $compiler
            ->addDebugInfo($this)
            ->raw("\$this->env->getExtension(")
            ->string('templating')
            ->raw(")->getContainer()->get(")
            ->string($this->getAttribute('helper'))
            ->raw(")->")
            ->raw($this->getAttribute('method'))
            ->raw("(")
        ;

        foreach ($this->getNode('values') as $i => $value) {
            $compiler->subcompile($value);
            if ($i !== count($this->getNode('values')) - 1) {
                $compiler->raw(', ');
            }
        }

        $compiler->raw(")");
    }
}
