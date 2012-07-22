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
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class SearchAndRenderBlockNode extends \Twig_Node_Expression_Function
{
    public function compile(\Twig_Compiler $compiler)
    {
        $compiler->addDebugInfo($this);
        $compiler->raw('$this->env->getExtension(\'form\')->renderer->searchAndRenderBlock(');

        preg_match('/_([^_]+)$/', $this->getAttribute('name'), $matches);

        $label = null;
        $arguments = iterator_to_array($this->getNode('arguments'));
        $blockNameSuffix = $matches[1];

        if (isset($arguments[0])) {
            $compiler->subcompile($arguments[0]);
            $compiler->raw(', \'' . $blockNameSuffix . '\'');

            if (isset($arguments[1])) {
                $compiler->raw(', ');

                // The "label" function allows one extra argument here, the label
                if ('label' === $blockNameSuffix) {
                    if (isset($arguments[2])) {
                        $compiler->subcompile($arguments[2]);
                        $compiler->raw(' + ');
                    }

                    // Add the label to the variable array
                    $compiler->raw('array(\'label\' => ');
                    $compiler->subcompile($arguments[1]);
                    $compiler->raw(')');
                } else {
                    $compiler->subcompile($arguments[1]);
                }
            }
        }

        $compiler->raw(")");
    }
}
