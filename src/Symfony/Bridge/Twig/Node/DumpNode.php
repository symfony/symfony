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
 * @author Julien Galenski <julien.galenski@gmail.com>
 */
class DumpNode extends \Twig_Node
{
    public function __construct(\Twig_NodeInterface $values = null, $lineno, $tag = null)
    {
        parent::__construct(array('values' => $values), array(), $lineno, $tag);
    }

    /**
     * {@inheritdoc}
     */
    public function compile(\Twig_Compiler $compiler)
    {
        $compiler
            ->write("if (\$this->env->isDebug()) {\n")
            ->indent()
        ;

        $values = $this->getNode('values');

        if (null === $values) {
            // remove embedded templates (macros) from the context
            $compiler
                ->write("\$vars = array();\n")
                ->write("foreach (\$context as \$key => \$value) {\n")
                ->indent()
                ->write("if (!\$value instanceof Twig_Template) {\n")
                ->indent()
                ->write("\$vars[\$key] = \$value;\n")
                ->outdent()
                ->write("}\n")
                ->outdent()
                ->write("}\n")
                ->addDebugInfo($this)
                ->write('\Symfony\Component\Debug\Debug::dump($vars);'."\n")
            ;
        } elseif (1 === $values->count()) {
            $compiler
                ->addDebugInfo($this)
                ->write('\Symfony\Component\Debug\Debug::dump(')
                ->subcompile($values->getNode(0))
                ->raw(");\n")
            ;
        } else {
            $compiler
                ->addDebugInfo($this)
                ->write('\Symfony\Component\Debug\Debug::dump(array(')
                ->indent()
            ;
            foreach ($values as $node) {
                $compiler->addIndentation();
                if ($node->hasAttribute('name')) {
                    $compiler
                        ->string($node->getAttribute('name'))
                        ->raw(' => ')
                    ;
                }
                $compiler
                    ->subcompile($node)
                    ->raw(",\n")
                ;
            }
            $compiler
                ->outdent()
                ->raw("));\n")
            ;
        }

        $compiler
            ->outdent()
            ->write("}\n")
        ;
    }
}
