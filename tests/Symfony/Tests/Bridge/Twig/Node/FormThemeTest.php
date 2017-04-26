<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Tests\Bridge\Twig\Node;

use Symfony\Tests\Bridge\Twig\TestCase;
use Symfony\Bridge\Twig\Node\FormThemeNode;

class FormThemeTest extends TestCase
{
    public function testConstructor()
    {
        $form = new \Twig_Node_Expression_Name('form', 0);
        $resources = new \Twig_Node(array(
            new \Twig_Node_Expression_Constant('tpl1', 0),
            new \Twig_Node_Expression_Constant('tpl2', 0)
        ));

        $node = new FormThemeNode($form, $resources, 0);

        $this->assertEquals($form, $node->getNode('form'));
        $this->assertEquals($resources, $node->getNode('resources'));
    }

    public function testCompile()
    {
        $form = new \Twig_Node_Expression_Name('form', 0);
        $resources = new \Twig_Node(array(
            new \Twig_Node_Expression_Constant('tpl1', 0),
            new \Twig_Node_Expression_Constant('tpl2', 0)
        ));

        $node = new FormThemeNode($form, $resources, 0);

        $compiler = new \Twig_Compiler(new \Twig_Environment());

        $this->assertEquals(
            'echo $this->env->getExtension(\'form\')->setTheme($this->getContext($context, "form"), array("tpl1", "tpl2", ));',
            trim($compiler->compile($node)->getSource())
        );
    }
}