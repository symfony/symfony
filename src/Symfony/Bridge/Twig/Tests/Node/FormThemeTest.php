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

use Symfony\Bridge\Twig\Tests\TestCase;
use Symfony\Bridge\Twig\Node\FormThemeNode;

class FormThemeTest extends TestCase
{
    protected function setUp()
    {
        parent::setUp();

        if (version_compare(\Twig_Environment::VERSION, '1.5.0', '<')) {
            $this->markTestSkipped('Requires Twig version to be at least 1.5.0.');
        }
    }

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
        $resources = new \Twig_Node_Expression_Array(array(
            new \Twig_Node_Expression_Constant(0, 0),
            new \Twig_Node_Expression_Constant('tpl1', 0),
            new \Twig_Node_Expression_Constant(1, 0),
            new \Twig_Node_Expression_Constant('tpl2', 0)
        ), 0);

        $node = new FormThemeNode($form, $resources, 0);

        $compiler = new \Twig_Compiler(new \Twig_Environment());

        $this->assertEquals(
            sprintf(
                '$formVar = %1$s;
$resources = array(0 => "tpl1", 1 => "tpl2");

if ($formVar instanceof \\Symfony\\Component\\Form\\FormView) {
    $this->env->getExtension(\'form\')->renderer->setTheme($formVar, $resources);
} else {
    $formVar = twig_ensure_traversable($formVar);

    foreach ($formVar as $nestedVar) {
        if ($nestedVar instanceof \\Symfony\\Component\\Form\\FormView) {
            $this->env->getExtension(\'form\')->renderer->setTheme($nestedVar, $resources);
        }
    }
}',
                $this->getVariableGetter('form')
             ),
            trim($compiler->compile($node)->getSource())
        );

        $resources = new \Twig_Node_Expression_Constant('tpl1', 0);

        $node = new FormThemeNode($form, $resources, 0);

        $this->assertEquals(
            sprintf(
                '$formVar = %1$s;
$resources = "tpl1";

if ($formVar instanceof \\Symfony\\Component\\Form\\FormView) {
    $this->env->getExtension(\'form\')->renderer->setTheme($formVar, $resources);
} else {
    $formVar = twig_ensure_traversable($formVar);

    foreach ($formVar as $nestedVar) {
        if ($nestedVar instanceof \\Symfony\\Component\\Form\\FormView) {
            $this->env->getExtension(\'form\')->renderer->setTheme($nestedVar, $resources);
        }
    }
}',
                $this->getVariableGetter('form')
             ),
            trim($compiler->compile($node)->getSource())
        );
    }

    protected function getVariableGetter($name)
    {
        if (version_compare(phpversion(), '5.4.0RC1', '>=')) {
            return sprintf('(isset($context["%s"]) ? $context["%s"] : null)', $name, $name);
        }

        return sprintf('$this->getContext($context, "%s")', $name);
    }
}
