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
                if ('label' === $blockNameSuffix) {
                    // The "label" function expects the label in the second argument.
                    // The array of variables is given in the third argument
                    $lineno = $arguments[1]->getLine();
                    $variables = new \Twig_Node_Expression_Array(array(), $lineno);
                    $givenVariables = isset($arguments[2]) ? $arguments[2] : $variables;
                    $labelKey = new \Twig_Node_Expression_Constant('label', $lineno);
                    $found = false;

                    // If the label is listed in the variables, the label given
                    // in the arguments should take precedence in the following form:
                    // labelInArgs|default(labelInAttr)
                    foreach ($givenVariables->getKeyValuePairs() as $pair) {
                        if ((string) $labelKey === (string) $pair['key']) {
                            $pair['value'] = new \Twig_Node_Expression_Filter_Default(
                                $arguments[1],
                                new \Twig_Node_Expression_Constant('default', $lineno),
                                new \Twig_Node(array($pair['value']), array(), $lineno),
                                $lineno
                            );
                            $found = true;
                        }

                        $variables->addElement($pair['value'], $pair['key']);
                    }

                    // If the label does not exist in the variables, simply add it
                    if (!$found) {
                        $variables->addElement($arguments[1], $labelKey);
                    }
                } else {
                    // All other functions than "label" expect the variables
                    // in the second argument
                    $variables = $arguments[1];
                }

                $compiler->raw(', ');
                $compiler->subcompile($variables);
            }
        }

        $compiler->raw(")");
    }
}
