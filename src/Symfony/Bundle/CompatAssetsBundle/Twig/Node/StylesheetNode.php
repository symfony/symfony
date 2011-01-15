<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\CompatAssetsBundle\Twig\Node;

/**
 * Represents a stylesheet node.
 *
 * @author Fabien Potencier <fabien.potencier@symfony-project.com>
 */
class StylesheetNode extends \Twig_Node
{
    public function __construct(\Twig_Node_Expression $expr, \Twig_Node_Expression $attributes, $lineno, $tag = null)
    {
        parent::__construct(array('expr' => $expr, 'attributes' => $attributes), array(), $lineno, $tag);
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
            ->write("echo \$this->env->getExtension('templating')->getContainer()->get('templating.helper.stylesheets')->add(")
            ->subcompile($this->getNode('expr'))
            ->raw(', ')
            ->subcompile($this->getNode('attributes'))
            ->raw(");\n")
        ;
    }
}
