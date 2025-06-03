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
use Twig\Attribute\FirstClassTwigCallableReady;
use Twig\Compiler;
use Twig\Environment;
use Twig\Extension\CoreExtension;
use Twig\Loader\LoaderInterface;
use Twig\Node\Expression\ArrayExpression;
use Twig\Node\Expression\ConditionalExpression;
use Twig\Node\Expression\ConstantExpression;
use Twig\Node\Expression\NameExpression;
use Twig\Node\Expression\Ternary\ConditionalTernary;
use Twig\Node\Expression\Variable\ContextVariable;
use Twig\Node\Node;
use Twig\Node\Nodes;
use Twig\TwigFunction;

class SearchAndRenderBlockNodeTest extends TestCase
{
    public function testCompileWidget()
    {
        if (class_exists(Nodes::class)) {
            $arguments = new Nodes([
                new ContextVariable('form', 0),
            ]);
        } else {
            $arguments = new Node([
                new NameExpression('form', 0),
            ]);
        }

        if (class_exists(FirstClassTwigCallableReady::class)) {
            $node = new SearchAndRenderBlockNode(new TwigFunction('form_widget'), $arguments, 0);
        } else {
            $node = new SearchAndRenderBlockNode('form_widget', $arguments, 0);
        }

        $compiler = new Compiler(new Environment($this->createMock(LoaderInterface::class)));

        $this->assertEquals(
            sprintf(
                '$this->env->getRuntime(\'Symfony\Component\Form\FormRenderer\')->searchAndRenderBlock(%s, \'widget\')',
                $this->getVariableGetter('form')
            ),
            trim($compiler->compile($node)->getSource())
        );
    }

    public function testCompileWidgetWithVariables()
    {
        if (class_exists(Nodes::class)) {
            $arguments = new Nodes([
                new ContextVariable('form', 0),
                new ArrayExpression([
                    new ConstantExpression('foo', 0),
                    new ConstantExpression('bar', 0),
                ], 0),
            ]);
        } else {
            $arguments = new Node([
                new NameExpression('form', 0),
                new ArrayExpression([
                    new ConstantExpression('foo', 0),
                    new ConstantExpression('bar', 0),
                ], 0),
            ]);
        }

        if (class_exists(FirstClassTwigCallableReady::class)) {
            $node = new SearchAndRenderBlockNode(new TwigFunction('form_widget'), $arguments, 0);
        } else {
            $node = new SearchAndRenderBlockNode('form_widget', $arguments, 0);
        }

        $compiler = new Compiler(new Environment($this->createMock(LoaderInterface::class)));

        $this->assertEquals(
            sprintf(
                '$this->env->getRuntime(\'Symfony\Component\Form\FormRenderer\')->searchAndRenderBlock(%s, \'widget\', ["foo" => "bar"])',
                $this->getVariableGetter('form')
            ),
            trim($compiler->compile($node)->getSource())
        );
    }

    public function testCompileLabelWithLabel()
    {
        if (class_exists(Nodes::class)) {
            $arguments = new Nodes([
                new ContextVariable('form', 0),
                new ConstantExpression('my label', 0),
            ]);
        } else {
            $arguments = new Node([
                new NameExpression('form', 0),
                new ConstantExpression('my label', 0),
            ]);
        }

        if (class_exists(FirstClassTwigCallableReady::class)) {
            $node = new SearchAndRenderBlockNode(new TwigFunction('form_label'), $arguments, 0);
        } else {
            $node = new SearchAndRenderBlockNode('form_label', $arguments, 0);
        }

        $compiler = new Compiler(new Environment($this->createMock(LoaderInterface::class)));

        $this->assertEquals(
            sprintf(
                '$this->env->getRuntime(\'Symfony\Component\Form\FormRenderer\')->searchAndRenderBlock(%s, \'label\', ["label" => "my label"])',
                $this->getVariableGetter('form')
            ),
            trim($compiler->compile($node)->getSource())
        );
    }

