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
use Symfony\Bridge\Twig\Extension\FormExtension;
use Symfony\Bridge\Twig\Node\SearchAndRenderBlockNode;
use Twig\Compiler;
use Twig\Environment;
use Twig\Node\Expression\ArrayExpression;
use Twig\Node\Expression\ConditionalExpression;
use Twig\Node\Expression\ConstantExpression;
use Twig\Node\Expression\NameExpression;
use Twig\Node\Node;

class SearchAndRenderBlockNodeTest extends TestCase
{
    public function testCompileWidget()
    {
        $arguments = new Node(array(
            new NameExpression('form', 0),
        ));

        $node = new SearchAndRenderBlockNode('form_widget', $arguments, 0);

        $this->assertEquals(
            sprintf(
                '$this->env->getRuntime(\'Symfony\Component\Form\FormRenderer\')->searchAndRenderBlock(%s, "widget")',
                $this->getVariableGetter('form')
             ),
            trim($this->getCompiler()->compile($node)->getSource())
        );
    }

    public function testCompileWidgetWithVariables()
    {
        $arguments = new Node(array(
            new NameExpression('form', 0),
            new ArrayExpression(array(
                new ConstantExpression('foo', 0),
                new ConstantExpression('bar', 0),
            ), 0),
        ));

        $node = new SearchAndRenderBlockNode('form_widget', $arguments, 0);

        $this->assertEquals(
            sprintf(
                '$this->env->getRuntime(\'Symfony\Component\Form\FormRenderer\')->searchAndRenderBlock(%s, "widget", array("foo" => "bar"))',
                $this->getVariableGetter('form')
            ),
            trim($this->getCompiler()->compile($node)->getSource())
        );
    }

    public function testCompileLabelWithLabel()
    {
        $arguments = new Node(array(
            new NameExpression('form', 0),
            new ConstantExpression('my label', 0),
        ));

        $node = new SearchAndRenderBlockNode('form_label', $arguments, 0);

        $this->assertEquals(
            sprintf(
                '$this->env->getRuntime(\'Symfony\Component\Form\FormRenderer\')->searchAndRenderBlock(%s, "label", array("label" => "my label"))',
                $this->getVariableGetter('form')
            ),
            trim($this->getCompiler()->compile($node)->getSource())
        );
    }

    public function testCompileLabelWithNullLabel()
    {
        $arguments = new Node(array(
            new NameExpression('form', 0),
            new ConstantExpression(null, 0),
        ));

        $node = new SearchAndRenderBlockNode('form_label', $arguments, 0);

        // "label" => null must not be included in the output!
        // Otherwise the default label is overwritten with null.
        $this->assertEquals(
            sprintf(
                '$this->env->getRuntime(\'Symfony\Component\Form\FormRenderer\')->searchAndRenderBlock(%s, "label", array())',
                $this->getVariableGetter('form')
            ),
            trim($this->getCompiler()->compile($node)->getSource())
        );
    }

    public function testCompileLabelWithEmptyStringLabel()
    {
        $arguments = new Node(array(
            new NameExpression('form', 0),
            new ConstantExpression('', 0),
        ));

        $node = new SearchAndRenderBlockNode('form_label', $arguments, 0);

        // "label" => null must not be included in the output!
        // Otherwise the default label is overwritten with null.
        $this->assertEquals(
            sprintf(
                '$this->env->getRuntime(\'Symfony\Component\Form\FormRenderer\')->searchAndRenderBlock(%s, "label", array())',
                $this->getVariableGetter('form')
            ),
            trim($this->getCompiler()->compile($node)->getSource())
        );
    }

    public function testCompileLabelWithDefaultLabel()
    {
        $arguments = new Node(array(
            new NameExpression('form', 0),
        ));

        $node = new SearchAndRenderBlockNode('form_label', $arguments, 0);

        $this->assertEquals(
            sprintf(
                '$this->env->getRuntime(\'Symfony\Component\Form\FormRenderer\')->searchAndRenderBlock(%s, "label")',
                $this->getVariableGetter('form')
            ),
            trim($this->getCompiler()->compile($node)->getSource())
        );
    }

