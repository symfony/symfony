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
use Symfony\Bridge\Twig\Node\SearchAndRenderBlockNode;

class SearchAndRenderBlockNodeTest extends TestCase
{
    protected function setUp()
    {
        parent::setUp();

        if (version_compare(\Twig_Environment::VERSION, '1.5.0', '<')) {
            $this->markTestSkipped('Requires Twig version to be at least 1.5.0.');
        }
    }

    public function testCompileWidget()
    {
        $arguments = new \Twig_Node(array(
            new \Twig_Node_Expression_Name('form', 0),
        ));

        $node = new SearchAndRenderBlockNode('form_widget', $arguments, 0);

        $compiler = new \Twig_Compiler(new \Twig_Environment());

        $this->assertEquals(
            sprintf(
                '$this->env->getExtension(\'form\')->renderer->searchAndRenderBlock(%s, \'widget\')',
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

        $compiler = new \Twig_Compiler(new \Twig_Environment());

        $this->assertEquals(
            sprintf(
                '$this->env->getExtension(\'form\')->renderer->searchAndRenderBlock(%s, \'widget\', array("foo" => "bar"))',
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

        $compiler = new \Twig_Compiler(new \Twig_Environment());

        $this->assertEquals(
            sprintf(
                '$this->env->getExtension(\'form\')->renderer->searchAndRenderBlock(%s, \'label\', array("label" => "my label"))',
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

        $compiler = new \Twig_Compiler(new \Twig_Environment());

        $this->assertEquals(
            sprintf(
                '$this->env->getExtension(\'form\')->renderer->searchAndRenderBlock(%s, \'label\', array("label" => null))',
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

        $compiler = new \Twig_Compiler(new \Twig_Environment());

        $this->assertEquals(
            sprintf(
                '$this->env->getExtension(\'form\')->renderer->searchAndRenderBlock(%s, \'label\')',
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

        $compiler = new \Twig_Compiler(new \Twig_Environment());

        $this->assertEquals(
            sprintf(
                '$this->env->getExtension(\'form\')->renderer->searchAndRenderBlock(%s, \'label\', array("foo" => "bar", "label" => null))',
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

        $compiler = new \Twig_Compiler(new \Twig_Environment());

        $this->assertEquals(
            sprintf(
                '$this->env->getExtension(\'form\')->renderer->searchAndRenderBlock(%s, \'label\', array("foo" => "bar", "label" => _twig_default_filter("value in argument", "value in attributes")))',
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
