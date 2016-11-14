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

use Symfony\Bridge\Twig\Node\SearchAndRenderBlockNode;

class SearchAndRenderBlockNodeTest extends \PHPUnit_Framework_TestCase
{
    public function testCompileWidget()
    {
        $arguments = new \Twig_Node(array(
            new \Twig_Node_Expression_Name('form', 0),
        ));

        $node = new SearchAndRenderBlockNode('form_widget', $arguments, 0);

        $compiler = new \Twig_Compiler(new \Twig_Environment($this->getMock('Twig_LoaderInterface')));

        $this->assertEquals(
            sprintf(
                '$this->env->getExtension(\'Symfony\Bridge\Twig\Extension\FormExtension\')->renderer->searchAndRenderBlock(%s, \'widget\')',
                $this->getVariableGetter('form')
             ),
            trim($compiler->compile($node)->getSource())
        );
    }

    public function testCompileWidgetWithVariables()
    {
        $arguments = new \Twig_Node(array(
            new \Twig_Node_Expression_Name('form', 0),
            new \Twig_Node_Expression_Array(array(
                new \Twig_Node_Expression_Constant('foo', 0),
                new \Twig_Node_Expression_Constant('bar', 0),
            ), 0),
        ));

        $node = new SearchAndRenderBlockNode('form_widget', $arguments, 0);

        $compiler = new \Twig_Compiler(new \Twig_Environment($this->getMock('Twig_LoaderInterface')));

        $this->assertEquals(
            sprintf(
                '$this->env->getExtension(\'Symfony\Bridge\Twig\Extension\FormExtension\')->renderer->searchAndRenderBlock(%s, \'widget\', array("foo" => "bar"))',
                $this->getVariableGetter('form')
            ),
            trim($compiler->compile($node)->getSource())
        );
    }

    public function testCompileLabelWithLabel()
    {
        $arguments = new \Twig_Node(array(
            new \Twig_Node_Expression_Name('form', 0),
            new \Twig_Node_Expression_Constant('my label', 0),
        ));

        $node = new SearchAndRenderBlockNode('form_label', $arguments, 0);

        $compiler = new \Twig_Compiler(new \Twig_Environment($this->getMock('Twig_LoaderInterface')));

        $this->assertEquals(
            sprintf(
                '$this->env->getExtension(\'Symfony\Bridge\Twig\Extension\FormExtension\')->renderer->searchAndRenderBlock(%s, \'label\', array("label" => "my label"))',
                $this->getVariableGetter('form')
            ),
            trim($compiler->compile($node)->getSource())
        );
    }

    public function testCompileLabelWithNullLabel()
    {
        $arguments = new \Twig_Node(array(
            new \Twig_Node_Expression_Name('form', 0),
            new \Twig_Node_Expression_Constant(null, 0),
        ));

        $node = new SearchAndRenderBlockNode('form_label', $arguments, 0);

        $compiler = new \Twig_Compiler(new \Twig_Environment($this->getMock('Twig_LoaderInterface')));

        // "label" => null must not be included in the output!
        // Otherwise the default label is overwritten with null.
        $this->assertEquals(
            sprintf(
                '$this->env->getExtension(\'Symfony\Bridge\Twig\Extension\FormExtension\')->renderer->searchAndRenderBlock(%s, \'label\')',
                $this->getVariableGetter('form')
            ),
            trim($compiler->compile($node)->getSource())
        );
    }

    public function testCompileLabelWithEmptyStringLabel()
    {
        $arguments = new \Twig_Node(array(
            new \Twig_Node_Expression_Name('form', 0),
            new \Twig_Node_Expression_Constant('', 0),
        ));

        $node = new SearchAndRenderBlockNode('form_label', $arguments, 0);

        $compiler = new \Twig_Compiler(new \Twig_Environment($this->getMock('Twig_LoaderInterface')));

        // "label" => null must not be included in the output!
        // Otherwise the default label is overwritten with null.
        $this->assertEquals(
            sprintf(
                '$this->env->getExtension(\'Symfony\Bridge\Twig\Extension\FormExtension\')->renderer->searchAndRenderBlock(%s, \'label\')',
                $this->getVariableGetter('form')
            ),
            trim($compiler->compile($node)->getSource())
        );
    }

    public function testCompileLabelWithDefaultLabel()
    {
        $arguments = new \Twig_Node(array(
            new \Twig_Node_Expression_Name('form', 0),
        ));

        $node = new SearchAndRenderBlockNode('form_label', $arguments, 0);

        $compiler = new \Twig_Compiler(new \Twig_Environment($this->getMock('Twig_LoaderInterface')));

        $this->assertEquals(
            sprintf(
                '$this->env->getExtension(\'Symfony\Bridge\Twig\Extension\FormExtension\')->renderer->searchAndRenderBlock(%s, \'label\')',
                $this->getVariableGetter('form')
            ),
            trim($compiler->compile($node)->getSource())
        );
    }

