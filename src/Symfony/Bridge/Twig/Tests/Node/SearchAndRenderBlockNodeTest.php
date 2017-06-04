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
    /**
     * @dataProvider getCompileWidgetCases
     */
    public function testCompileWidget(Node $arguments)
    {
        $node = new SearchAndRenderBlockNode('form_widget', $arguments, 0);

        $compiler = new Compiler(new Environment($this->getMockBuilder('Twig\Loader\LoaderInterface')->getMock()));

        $this->assertEquals(
            sprintf(
                '$this->env->getRuntime(\'Symfony\Component\Form\FormRenderer\')->searchAndRenderBlock(%s, \'widget\')',
                $this->getVariableGetter('form')
             ),
            trim($compiler->compile($node)->getSource())
        );
    }

    public function getCompileWidgetCases()
    {
        yield array(new Node(array(
            new NameExpression('form', 0),
        )));

        yield array(new Node(array(
            'view' => new NameExpression('form', 0),
        )));
    }

    /**
     * @dataProvider getCompileWidgetWithVariablesCases
     */
    public function testCompileWidgetWithVariables(Node $arguments)
    {
        $node = new SearchAndRenderBlockNode('form_widget', $arguments, 0);

        $compiler = new Compiler(new Environment($this->getMockBuilder('Twig\Loader\LoaderInterface')->getMock()));

        $this->assertEquals(
            sprintf(
                '$this->env->getRuntime(\'Symfony\Component\Form\FormRenderer\')->searchAndRenderBlock(%s, \'widget\', array("foo" => "bar"))',
                $this->getVariableGetter('form')
            ),
            trim($compiler->compile($node)->getSource())
        );
    }

    public function getCompileWidgetWithVariablesCases()
    {
        yield array(new Node(array(
            new NameExpression('form', 0),
            new ArrayExpression(array(
                new ConstantExpression('foo', 0),
                new ConstantExpression('bar', 0),
            ), 0),
        )));

        yield array(new Node(array(
            'view' => new NameExpression('form', 0),
            'variables' => new ArrayExpression(array(
                new ConstantExpression('foo', 0),
                new ConstantExpression('bar', 0),
            ), 0),
        )));

        yield array(new Node(array(
            'variables' => new ArrayExpression(array(
                new ConstantExpression('foo', 0),
                new ConstantExpression('bar', 0),
            ), 0),
            'view' => new NameExpression('form', 0),
        )));
    }

    public function testCompileWidgetWithUnknownParameter()
    {
        $node = new SearchAndRenderBlockNode('form_widget', new Node(array(
            'foo' => new NameExpression('form', 0),
        )), 0);

        $compiler = new Compiler(new Environment($this->getMockBuilder('Twig_LoaderInterface')->getMock()));

        $this->{method_exists($this, $_ = 'expectException') ? $_ : 'setExpectedException'}(
            'LogicException',
            'Unknown argument "foo" for function "form_widget".'
        );

        $compiler->compile($node);
    }

    public function testCompileWidgetWithDuplicatedParameter()
    {
        $node = new SearchAndRenderBlockNode('form_widget', new Node(array(
            new NameExpression('form', 0),
            'view' => new NameExpression('form', 0),
        )), 0);

        $compiler = new Compiler(new Environment($this->getMockBuilder('Twig_LoaderInterface')->getMock()));

        $this->{method_exists($this, $_ = 'expectException') ? $_ : 'setExpectedException'}(
            'Twig_Error_Syntax',
            'Argument "view" is defined twice for function "form_widget".'
        );

        $compiler->compile($node);
    }

    public function testCompileWidgetWithPositionalParameterAfterNamedParameter()
    {
        $node = new SearchAndRenderBlockNode('form_widget', new Node(array(
            'view' => new NameExpression('form', 0),
            new ArrayExpression(array(
                new ConstantExpression('foo', 0),
                new ConstantExpression('bar', 0),
            ), 0),
        )), 0);

        $compiler = new Compiler(new Environment($this->getMockBuilder('Twig_LoaderInterface')->getMock()));

        $this->{method_exists($this, $_ = 'expectException') ? $_ : 'setExpectedException'}(
            'Twig_Error_Syntax',
            'Positional arguments cannot be used after named arguments for function "form_widget".'
        );

        $compiler->compile($node);
    }

    /**
     * @dataProvider getCompileLabelWithLabelCases
     */
    public function testCompileLabelWithLabel(Node $arguments)
    {
        $node = new SearchAndRenderBlockNode('form_label', $arguments, 0);

        $compiler = new Compiler(new Environment($this->getMockBuilder('Twig\Loader\LoaderInterface')->getMock()));

        $this->assertEquals(
            sprintf(
                '$this->env->getRuntime(\'Symfony\Component\Form\FormRenderer\')->searchAndRenderBlock(%s, \'label\', array("label" => "my label"))',
                $this->getVariableGetter('form')
            ),
            trim($compiler->compile($node)->getSource())
        );
    }

    public function getCompileLabelWithLabelCases()
    {
        yield array(new Node(array(
            new NameExpression('form', 0),
            new ConstantExpression('my label', 0),
        )));

        yield array(new Node(array(
            'view' => new NameExpression('form', 0),
            'label' => new ConstantExpression('my label', 0),
        )));

        yield array(new Node(array(
            'label' => new ConstantExpression('my label', 0),
            'view' => new NameExpression('form', 0),
        )));
    }

    /**
     * @dataProvider getCompileLabelWithNullLabelCases
     */
    public function testCompileLabelWithNullLabel(Node $arguments)
    {
        $arguments = new Node(array(
            new NameExpression('form', 0),
            new ConstantExpression(null, 0),
        ));

        $node = new SearchAndRenderBlockNode('form_label', $arguments, 0);

        $compiler = new Compiler(new Environment($this->getMockBuilder('Twig\Loader\LoaderInterface')->getMock()));

        // "label" => null must not be included in the output!
        // Otherwise the default label is overwritten with null.
        $this->assertEquals(
            sprintf(
                '$this->env->getRuntime(\'Symfony\Component\Form\FormRenderer\')->searchAndRenderBlock(%s, \'label\')',
                $this->getVariableGetter('form')
            ),
            trim($compiler->compile($node)->getSource())
        );
    }

    public function getCompileLabelWithNullLabelCases()
    {
        yield array(new Node(array(
            new NameExpression('form', 0),
            new ConstantExpression(null, 0),
        )));

        yield array(new Node(array(
            'view' => new NameExpression('form', 0),
            'label' => new ConstantExpression(null, 0),
        )));

        yield array(new Node(array(
            'label' => new ConstantExpression(null, 0),
            'view' => new NameExpression('form', 0),
        )));
    }

    /**
     * @dataProvider getCompileLabelWithEmptyStringLabelCases
     */
    public function testCompileLabelWithEmptyStringLabel(Node $arguments)
    {
        $node = new SearchAndRenderBlockNode('form_label', $arguments, 0);

        $compiler = new Compiler(new Environment($this->getMockBuilder('Twig\Loader\LoaderInterface')->getMock()));

        // "label" => null must not be included in the output!
        // Otherwise the default label is overwritten with null.
        $this->assertEquals(
            sprintf(
                '$this->env->getRuntime(\'Symfony\Component\Form\FormRenderer\')->searchAndRenderBlock(%s, \'label\')',
                $this->getVariableGetter('form')
            ),
            trim($compiler->compile($node)->getSource())
        );
    }

    public function getCompileLabelWithEmptyStringLabelCases()
    {
        yield array(new Node(array(
            new NameExpression('form', 0),
            new ConstantExpression('', 0),
        )));

        yield array(new Node(array(
            'view' => new NameExpression('form', 0),
            'label' => new ConstantExpression('', 0),
        )));

        yield array(new Node(array(
            'label' => new ConstantExpression('', 0),
            'view' => new NameExpression('form', 0),
        )));
    }

    /**
     * @dataProvider getCompileLabelWithDefaultLabelCases
     */
    public function testCompileLabelWithDefaultLabel(Node $arguments)
    {
        $node = new SearchAndRenderBlockNode('form_label', $arguments, 0);

        $compiler = new Compiler(new Environment($this->getMockBuilder('Twig\Loader\LoaderInterface')->getMock()));

        $this->assertEquals(
            sprintf(
                '$this->env->getRuntime(\'Symfony\Component\Form\FormRenderer\')->searchAndRenderBlock(%s, \'label\')',
                $this->getVariableGetter('form')
            ),
            trim($compiler->compile($node)->getSource())
        );
    }

    public function getCompileLabelWithDefaultLabelCases()
    {
        yield array(new Node(array(
            new NameExpression('form', 0),
        )));

        yield array(new Node(array(
            'view' => new NameExpression('form', 0),
        )));
    }

    /**
     * @dataProvider getCompileLabelWithAttributesCases
     */
    public function testCompileLabelWithAttributes(Node $arguments)
    {
        $node = new SearchAndRenderBlockNode('form_label', $arguments, 0);

        $compiler = new Compiler(new Environment($this->getMockBuilder('Twig\Loader\LoaderInterface')->getMock()));

        // "label" => null must not be included in the output!
        // Otherwise the default label is overwritten with null.
        // https://github.com/symfony/symfony/issues/5029
        $this->assertEquals(
            sprintf(
                '$this->env->getRuntime(\'Symfony\Component\Form\FormRenderer\')->searchAndRenderBlock(%s, \'label\', array("foo" => "bar"))',
                $this->getVariableGetter('form')
            ),
            trim($compiler->compile($node)->getSource())
        );
    }

    public function getCompileLabelWithAttributesCases()
    {
        yield array(new Node(array(
            new NameExpression('form', 0),
            new ConstantExpression(null, 0),
            new ArrayExpression(array(
                new ConstantExpression('foo', 0),
                new ConstantExpression('bar', 0),
            ), 0),
        )));

        yield array(new Node(array(
            'view' => new NameExpression('form', 0),
            'label' => new ConstantExpression(null, 0),
            'variables' => new ArrayExpression(array(
                new ConstantExpression('foo', 0),
                new ConstantExpression('bar', 0),
            ), 0),
        )));

        yield array(new Node(array(
            'view' => new NameExpression('form', 0),
            'variables' => new ArrayExpression(array(
                new ConstantExpression('foo', 0),
                new ConstantExpression('bar', 0),
            ), 0),
            'label' => new ConstantExpression(null, 0),
        )));

        yield array(new Node(array(
            'label' => new ConstantExpression(null, 0),
            'view' => new NameExpression('form', 0),
            'variables' => new ArrayExpression(array(
                new ConstantExpression('foo', 0),
                new ConstantExpression('bar', 0),
            ), 0),
        )));

        yield array(new Node(array(
            'label' => new ConstantExpression(null, 0),
            'variables' => new ArrayExpression(array(
                new ConstantExpression('foo', 0),
                new ConstantExpression('bar', 0),
            ), 0),
            'view' => new NameExpression('form', 0),
        )));

        yield array(new Node(array(
            'variables' => new ArrayExpression(array(
                new ConstantExpression('foo', 0),
                new ConstantExpression('bar', 0),
            ), 0),
            'view' => new NameExpression('form', 0),
            'label' => new ConstantExpression(null, 0),
        )));

        yield array(new Node(array(
            'variables' => new ArrayExpression(array(
                new ConstantExpression('foo', 0),
                new ConstantExpression('bar', 0),
            ), 0),
            'label' => new ConstantExpression(null, 0),
            'view' => new NameExpression('form', 0),
        )));

        yield array(new Node(array(
            'view' => new NameExpression('form', 0),
            'variables' => new ArrayExpression(array(
                new ConstantExpression('foo', 0),
                new ConstantExpression('bar', 0),
            ), 0),
        )));

        yield array(new Node(array(
            'variables' => new ArrayExpression(array(
                new ConstantExpression('foo', 0),
                new ConstantExpression('bar', 0),
            ), 0),
            'view' => new NameExpression('form', 0),
        )));
    }

    /**
     * @dataProvider getCompileLabelWithLabelAndAttributesCases
     */
    public function testCompileLabelWithLabelAndAttributes(Node $arguments)
    {
        $node = new SearchAndRenderBlockNode('form_label', $arguments, 0);

        $compiler = new Compiler(new Environment($this->getMockBuilder('Twig\Loader\LoaderInterface')->getMock()));

        $this->assertEquals(
            sprintf(
                '$this->env->getRuntime(\'Symfony\Component\Form\FormRenderer\')->searchAndRenderBlock(%s, \'label\', array("foo" => "bar", "label" => "value in argument"))',
                $this->getVariableGetter('form')
            ),
            trim($compiler->compile($node)->getSource())
        );
    }

    public function getCompileLabelWithLabelAndAttributesCases()
    {
        yield array(new Node(array(
            new NameExpression('form', 0),
            new ConstantExpression('value in argument', 0),
            new ArrayExpression(array(
                new ConstantExpression('foo', 0),
                new ConstantExpression('bar', 0),
                new ConstantExpression('label', 0),
                new ConstantExpression('value in attributes', 0),
            ), 0),
        )));

        yield array(new Node(array(
            'view' => new NameExpression('form', 0),
            'label' => new ConstantExpression('value in argument', 0),
            'variables' => new ArrayExpression(array(
                new ConstantExpression('foo', 0),
                new ConstantExpression('bar', 0),
                new ConstantExpression('label', 0),
                new ConstantExpression('value in attributes', 0),
            ), 0),
        )));

        yield array(new Node(array(
            'view' => new NameExpression('form', 0),
            'variables' => new ArrayExpression(array(
                new ConstantExpression('foo', 0),
                new ConstantExpression('bar', 0),
                new ConstantExpression('label', 0),
                new ConstantExpression('value in attributes', 0),
            ), 0),
            'label' => new ConstantExpression('value in argument', 0),
        )));

        yield array(new Node(array(
            'label' => new ConstantExpression('value in argument', 0),
            'view' => new NameExpression('form', 0),
            'variables' => new ArrayExpression(array(
                new ConstantExpression('foo', 0),
                new ConstantExpression('bar', 0),
                new ConstantExpression('label', 0),
                new ConstantExpression('value in attributes', 0),
            ), 0),
        )));

        yield array(new Node(array(
            'label' => new ConstantExpression('value in argument', 0),
            'variables' => new ArrayExpression(array(
                new ConstantExpression('foo', 0),
                new ConstantExpression('bar', 0),
                new ConstantExpression('label', 0),
                new ConstantExpression('value in attributes', 0),
            ), 0),
            'view' => new NameExpression('form', 0),
        )));

        yield array(new Node(array(
            'variables' => new ArrayExpression(array(
                new ConstantExpression('foo', 0),
                new ConstantExpression('bar', 0),
                new ConstantExpression('label', 0),
                new ConstantExpression('value in attributes', 0),
            ), 0),
            'view' => new NameExpression('form', 0),
            'label' => new ConstantExpression('value in argument', 0),
        )));

        yield array(new Node(array(
            'variables' => new ArrayExpression(array(
                new ConstantExpression('foo', 0),
                new ConstantExpression('bar', 0),
                new ConstantExpression('label', 0),
                new ConstantExpression('value in attributes', 0),
            ), 0),
            'label' => new ConstantExpression('value in argument', 0),
            'view' => new NameExpression('form', 0),
        )));
    }

    /**
     * @dataProvider getCompileLabelWithLabelThatEvaluatesToNullCases
     */
    public function testCompileLabelWithLabelThatEvaluatesToNull(Node $arguments)
    {
        $node = new SearchAndRenderBlockNode('form_label', $arguments, 0);

        $compiler = new Compiler(new Environment($this->getMockBuilder('Twig\Loader\LoaderInterface')->getMock()));

        // "label" => null must not be included in the output!
        // Otherwise the default label is overwritten with null.
        // https://github.com/symfony/symfony/issues/5029
        $this->assertEquals(
            sprintf(
                '$this->env->getRuntime(\'Symfony\Component\Form\FormRenderer\')->searchAndRenderBlock(%s, \'label\', (twig_test_empty($_label_ = ((true) ? (null) : (null))) ? array() : array("label" => $_label_)))',
                $this->getVariableGetter('form')
            ),
            trim($compiler->compile($node)->getSource())
        );
    }

    public function getCompileLabelWithLabelThatEvaluatesToNullCases()
    {
        yield array(new Node(array(
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
        )));

        yield array(new Node(array(
            'view' => new NameExpression('form', 0),
            'label' => new ConditionalExpression(
                // if
                new ConstantExpression(true, 0),
                // then
                new ConstantExpression(null, 0),
                // else
                new ConstantExpression(null, 0),
                0
            ),
        )));

        yield array(new Node(array(
            'label' => new ConditionalExpression(
                // if
                new ConstantExpression(true, 0),
                // then
                new ConstantExpression(null, 0),
                // else
                new ConstantExpression(null, 0),
                0
            ),
            'view' => new NameExpression('form', 0),
        )));
    }

    /**
     * @dataProvider getCompileLabelWithLabelThatEvaluatesToNullAndAttributesCases
     */
    public function testCompileLabelWithLabelThatEvaluatesToNullAndAttributes(Node $arguments)
    {
        $node = new SearchAndRenderBlockNode('form_label', $arguments, 0);

        $compiler = new Compiler(new Environment($this->getMockBuilder('Twig\Loader\LoaderInterface')->getMock()));

        // "label" => null must not be included in the output!
        // Otherwise the default label is overwritten with null.
        // https://github.com/symfony/symfony/issues/5029
        $this->assertEquals(
            sprintf(
                '$this->env->getRuntime(\'Symfony\Component\Form\FormRenderer\')->searchAndRenderBlock(%s, \'label\', array("foo" => "bar", "label" => "value in attributes") + (twig_test_empty($_label_ = ((true) ? (null) : (null))) ? array() : array("label" => $_label_)))',
                $this->getVariableGetter('form')
            ),
            trim($compiler->compile($node)->getSource())
        );
    }

    public function getCompileLabelWithLabelThatEvaluatesToNullAndAttributesCases()
    {
        yield array(new Node(array(
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
        )));

        yield array(new Node(array(
            'view' => new NameExpression('form', 0),
            'label' => new ConditionalExpression(
                // if
                new ConstantExpression(true, 0),
                // then
                new ConstantExpression(null, 0),
                // else
                new ConstantExpression(null, 0),
                0
            ),
            'variables' => new ArrayExpression(array(
                new ConstantExpression('foo', 0),
                new ConstantExpression('bar', 0),
                new ConstantExpression('label', 0),
                new ConstantExpression('value in attributes', 0),
            ), 0),
        )));

        yield array(new Node(array(
            'view' => new NameExpression('form', 0),
            'variables' => new ArrayExpression(array(
                new ConstantExpression('foo', 0),
                new ConstantExpression('bar', 0),
                new ConstantExpression('label', 0),
                new ConstantExpression('value in attributes', 0),
            ), 0),
            'label' => new ConditionalExpression(
                // if
                new ConstantExpression(true, 0),
                // then
                new ConstantExpression(null, 0),
                // else
                new ConstantExpression(null, 0),
                0
            ),
        )));

        yield array(new Node(array(
            'label' => new ConditionalExpression(
                // if
                new ConstantExpression(true, 0),
                // then
                new ConstantExpression(null, 0),
                // else
                new ConstantExpression(null, 0),
                0
            ),
            'view' => new NameExpression('form', 0),
            'variables' => new ArrayExpression(array(
                new ConstantExpression('foo', 0),
                new ConstantExpression('bar', 0),
                new ConstantExpression('label', 0),
                new ConstantExpression('value in attributes', 0),
            ), 0),
        )));

        yield array(new Node(array(
            'label' => new ConditionalExpression(
                // if
                new ConstantExpression(true, 0),
                // then
                new ConstantExpression(null, 0),
                // else
                new ConstantExpression(null, 0),
                0
            ),
            'variables' => new ArrayExpression(array(
                new ConstantExpression('foo', 0),
                new ConstantExpression('bar', 0),
                new ConstantExpression('label', 0),
                new ConstantExpression('value in attributes', 0),
            ), 0),
            'view' => new NameExpression('form', 0),
        )));

        yield array(new Node(array(
            'variables' => new ArrayExpression(array(
                new ConstantExpression('foo', 0),
                new ConstantExpression('bar', 0),
                new ConstantExpression('label', 0),
                new ConstantExpression('value in attributes', 0),
            ), 0),
            'view' => new NameExpression('form', 0),
            'label' => new ConditionalExpression(
                // if
                new ConstantExpression(true, 0),
                // then
                new ConstantExpression(null, 0),
                // else
                new ConstantExpression(null, 0),
                0
            ),
        )));

        yield array(new Node(array(
            'variables' => new ArrayExpression(array(
                new ConstantExpression('foo', 0),
                new ConstantExpression('bar', 0),
                new ConstantExpression('label', 0),
                new ConstantExpression('value in attributes', 0),
            ), 0),
            'label' => new ConditionalExpression(
                // if
                new ConstantExpression(true, 0),
                // then
                new ConstantExpression(null, 0),
                // else
                new ConstantExpression(null, 0),
                0
            ),
            'view' => new NameExpression('form', 0),
        )));
    }

    public function testCompileLabelWithUnknownParameter()
    {
        $node = new SearchAndRenderBlockNode('form_label', new Node(array(
            'foo' => new NameExpression('form', 0),
        )), 0);

        $compiler = new Compiler(new Environment($this->getMockBuilder('Twig_LoaderInterface')->getMock()));

        $this->{method_exists($this, $_ = 'expectException') ? $_ : 'setExpectedException'}(
            'LogicException',
            'Unknown argument "foo" for function "form_label".'
        );

        $compiler->compile($node);
    }

    public function testCompileLabelWithDuplicatedParameter()
    {
        $node = new SearchAndRenderBlockNode('form_label', new Node(array(
            new NameExpression('form', 0),
            'view' => new NameExpression('form', 0),
        )), 0);

        $compiler = new Compiler(new Environment($this->getMockBuilder('Twig_LoaderInterface')->getMock()));

        $this->{method_exists($this, $_ = 'expectException') ? $_ : 'setExpectedException'}(
            'Twig_Error_Syntax',
            'Argument "view" is defined twice for function "form_label".'
        );

        $compiler->compile($node);
    }

    public function testCompileLabelWithPositionalParameterAfterNamedParameter()
    {
        $node = new SearchAndRenderBlockNode('form_label', new Node(array(
            'view' => new NameExpression('form', 0),
            new ArrayExpression(array(
                new ConstantExpression('foo', 0),
                new ConstantExpression('bar', 0),
            ), 0),
        )), 0);

        $compiler = new Compiler(new Environment($this->getMockBuilder('Twig_LoaderInterface')->getMock()));

        $this->{method_exists($this, $_ = 'expectException') ? $_ : 'setExpectedException'}(
            'Twig_Error_Syntax',
            'Positional arguments cannot be used after named arguments for function "form_label".'
        );

        $compiler->compile($node);
    }

    protected function getVariableGetter($name)
    {
        if (\PHP_VERSION_ID >= 70000) {
            return sprintf('($context["%s"] ?? null)', $name, $name);
        }

        return sprintf('(isset($context["%s"]) ? $context["%s"] : null)', $name, $name);
    }
}
