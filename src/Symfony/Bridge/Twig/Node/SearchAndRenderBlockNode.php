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

use Twig\Compiler;
use Twig\Node\Expression\ArrayExpression;
use Twig\Node\Expression\ConditionalExpression;
use Twig\Node\Expression\ConstantExpression;
use Twig\Node\Expression\FilterExpression;
use Twig\Node\Expression\TestExpression;
use Twig\Node\Expression\FunctionExpression;
use Twig\Node\Node;

/**
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class SearchAndRenderBlockNode extends FunctionExpression
{
    public function compile(Compiler $compiler)
    {
        $compiler->addDebugInfo($this);
        preg_match('/_([^_]+)$/', $this->getAttribute('name'), $matches);
        $label = null;
        $blockNameSuffix = $matches[1];

        $arguments = iterator_to_array($this->getNode('arguments'));
        if ('label' === $blockNameSuffix) {
            if (isset($arguments[2])) {
                $arguments['variables'] = $arguments[2];
                unset($arguments[2]);
            }
            $label = null;
            if (isset($arguments[1])) {
                $label = $arguments[1];
                unset($arguments[1]);
            } elseif (isset($arguments[1])) {
                $label = $arguments['label'];
                unset($arguments['label']);
            }

            if (null !== $label) {
                $lineno = $label->getTemplateLine();
                if (!isset($arguments['variables'])) {
                    $arguments['variables'] = new ArrayExpression(array(), $lineno);
                }
                if ($label instanceof ConstantExpression) {
                    // If the label argument is given as a constant, we can either
                    // strip it away if it is empty, or integrate it into the array
                    // of variables at compile time.

                    // Only insert the label into the array if it is not empty
                    if (!twig_test_empty($label->getAttribute('value'))) {
                        $variables = new ArrayExpression(array(), $lineno);
                        $labelKey = new ConstantExpression('label', $lineno);

                        if (null !== $arguments['variables']) {
                            foreach ($arguments['variables']->getKeyValuePairs() as $pair) {
                                // Don't copy the original label attribute over if it exists
                                if ((string) $labelKey !== (string) $pair['key']) {
                                    $variables->addElement($pair['value'], $pair['key']);
                                }
                            }
                        }

                        // Insert the label argument into the array
                        $variables->addElement($label, $labelKey);
                        $arguments['variables'] = $variables;
                    }
                } else {
                    // The label argument is not a constant, but some kind of
                    // expression. This expression needs to be evaluated at runtime.
                    // Depending on the result (whether it is null or not), the
                    // label in the arguments should take precedence over the label
                    // in the attributes or not.

                    // Check at runtime whether the label is empty.
                    // If not, add it to the array at runtime.
                    $labelExpr = new ArrayExpression(array(new ConstantExpression('label', $lineno), $label), $lineno);
                    $condition = new TestExpression($label, 'empty', new Node(), $lineno);
                    $conditional = new ConditionalExpression($condition, new ArrayExpression(array(), $lineno), $labelExpr, $lineno);

                    if (count($arguments['variables'])) {
                        $arguments['variables'] = new FilterExpression(
                            new Node(array($conditional)),
                            new ConstantExpression('merge', $lineno),
                            $arguments['variables'],
                            $lineno
                        );
                    } else {
                        $arguments['variables'] = $conditional;
                    }
                }

                unset($arguments[1]);
            }
        } else {
            if (isset($arguments[1])) {
                $arguments['variables'] = $arguments[1];
                unset($arguments[1]);
            }
        }

        $this->setNode('arguments', new Node(array_merge(
            $arguments,
            array('blockNameSuffix' => new \Twig_Node_Expression_Constant($blockNameSuffix, 0))
        )));

        parent::compile($compiler);
    }
}
