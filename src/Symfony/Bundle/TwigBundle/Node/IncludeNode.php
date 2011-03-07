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
 * Represents an include node.
 *
 * @author Fabien Potencier <fabien@symfony.com>
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
    public function compile(\Twig_Compiler $compiler)
    {
        // template
        $compiler
            ->addDebugInfo($this)
            ->write("\$template = ")
            ->subcompile($this->getNode('expr'))
            ->raw(";\n")
            ->write("if (\$template instanceof Twig_Template) {\n")
            ->indent()
        ;

        // template is a Twig_Template instance
        $compiler->write("\$template->display(");
        $this->compileTemplateVariables($compiler);
        $compiler
            ->raw(");\n")
            ->outdent()
            ->write("} else {\n")
            ->indent()
        ;

        // else use the templating engine
        $compiler->write("echo \$this->env->getExtension('templating')->getTemplating()->render(\$template, ");
        $this->compileTemplateVariables($compiler);
        $compiler
            ->raw(");\n")
            ->outdent()
            ->write("}\n")
        ;
    }

    protected function compileTemplateVariables($compiler)
    {
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
    }
}
