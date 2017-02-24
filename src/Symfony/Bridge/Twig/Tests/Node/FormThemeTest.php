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
use Symfony\Bridge\Twig\Node\FormThemeNode;

class FormThemeTest extends TestCase
{
    public function testConstructor()
    {
        $form = new \Twig_Node_Expression_Name('form', 0);
        $resources = new \Twig_Node(array(
            new \Twig_Node_Expression_Constant('tpl1', 0),
            new \Twig_Node_Expression_Constant('tpl2', 0),
        ));

        $node = new FormThemeNode($form, $resources, 0);

        $this->assertEquals($form, $node->getNode('form'));
        $this->assertEquals($resources, $node->getNode('resources'));
    }

    public function testCompile()
    {
        $form = new \Twig_Node_Expression_Name('form', 0);
        $resources = new \Twig_Node_Expression_Array(array(
            new \Twig_Node_Expression_Constant(0, 0),
            new \Twig_Node_Expression_Constant('tpl1', 0),
            new \Twig_Node_Expression_Constant(1, 0),
            new \Twig_Node_Expression_Constant('tpl2', 0),
        ), 0);

        $node = new FormThemeNode($form, $resources, 0);

        $compiler = new \Twig_Compiler(new \Twig_Environment($this->getMockBuilder('Twig_LoaderInterface')->getMock()));

        $this->assertEquals(
            sprintf(
                '$this->env->getRuntime(\'Symfony\Bridge\Twig\Form\TwigRenderer\')->setTheme(%s, array(0 => "tpl1", 1 => "tpl2"));',
                $this->getVariableGetter('form')
             ),
            trim($compiler->compile($node)->getSource())
        );

        $resources = new \Twig_Node_Expression_Constant('tpl1', 0);

        $node = new FormThemeNode($form, $resources, 0);

        $this->assertEquals(
            sprintf(
                '$this->env->getRuntime(\'Symfony\Bridge\Twig\Form\TwigRenderer\')->setTheme(%s, "tpl1");',
                $this->getVariableGetter('form')
             ),
            trim($compiler->compile($node)->getSource())
        );
    }

    protected function getVariableGetter($name)
    {
        if (PHP_VERSION_ID >= 70000) {
            return sprintf('($context["%s"] ?? null)', $name, $name);
        }

        return sprintf('(isset($context["%s"]) ? $context["%s"] : null)', $name, $name);
    }
}
