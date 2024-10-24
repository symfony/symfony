<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Twig\Tests\Node;

use PHPUnit\Framework\TestCase;
use Symfony\Bridge\Twig\Node\DumpNode;
use Twig\Compiler;
use Twig\Environment;
use Twig\Loader\LoaderInterface;
use Twig\Node\Expression\NameExpression;
use Twig\Node\Expression\Variable\ContextVariable;
use Twig\Node\Node;
use Twig\Node\Nodes;

class DumpNodeTest extends TestCase
{
    public function testNoVar()
    {
        $node = new DumpNode('bar', null, 7);

        $env = new Environment($this->createMock(LoaderInterface::class));
        $compiler = new Compiler($env);

        $expected = <<<'EOTXT'
if ($this->env->isDebug()) {
    $barvars = [];
    foreach ($context as $barkey => $barval) {
        if (!$barval instanceof \Twig\Template) {
            $barvars[$barkey] = $barval;
        }
    }
    // line 7
    \Symfony\Component\VarDumper\VarDumper::dump($barvars);
}

EOTXT;

        $this->assertSame($expected, $compiler->compile($node)->getSource());
    }

    public function testIndented()
    {
        $node = new DumpNode('bar', null, 7);

        $env = new Environment($this->createMock(LoaderInterface::class));
        $compiler = new Compiler($env);

        $expected = <<<'EOTXT'
    if ($this->env->isDebug()) {
        $barvars = [];
        foreach ($context as $barkey => $barval) {
            if (!$barval instanceof \Twig\Template) {
                $barvars[$barkey] = $barval;
            }
        }
        // line 7
        \Symfony\Component\VarDumper\VarDumper::dump($barvars);
    }

EOTXT;

        $this->assertSame($expected, $compiler->compile($node, 1)->getSource());
    }

    public function testOneVar()
    {
        if (class_exists(Nodes::class)) {
            $vars = new Nodes([
                class_exists(ContextVariable::class) ? new ContextVariable('foo', 7) : new NameExpression('foo', 7),
            ]);
        } else {
            $vars = new Node([
                class_exists(ContextVariable::class) ? new ContextVariable('foo', 7) : new NameExpression('foo', 7),
            ]);
        }

        $node = new DumpNode('bar', $vars, 7);

        $env = new Environment($this->createMock(LoaderInterface::class));
        $compiler = new Compiler($env);

        $expected = <<<'EOTXT'
if ($this->env->isDebug()) {
    // line 7
    \Symfony\Component\VarDumper\VarDumper::dump(%foo%);
}

EOTXT;

        $expected = preg_replace('/%(.*?)%/', '($context["$1"] ?? null)', $expected);

        $this->assertSame($expected, $compiler->compile($node)->getSource());
    }

    public function testMultiVars()
    {
        if (class_exists(Nodes::class)) {
            $vars = new Nodes([
                class_exists(ContextVariable::class) ? new ContextVariable('foo', 7) : new NameExpression('foo', 7),
                class_exists(ContextVariable::class) ? new ContextVariable('bar', 7) : new NameExpression('bar', 7),
            ]);
        } else {
            $vars = new Node([
                class_exists(ContextVariable::class) ? new ContextVariable('foo', 7) : new NameExpression('foo', 7),
                class_exists(ContextVariable::class) ? new ContextVariable('bar', 7) : new NameExpression('bar', 7),
            ]);
        }

        $node = new DumpNode('bar', $vars, 7);

        $env = new Environment($this->createMock(LoaderInterface::class));
        $compiler = new Compiler($env);

        $expected = <<<'EOTXT'
if ($this->env->isDebug()) {
    // line 7
    \Symfony\Component\VarDumper\VarDumper::dump([
        "foo" => %foo%,
        "bar" => %bar%,
    ]);
}

EOTXT;

        $expected = preg_replace('/%(.*?)%/', '($context["$1"] ?? null)', $expected);

        $this->assertSame($expected, $compiler->compile($node)->getSource());
    }
}
