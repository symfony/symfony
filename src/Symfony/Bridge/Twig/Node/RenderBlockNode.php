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
 * Compiles a call to {@link \Symfony\Component\Form\FormRendererInterface::renderBlock()}.
 *
 * The function name is used as block name. For example, if the function name
 * is "foo", the block "foo" will be rendered.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class RenderBlockNode extends \Twig_Node_Expression_Function
{
    public function compile(\Twig_Compiler $compiler)
    {
        $compiler->addDebugInfo($this);
        $arguments = iterator_to_array($this->getNode('arguments'));
        $compiler->write('$this->env->getRuntime(\'Symfony\Bridge\Twig\Form\TwigRenderer\')->renderBlock(');

        if (isset($arguments[0])) {
            $compiler->subcompile($arguments[0]);
            $compiler->raw(', \''.$this->getAttribute('name').'\'');

            if (isset($arguments[1])) {
                $compiler->raw(', ');
                $compiler->subcompile($arguments[1]);
            }
        }

        $compiler->raw(')');
    }
}
