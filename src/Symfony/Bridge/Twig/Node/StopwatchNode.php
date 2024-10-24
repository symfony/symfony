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

use Twig\Attribute\FirstClassTwigCallableReady;
use Twig\Attribute\YieldReady;
use Twig\Compiler;
use Twig\Node\Expression\AssignNameExpression;
use Twig\Node\Expression\Variable\LocalVariable;
use Twig\Node\Node;

/**
 * Represents a stopwatch node.
 *
 * @author Wouter J <wouter@wouterj.nl>
 */
#[YieldReady]
final class StopwatchNode extends Node
{
    /**
     * @param AssignNameExpression|LocalVariable $var
     */
    public function __construct(Node $name, Node $body, $var, int $lineno = 0, ?string $tag = null)
    {
        if (!$var instanceof AssignNameExpression && !$var instanceof LocalVariable) {
            throw new \TypeError(sprintf('Expected an instance of "%s" or "%s", but got "%s".', AssignNameExpression::class, LocalVariable::class, get_debug_type($var)));
        }

        if (class_exists(FirstClassTwigCallableReady::class)) {
            parent::__construct(['body' => $body, 'name' => $name, 'var' => $var], [], $lineno);
        } else {
            parent::__construct(['body' => $body, 'name' => $name, 'var' => $var], [], $lineno, $tag);
        }
    }

    public function compile(Compiler $compiler): void
    {
        $compiler
            ->addDebugInfo($this)
            ->write('')
            ->subcompile($this->getNode('var'))
            ->raw(' = ')
            ->subcompile($this->getNode('name'))
            ->write(";\n")
            ->write("\$this->env->getExtension('Symfony\Bridge\Twig\Extension\StopwatchExtension')->getStopwatch()->start(")
            ->subcompile($this->getNode('var'))
            ->raw(", 'template');\n")
            ->subcompile($this->getNode('body'))
            ->write("\$this->env->getExtension('Symfony\Bridge\Twig\Extension\StopwatchExtension')->getStopwatch()->stop(")
            ->subcompile($this->getNode('var'))
            ->raw(");\n")
        ;
    }
}
