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
class TagNode extends \Twig_Node
{
    public function __construct(\Twig_NodeInterface $htmlTag, \Twig_NodeInterface $content = null, \Twig_Node_Expression $attributes = null, $lineno, $tag = null)
    {
        parent::__construct(array('html_tag' => $htmlTag, 'content' => $content, 'html_attributes' => $attributes), array(), $lineno, $tag);
    }

    /**
     * Compiles the node to PHP.
     *
     * @param \Twig_Compiler A Twig_Compiler instance
     */
    public function compile($compiler)
    {
        $compiler->addDebugInfo($this);

        $method = null === $this->getNode('content') ? 'tag' : 'contentTag';

        if (null !== $this->getNode('content')) {
            $compiler
                ->write("ob_start();\n")
                ->subcompile($this->getNode('content'))
                ->write('$content = ob_get_clean();')
            ;
        }

        $compiler
            ->write('echo $this->env->getExtension(\'html\')->getGenerator()->'.$method.'(')
            ->subcompile($this->getNode('html_tag'))
            ->raw(', ')
        ;

        if (null !== $this->getNode('content')) {
            $compiler
                ->raw('$content, ')
            ;
        }

        $compiler
            ->subcompile($this->getNode('html_attributes'))
            ->raw(');')
        ;
    }
}