    public function testCompileLabelWithAttributes()
    {
        $arguments = new \Twig_Node(array(
            new \Twig_Node_Expression_Name('form', 0),
            new \Twig_Node_Expression_Constant(null, 0),
            new \Twig_Node_Expression_Array(array(
                new \Twig_Node_Expression_Constant('foo', 0),
                new \Twig_Node_Expression_Constant('bar', 0),
            ), 0),
        ));

        $node = new SearchAndRenderBlockNode('form_label', $arguments, 0);

        $compiler = new \Twig_Compiler(new \Twig_Environment($this->getMock('Twig_LoaderInterface')));

        // "label" => null must not be included in the output!
        // Otherwise the default label is overwritten with null.
        // https://github.com/symfony/symfony/issues/5029
        $this->assertEquals(
            sprintf(
                '$this->env->getExtension(\'Symfony\Bridge\Twig\Extension\FormExtension\')->renderer->searchAndRenderBlock(%s, \'label\', array("foo" => "bar"))',
                $this->getVariableGetter('form')
            ),
            trim($compiler->compile($node)->getSource())
        );
    }

    public function testCompileLabelWithLabelAndAttributes()
    {
        $arguments = new \Twig_Node(array(
            new \Twig_Node_Expression_Name('form', 0),
            new \Twig_Node_Expression_Constant('value in argument', 0),
            new \Twig_Node_Expression_Array(array(
                new \Twig_Node_Expression_Constant('foo', 0),
                new \Twig_Node_Expression_Constant('bar', 0),
                new \Twig_Node_Expression_Constant('label', 0),
                new \Twig_Node_Expression_Constant('value in attributes', 0),
            ), 0),
        ));

        $node = new SearchAndRenderBlockNode('form_label', $arguments, 0);

        $compiler = new \Twig_Compiler(new \Twig_Environment($this->getMock('Twig_LoaderInterface')));

        $this->assertEquals(
            sprintf(
                '$this->env->getExtension(\'Symfony\Bridge\Twig\Extension\FormExtension\')->renderer->searchAndRenderBlock(%s, \'label\', array("foo" => "bar", "label" => "value in argument"))',
                $this->getVariableGetter('form')
            ),
            trim($compiler->compile($node)->getSource())
        );
    }

    public function testCompileLabelWithLabelThatEvaluatesToNull()
    {
        $arguments = new \Twig_Node(array(
            new \Twig_Node_Expression_Name('form', 0),
            new \Twig_Node_Expression_Conditional(
                // if
                new \Twig_Node_Expression_Constant(true, 0),
                // then
                new \Twig_Node_Expression_Constant(null, 0),
                // else
                new \Twig_Node_Expression_Constant(null, 0),
                0
            ),
        ));

        $node = new SearchAndRenderBlockNode('form_label', $arguments, 0);

        $compiler = new \Twig_Compiler(new \Twig_Environment($this->getMock('Twig_LoaderInterface')));

        // "label" => null must not be included in the output!
        // Otherwise the default label is overwritten with null.
        // https://github.com/symfony/symfony/issues/5029
        $this->assertEquals(
            sprintf(
                '$this->env->getExtension(\'Symfony\Bridge\Twig\Extension\FormExtension\')->renderer->searchAndRenderBlock(%s, \'label\', (twig_test_empty($_label_ = ((true) ? (null) : (null))) ? array() : array("label" => $_label_)))',
                $this->getVariableGetter('form')
            ),
            trim($compiler->compile($node)->getSource())
        );
    }

    public function testCompileLabelWithLabelThatEvaluatesToNullAndAttributes()
    {
        $arguments = new \Twig_Node(array(
            new \Twig_Node_Expression_Name('form', 0),
            new \Twig_Node_Expression_Conditional(
                // if
                new \Twig_Node_Expression_Constant(true, 0),
                // then
                new \Twig_Node_Expression_Constant(null, 0),
                // else
                new \Twig_Node_Expression_Constant(null, 0),
                0
            ),
            new \Twig_Node_Expression_Array(array(
                new \Twig_Node_Expression_Constant('foo', 0),
                new \Twig_Node_Expression_Constant('bar', 0),
                new \Twig_Node_Expression_Constant('label', 0),
                new \Twig_Node_Expression_Constant('value in attributes', 0),
            ), 0),
        ));

        $node = new SearchAndRenderBlockNode('form_label', $arguments, 0);

        $compiler = new \Twig_Compiler(new \Twig_Environment($this->getMock('Twig_LoaderInterface')));

        // "label" => null must not be included in the output!
        // Otherwise the default label is overwritten with null.
        // https://github.com/symfony/symfony/issues/5029
        $this->assertEquals(
            sprintf(
                '$this->env->getExtension(\'Symfony\Bridge\Twig\Extension\FormExtension\')->renderer->searchAndRenderBlock(%s, \'label\', array("foo" => "bar", "label" => "value in attributes") + (twig_test_empty($_label_ = ((true) ? (null) : (null))) ? array() : array("label" => $_label_)))',
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
