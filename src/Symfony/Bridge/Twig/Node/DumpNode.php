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

use Twig\Attribute\YieldReady;
use Twig\Compiler;
use Twig\Node\Node;

/**
 * @author Julien Galenski <julien.galenski@gmail.com>
 */
#[YieldReady]
final class DumpNode extends Node
{
    private string $varPrefix;

    public function __construct(string $varPrefix, ?Node $values, int $lineno, ?string $tag = null)
    {
        $nodes = [];
        if (null !== $values) {
            $nodes['values'] = $values;
        }

        parent::__construct($nodes, [], $lineno, $tag);
        $this->varPrefix = $varPrefix;
    }

    public function compile(Compiler $compiler): void
    {
        $compiler
            ->write("if (\$this->env->isDebug()) {\n")
            ->indent();

        if (!$this->hasNode('values')) {
            // remove embedded templates (macros) from the context
            $compiler
                ->write(sprintf('$%svars = [];'."\n", $this->varPrefix))
                ->write(sprintf('foreach ($context as $%1$skey => $%1$sval) {'."\n", $this->varPrefix))
                ->indent()
                ->write(sprintf('if (!$%sval instanceof \Twig\Template) {'."\n", $this->varPrefix))
                ->indent()
                ->write(sprintf('$%1$svars[$%1$skey] = $%1$sval;'."\n", $this->varPrefix))
                ->outdent()
                ->write("}\n")
                ->outdent()
                ->write("}\n")
                ->addDebugInfo($this)
                ->write(sprintf('\Symfony\Component\VarDumper\VarDumper::dump($%svars);'."\n", $this->varPrefix));
        } elseif (($values = $this->getNode('values')) && 1 === $values->count()) {
            $compiler
                ->addDebugInfo($this)
                ->write('\Symfony\Component\VarDumper\VarDumper::dump(')
                ->subcompile($values->getNode(0))
                ->raw(");\n");
        } else {
            $compiler
                ->addDebugInfo($this)
                ->write('\Symfony\Component\VarDumper\VarDumper::dump(['."\n")
                ->indent();
            foreach ($values as $node) {
                $compiler->write('');
                if ($node->hasAttribute('name')) {
                    $compiler
                        ->string($node->getAttribute('name'))
                        ->raw(' => ');
                }
                $compiler
                    ->subcompile($node)
                    ->raw(",\n");
            }
            $compiler
                ->outdent()
                ->write("]);\n");
        }

        $compiler
            ->outdent()
            ->write("}\n");
    }
}