    public function testCompileLabelWithNullLabel()
    {
        if (class_exists(Nodes::class)) {
            $arguments = new Nodes([
                new ContextVariable('form', 0),
                new ConstantExpression(null, 0),
            ]);
        } else {
            $arguments = new Node([
                new NameExpression('form', 0),
                new ConstantExpression(null, 0),
            ]);
        }

        if (class_exists(FirstClassTwigCallableReady::class)) {
            $node = new SearchAndRenderBlockNode(new TwigFunction('form_label'), $arguments, 0);
        } else {
            $node = new SearchAndRenderBlockNode('form_label', $arguments, 0);
        }

        $compiler = new Compiler(new Environment($this->createMock(LoaderInterface::class)));

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

    public function testCompileLabelWithEmptyStringLabel()
    {
        if (class_exists(Nodes::class)) {
            $arguments = new Nodes([
                new ContextVariable('form', 0),
                new ConstantExpression('', 0),
            ]);
        } else {
            $arguments = new Node([
                new NameExpression('form', 0),
                new ConstantExpression('', 0),
            ]);
        }

        if (class_exists(FirstClassTwigCallableReady::class)) {
            $node = new SearchAndRenderBlockNode(new TwigFunction('form_label'), $arguments, 0);
        } else {
            $node = new SearchAndRenderBlockNode('form_label', $arguments, 0);
        }

        $compiler = new Compiler(new Environment($this->createMock(LoaderInterface::class)));

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

    public function testCompileLabelWithDefaultLabel()
    {
        if (class_exists(Nodes::class)) {
            $arguments = new Nodes([
                new ContextVariable('form', 0),
            ]);
        } else {
            $arguments = new Node([
                new NameExpression('form', 0),
            ]);
        }

        if (class_exists(FirstClassTwigCallableReady::class)) {
            $node = new SearchAndRenderBlockNode(new TwigFunction('form_label'), $arguments, 0);
        } else {
            $node = new SearchAndRenderBlockNode('form_label', $arguments, 0);
        }

        $compiler = new Compiler(new Environment($this->createMock(LoaderInterface::class)));

        $this->assertEquals(
            sprintf(
                '$this->env->getRuntime(\'Symfony\Component\Form\FormRenderer\')->searchAndRenderBlock(%s, \'label\')',
                $this->getVariableGetter('form')
            ),
            trim($compiler->compile($node)->getSource())
        );
    }

    public function testCompileLabelWithAttributes()
    {
        if (class_exists(Nodes::class)) {
            $arguments = new Nodes([
                new ContextVariable('form', 0),
                new ConstantExpression(null, 0),
                new ArrayExpression([
                    new ConstantExpression('foo', 0),
                    new ConstantExpression('bar', 0),
                ], 0),
            ]);
        } else {
            $arguments = new Node([
                new NameExpression('form', 0),
                new ConstantExpression(null, 0),
                new ArrayExpression([
                    new ConstantExpression('foo', 0),
                    new ConstantExpression('bar', 0),
                ], 0),
            ]);
        }

        if (class_exists(FirstClassTwigCallableReady::class)) {
            $node = new SearchAndRenderBlockNode(new TwigFunction('form_label'), $arguments, 0);
        } else {
            $node = new SearchAndRenderBlockNode('form_label', $arguments, 0);
        }

        $compiler = new Compiler(new Environment($this->createMock(LoaderInterface::class)));

        // "label" => null must not be included in the output!
        // Otherwise the default label is overwritten with null.
        // https://github.com/symfony/symfony/issues/5029
        $this->assertEquals(
            sprintf(
                '$this->env->getRuntime(\'Symfony\Component\Form\FormRenderer\')->searchAndRenderBlock(%s, \'label\', ["foo" => "bar"])',
                $this->getVariableGetter('form')
            ),
            trim($compiler->compile($node)->getSource())
        );
    }

    public function testCompileLabelWithLabelAndAttributes()
    {
        if (class_exists(Nodes::class)) {
            $arguments = new Nodes([
                new ContextVariable('form', 0),
                new ConstantExpression('value in argument', 0),
                new ArrayExpression([
                    new ConstantExpression('foo', 0),
                    new ConstantExpression('bar', 0),
                    new ConstantExpression('label', 0),
                    new ConstantExpression('value in attributes', 0),
                ], 0),
            ]);
        } else {
            $arguments = new Node([
                new NameExpression('form', 0),
                new ConstantExpression('value in argument', 0),
                new ArrayExpression([
                    new ConstantExpression('foo', 0),
                    new ConstantExpression('bar', 0),
                    new ConstantExpression('label', 0),
                    new ConstantExpression('value in attributes', 0),
                ], 0),
            ]);
        }

        if (class_exists(FirstClassTwigCallableReady::class)) {
            $node = new SearchAndRenderBlockNode(new TwigFunction('form_label'), $arguments, 0);
        } else {
            $node = new SearchAndRenderBlockNode('form_label', $arguments, 0);
        }

        $compiler = new Compiler(new Environment($this->createMock(LoaderInterface::class)));

        $this->assertEquals(
            sprintf(
                '$this->env->getRuntime(\'Symfony\Component\Form\FormRenderer\')->searchAndRenderBlock(%s, \'label\', ["foo" => "bar", "label" => "value in argument"])',
                $this->getVariableGetter('form')
            ),
            trim($compiler->compile($node)->getSource())
        );
    }

    public function testCompileLabelWithLabelThatEvaluatesToNull()
    {
        if (class_exists(ConditionalTernary::class)) {
            $conditional = new ConditionalTernary(
                // if
                new ConstantExpression(true, 0),
                // then
                new ConstantExpression(null, 0),
                // else
                new ConstantExpression(null, 0),
                0
            );
        } else {
            $conditional = new ConditionalExpression(
                // if
                new ConstantExpression(true, 0),
                // then
                new ConstantExpression(null, 0),
                // else
                new ConstantExpression(null, 0),
                0
            );
        }

        if (class_exists(Nodes::class)) {
            $arguments = new Nodes([new ContextVariable('form', 0), $conditional]);
        } else {
            $arguments = new Node([new NameExpression('form', 0), $conditional]);
        }

        if (class_exists(FirstClassTwigCallableReady::class)) {
            $node = new SearchAndRenderBlockNode(new TwigFunction('form_label'), $arguments, 0);
        } else {
            $node = new SearchAndRenderBlockNode('form_label', $arguments, 0);
        }

        $compiler = new Compiler(new Environment($this->createMock(LoaderInterface::class)));

        // "label" => null must not be included in the output!
        // Otherwise the default label is overwritten with null.
        // https://github.com/symfony/symfony/issues/5029
        $this->assertEquals(
            sprintf(
                '$this->env->getRuntime(\'Symfony\Component\Form\FormRenderer\')->searchAndRenderBlock(%s, \'label\', (%s($_label_ = ((true) ? (null) : (null))) ? [] : ["label" => $_label_]))',
                $this->getVariableGetter('form'),
                method_exists(CoreExtension::class, 'testEmpty') ? 'CoreExtension::testEmpty' : 'twig_test_empty'
            ),
            trim($compiler->compile($node)->getSource())
        );
    }

    public function testCompileLabelWithLabelThatEvaluatesToNullAndAttributes()
    {
        if (class_exists(ConditionalTernary::class)) {
            $conditional = new ConditionalTernary(
                // if
                new ConstantExpression(true, 0),
                // then
                new ConstantExpression(null, 0),
                // else
                new ConstantExpression(null, 0),
                0
            );
        } else {
            $conditional = new ConditionalExpression(
                // if
                new ConstantExpression(true, 0),
                // then
                new ConstantExpression(null, 0),
                // else
                new ConstantExpression(null, 0),
                0
            );
        }

        if (class_exists(Nodes::class)) {
            $arguments = new Nodes([
                new ContextVariable('form', 0),
                $conditional,
                new ArrayExpression([
                    new ConstantExpression('foo', 0),
                    new ConstantExpression('bar', 0),
                    new ConstantExpression('label', 0),
                    new ConstantExpression('value in attributes', 0),
                ], 0),
            ]);
        } else {
            $arguments = new Node([
                new NameExpression('form', 0),
                $conditional,
                new ArrayExpression([
                    new ConstantExpression('foo', 0),
                    new ConstantExpression('bar', 0),
                    new ConstantExpression('label', 0),
                    new ConstantExpression('value in attributes', 0),
                ], 0),
            ]);
        }

        if (class_exists(FirstClassTwigCallableReady::class)) {
            $node = new SearchAndRenderBlockNode(new TwigFunction('form_label'), $arguments, 0);
        } else {
            $node = new SearchAndRenderBlockNode('form_label', $arguments, 0);
        }

        $compiler = new Compiler(new Environment($this->createMock(LoaderInterface::class)));

        // "label" => null must not be included in the output!
        // Otherwise the default label is overwritten with null.
        // https://github.com/symfony/symfony/issues/5029
        $this->assertEquals(
            sprintf(
                '$this->env->getRuntime(\'Symfony\Component\Form\FormRenderer\')->searchAndRenderBlock(%s, \'label\', ["foo" => "bar", "label" => "value in attributes"] + (%s($_label_ = ((true) ? (null) : (null))) ? [] : ["label" => $_label_]))',
                $this->getVariableGetter('form'),
                method_exists(CoreExtension::class, 'testEmpty') ? 'CoreExtension::testEmpty' : 'twig_test_empty'
            ),
            trim($compiler->compile($node)->getSource())
        );
    }

    protected function getVariableGetter($name)
    {
        return sprintf('($context["%s"] ?? null)', $name);
    }
}