    public function testCompileLabelWithAttributes()
    {
        $arguments = new Node(array(
            new NameExpression('form', 0),
            new ConstantExpression(null, 0),
            new ArrayExpression(array(
                new ConstantExpression('foo', 0),
                new ConstantExpression('bar', 0),
            ), 0),
        ));

        $node = new SearchAndRenderBlockNode('form_label', $arguments, 0);

        // "label" => null must not be included in the output!
        // Otherwise the default label is overwritten with null.
        // https://github.com/symfony/symfony/issues/5029
        $this->assertEquals(
            sprintf(
                '$this->env->getRuntime(\'Symfony\Component\Form\FormRenderer\')->searchAndRenderBlock(%s, "label", array("foo" => "bar"))',
                $this->getVariableGetter('form')
            ),
            trim($this->getCompiler()->compile($node)->getSource())
        );
    }

    public function testCompileLabelWithLabelAndAttributes()
    {
        $arguments = new Node(array(
            new NameExpression('form', 0),
            new ConstantExpression('value in argument', 0),
            new ArrayExpression(array(
                new ConstantExpression('foo', 0),
                new ConstantExpression('bar', 0),
                new ConstantExpression('label', 0),
                new ConstantExpression('value in attributes', 0),
            ), 0),
        ));

        $node = new SearchAndRenderBlockNode('form_label', $arguments, 0);

        $this->assertEquals(
            sprintf(
                '$this->env->getRuntime(\'Symfony\Component\Form\FormRenderer\')->searchAndRenderBlock(%s, "label", array("foo" => "bar", "label" => "value in argument"))',
                $this->getVariableGetter('form')
            ),
            trim($this->getCompiler()->compile($node)->getSource())
        );
    }

    public function testCompileLabelWithLabelThatEvaluatesToNull()
    {
        $arguments = new Node(array(
            new NameExpression('form', 0),
            new ConditionalExpression(
                // if
                new ConstantExpression(true, 0),
                // then
                new ConstantExpression(null, 0),
                // else
                new ConstantExpression(null, 0),
                0
            ),
        ));

        $node = new SearchAndRenderBlockNode('form_label', $arguments, 0);

        // "label" => null must not be included in the output!
        // Otherwise the default label is overwritten with null.
        // https://github.com/symfony/symfony/issues/5029
        $this->assertEquals(
            sprintf(
                '$this->env->getRuntime(\'Symfony\Component\Form\FormRenderer\')->searchAndRenderBlock(%s, "label", ((twig_test_empty(((true) ? (null) : (null)))) ? (array()) : (array("label" => ((true) ? (null) : (null))))))',
                $this->getVariableGetter('form')
            ),
            trim($this->getCompiler()->compile($node)->getSource())
        );
    }

    public function testCompileLabelWithLabelThatEvaluatesToNullAndAttributes()
    {
        $arguments = new Node(array(
            new NameExpression('form', 0),
            new ConditionalExpression(
                // if
                new ConstantExpression(true, 0),
                // then
                new ConstantExpression(null, 0),
                // else
                new ConstantExpression(null, 0),
                0
            ),
            new ArrayExpression(array(
                new ConstantExpression('foo', 0),
                new ConstantExpression('bar', 0),
                new ConstantExpression('label', 0),
                new ConstantExpression('value in attributes', 0),
            ), 0),
        ));

        $node = new SearchAndRenderBlockNode('form_label', $arguments, 0);

        // "label" => null must not be included in the output!
        // Otherwise the default label is overwritten with null.
        // https://github.com/symfony/symfony/issues/5029
        $this->assertEquals(
            sprintf(
                '$this->env->getRuntime(\'Symfony\Component\Form\FormRenderer\')->searchAndRenderBlock(%s, "label", twig_array_merge(((twig_test_empty(((true) ? (null) : (null)))) ? (array()) : (array("label" => ((true) ? (null) : (null))))), "foo", "bar", "label", "value in attributes"))',
                $this->getVariableGetter('form')
            ),
            trim($this->getCompiler()->compile($node)->getSource())
        );
    }

    protected function getVariableGetter($name)
    {
        if (\PHP_VERSION_ID >= 70000) {
            return sprintf('($context["%s"] ?? null)', $name, $name);
        }

        return sprintf('(isset($context["%s"]) ? $context["%s"] : null)', $name, $name);
    }

    private function getCompiler()
    {
        $loader = $this->getMockBuilder('Twig\Loader\LoaderInterface')->getMock();
        $environment = new Environment($loader, array('strict_variables' => false));
        $environment->addExtension(new FormExtension());

        return new Compiler($environment);
    }
}
