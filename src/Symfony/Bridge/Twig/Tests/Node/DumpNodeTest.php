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

use Symfony\Bridge\Twig\Node\DumpNode;

class DumpNodeTest extends \PHPUnit_Framework_TestCase
{
    public function testNoVar()
    {
        $node = new DumpNode('bar', null, 7);

        $env = new \Twig_Environment();
        $compiler = new \Twig_Compiler($env);

        $expected = <<<'EOTXT'
if ($this->env->isDebug()) {
    $barvars = array();
    foreach ($context as $barkey => $barval) {
        if (!$barval instanceof \Twig_Template) {
            $barvars[$barkey] = $barval;
        }
    }
    // line 7
    \Symfony\Component\VarDumper\VarDumper::dump($barvars);
}

EOTXT;

        $this->assertSame($expected, $compiler->compile($node)->getSource());
    }

    public function testOneVar()
    {
        $vars = new \Twig_Node(array(
            new \Twig_Node_Expression_Name('foo', 7),
        ));
        $node = new DumpNode('bar', $vars, 7);

        $env = new \Twig_Environment();
        $compiler = new \Twig_Compiler($env);

        $expected = <<<'EOTXT'
if ($this->env->isDebug()) {
    // line 7
    \Symfony\Component\VarDumper\VarDumper::dump(%foo%);
}

EOTXT;
        $expected = preg_replace('/%(.*?)%/', '(isset($context["$1"]) ? $context["$1"] : null)', $expected);

        $this->assertSame($expected, $compiler->compile($node)->getSource());
    }

    public function testMultiVars()
    {
        $vars = new \Twig_Node(array(
            new \Twig_Node_Expression_Name('foo', 7),
            new \Twig_Node_Expression_Name('bar', 7),
        ));
        $node = new DumpNode('bar', $vars, 7);

        $env = new \Twig_Environment();
        $compiler = new \Twig_Compiler($env);

        $expected = <<<'EOTXT'
if ($this->env->isDebug()) {
    // line 7
    \Symfony\Component\VarDumper\VarDumper::dump(array(
        "foo" => %foo%,
        "bar" => %bar%,
    ));
}

EOTXT;
        $expected = preg_replace('/%(.*?)%/', '(isset($context["$1"]) ? $context["$1"] : null)', $expected);

        $this->assertSame($expected, $compiler->compile($node)->getSource());
    }
}
