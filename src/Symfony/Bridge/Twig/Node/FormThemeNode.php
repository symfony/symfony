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

/**
 * @author Fabien Potencier <fabien@symfony.com>
 */
class FormThemeNode extends \Twig_Node
{
    public function __construct(\Twig_NodeInterface $form, \Twig_NodeInterface $resources, $lineno, $tag = null)
    {
        parent::__construct(array('form' => $form, 'resources' => $resources), array(), $lineno, $tag);
    }

    /**
     * Compiles the node to PHP.
     *
     * @param \Twig_Compiler $compiler A Twig_Compiler instance
     */
    public function compile(\Twig_Compiler $compiler)
    {
        $compiler
            ->addDebugInfo($this)
            ->write('if (')
            ->subcompile($this->getNode('form'))
            ->raw(" instanceof \\Symfony\\Component\\Form\\FormView) {\n")
            ->write('    $this->env->getExtension(\'form\')->renderer->setTheme(')
            ->subcompile($this->getNode('form'))
            ->raw(', ')
            ->subcompile($this->getNode('resources'))
            ->raw(");\n")
            ->write("} else {\n")
            ->write('    $forms = array();'."\n")
            ->write('    foreach (')
            ->subcompile($this->getNode('form'))
            ->raw(' as $contextVar) {' . "\n")
            ->write('        if ($contextVar instanceof \\Symfony\\Component\\Form\\FormView) {'."\n")
            ->write('            $this->env->getExtension(\'form\')->renderer->setTheme($contextVar, ')
            ->subcompile($this->getNode('resources'))
            ->raw(");\n")
            ->write("        }\n")
            ->write("    }\n")
            ->write("}\n");
        ;
    }
}
