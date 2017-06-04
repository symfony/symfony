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
use Symfony\Bridge\Twig\Node\RenderBlockNode;
use Twig\Compiler;
use Twig\Environment;
use Twig\Node\Expression\ArrayExpression;
use Twig\Node\Expression\ConstantExpression;
use Twig\Node\Expression\NameExpression;
use Twig\Node\Node;

class RenderBlockNodeTest extends TestCase
{
    /**
     * @dataProvider getCompileFormCases
     */
    public function testCompileForm(Node $arguments)
    {
        $node = new RenderBlockNode('form', $arguments, 0);

        $compiler = new Compiler(new Environment($this->getMockBuilder('Twig_LoaderInterface')->getMock()));

        $this->assertEquals(
            sprintf(
                '$this->env->getRuntime(\'Symfony\Component\Form\FormRenderer\')->renderBlock(%s, \'form\')',
                $this->getVariableGetter('form')
             ),
            trim($compiler->compile($node)->getSource())
        );
    }

    public function getCompileFormCases()
    {
        yield array(new Node(array(
            new NameExpression('form', 0),
        )));

        yield array(new Node(array(
            'view' => new NameExpression('form', 0),
        )));
    }

    /**
     * @dataProvider getCompileFormWithVariablesCases
     */
    public function testCompileFormWithVariables(Node $arguments)
    {
        $node = new RenderBlockNode('form', $arguments, 0);

        $compiler = new Compiler(new Environment($this->getMockBuilder('Twig_LoaderInterface')->getMock()));

        $this->assertEquals(
            sprintf(
                '$this->env->getRuntime(\'Symfony\Component\Form\FormRenderer\')->renderBlock(%s, \'form\', array("foo" => "bar"))',
                $this->getVariableGetter('form')
             ),
            trim($compiler->compile($node)->getSource())
        );
    }

    public function getCompileFormWithVariablesCases()
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

    public function testCompileFormWithUnknownParameter()
    {
        $node = new RenderBlockNode('form', new Node(array(
            'foo' => new NameExpression('form', 0),
        )), 0);

        $compiler = new Compiler(new Environment($this->getMockBuilder('Twig_LoaderInterface')->getMock()));

        $this->{method_exists($this, $_ = 'expectException') ? $_ : 'setExpectedException'}(
            'LogicException',
            'Unknown argument "foo" for function "form".'
        );

        $compiler->compile($node);
    }

    public function testCompileFormWithDuplicatedParameter()
    {
        $node = new RenderBlockNode('form', new Node(array(
            new NameExpression('form', 0),
            'view' => new NameExpression('form', 0),
        )), 0);

        $compiler = new Compiler(new Environment($this->getMockBuilder('Twig_LoaderInterface')->getMock()));

        $this->{method_exists($this, $_ = 'expectException') ? $_ : 'setExpectedException'}(
            'Twig_Error_Syntax',
            'Argument "view" is defined twice for function "form".'
        );

        $compiler->compile($node);
    }

    public function testCompileFormWithPositionalParameterAfterNamedParameter()
    {
        $node = new RenderBlockNode('form', new Node(array(
            'view' => new NameExpression('form', 0),
            new ArrayExpression(array(
                new ConstantExpression('foo', 0),
                new ConstantExpression('bar', 0),
            ), 0),
        )), 0);

        $compiler = new Compiler(new Environment($this->getMockBuilder('Twig_LoaderInterface')->getMock()));

        $this->{method_exists($this, $_ = 'expectException') ? $_ : 'setExpectedException'}(
            'Twig_Error_Syntax',
            'Positional arguments cannot be used after named arguments for function "form".'
        );

        $compiler->compile($node);
    }

    /**
     * @dataProvider getCompileFormStartCases
     */
    public function testCompileFormStart(Node $arguments)
    {
        $node = new RenderBlockNode('form_start', $arguments, 0);

        $compiler = new Compiler(new Environment($this->getMockBuilder('Twig_LoaderInterface')->getMock()));

        $this->assertEquals(
            sprintf(
                '$this->env->getRuntime(\'Symfony\Component\Form\FormRenderer\')->renderBlock(%s, \'form_start\')',
                $this->getVariableGetter('form')
             ),
            trim($compiler->compile($node)->getSource())
        );
    }

    public function getCompileFormStartCases()
    {
        yield array(new Node(array(
            new NameExpression('form', 0),
        )));

        yield array(new Node(array(
            'view' => new NameExpression('form', 0),
        )));
    }

    /**
     * @dataProvider getCompileFormStartWithVariablesCases
     */
    public function testCompileFormStartWithVariables(Node $arguments)
    {
        $node = new RenderBlockNode('form_start', $arguments, 0);

        $compiler = new Compiler(new Environment($this->getMockBuilder('Twig_LoaderInterface')->getMock()));

        $this->assertEquals(
            sprintf(
                '$this->env->getRuntime(\'Symfony\Component\Form\FormRenderer\')->renderBlock(%s, \'form_start\', array("foo" => "bar"))',
                $this->getVariableGetter('form')
             ),
            trim($compiler->compile($node)->getSource())
        );
    }

    public function getCompileFormStartWithVariablesCases()
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

    public function testCompileFormStartWithUnknownParameter()
    {
        $node = new RenderBlockNode('form_start', new Node(array(
            'foo' => new NameExpression('form', 0),
        )), 0);

        $compiler = new Compiler(new Environment($this->getMockBuilder('Twig_LoaderInterface')->getMock()));

        $this->{method_exists($this, $_ = 'expectException') ? $_ : 'setExpectedException'}(
            'LogicException',
            'Unknown argument "foo" for function "form_start".'
        );

        $compiler->compile($node);
    }

    public function testCompileFormStartWithDuplicatedParameter()
    {
        $node = new RenderBlockNode('form_start', new Node(array(
            new NameExpression('form', 0),
            'view' => new NameExpression('form', 0),
        )), 0);

        $compiler = new Compiler(new Environment($this->getMockBuilder('Twig_LoaderInterface')->getMock()));

        $this->{method_exists($this, $_ = 'expectException') ? $_ : 'setExpectedException'}(
            'Twig_Error_Syntax',
            'Argument "view" is defined twice for function "form_start".'
        );

        $compiler->compile($node);
    }

    public function testCompileFormStartWithPositionalParameterAfterNamedParameter()
    {
        $node = new RenderBlockNode('form_start', new Node(array(
            'view' => new NameExpression('form', 0),
            new ArrayExpression(array(
                new ConstantExpression('foo', 0),
                new ConstantExpression('bar', 0),
            ), 0),
        )), 0);

        $compiler = new Compiler(new Environment($this->getMockBuilder('Twig_LoaderInterface')->getMock()));

        $this->{method_exists($this, $_ = 'expectException') ? $_ : 'setExpectedException'}(
            'Twig_Error_Syntax',
            'Positional arguments cannot be used after named arguments for function "form_start".'
        );

        $compiler->compile($node);
    }

    /**
     * @dataProvider getCompileFormEndCases
     */
    public function testCompileFormEnd(Node $arguments)
    {
        $node = new RenderBlockNode('form_end', $arguments, 0);

        $compiler = new Compiler(new Environment($this->getMockBuilder('Twig_LoaderInterface')->getMock()));

        $this->assertEquals(
            sprintf(
                '$this->env->getRuntime(\'Symfony\Component\Form\FormRenderer\')->renderBlock(%s, \'form_end\')',
                $this->getVariableGetter('form')
             ),
            trim($compiler->compile($node)->getSource())
        );
    }

    public function getCompileFormEndCases()
    {
        yield array(new Node(array(
            new NameExpression('form', 0),
        )));

        yield array(new Node(array(
            'view' => new NameExpression('form', 0),
        )));
    }

    /**
     * @dataProvider getCompileFormEndWithVariablesCases
     */
    public function testCompileFormEndWithVariables(Node $arguments)
    {
        $node = new RenderBlockNode('form_end', $arguments, 0);

        $compiler = new Compiler(new Environment($this->getMockBuilder('Twig_LoaderInterface')->getMock()));

        $this->assertEquals(
            sprintf(
                '$this->env->getRuntime(\'Symfony\Component\Form\FormRenderer\')->renderBlock(%s, \'form_end\', array("foo" => "bar"))',
                $this->getVariableGetter('form')
             ),
            trim($compiler->compile($node)->getSource())
        );
    }

    public function getCompileFormEndWithVariablesCases()
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

    public function testCompileFormEndWithUnknownParameter()
    {
        $node = new RenderBlockNode('form_end', new Node(array(
            'foo' => new NameExpression('form', 0),
        )), 0);

        $compiler = new Compiler(new Environment($this->getMockBuilder('Twig_LoaderInterface')->getMock()));

        $this->{method_exists($this, $_ = 'expectException') ? $_ : 'setExpectedException'}(
            'LogicException',
            'Unknown argument "foo" for function "form_end".'
        );

        $compiler->compile($node);
    }

    public function testCompileFormEndWithDuplicatedParameter()
    {
        $node = new RenderBlockNode('form_end',
            new Node(array(
                new NameExpression('form', 0),
                'view' => new NameExpression('form', 0),
            )), 0);

        $compiler = new Compiler(new Environment($this->getMockBuilder('Twig_LoaderInterface')->getMock()));

        $this->{method_exists($this, $_ = 'expectException') ? $_ : 'setExpectedException'}(
            'Twig_Error_Syntax',
            'Argument "view" is defined twice for function "form_end".'
        );

        $compiler->compile($node);
    }

    public function testCompileFormEndWithPositionalParameterAfterNamedParameter()
    {
        $node = new RenderBlockNode('form_end', new Node(array(
            'view' => new NameExpression('form', 0),
            new ArrayExpression(array(
                new ConstantExpression('foo', 0),
                new ConstantExpression('bar', 0),
            ), 0),
        )), 0);

        $compiler = new Compiler(new Environment($this->getMockBuilder('Twig_LoaderInterface')->getMock()));

        $this->{method_exists($this, $_ = 'expectException') ? $_ : 'setExpectedException'}(
            'Twig_Error_Syntax',
            'Positional arguments cannot be used after named arguments for function "form_end".'
        );

        $compiler->compile($node);
    }

    private function getVariableGetter($name)
    {
        if (PHP_VERSION_ID >= 70000) {
            return sprintf('($context["%s"] ?? null)', $name, $name);
        }

        return sprintf('(isset($context["%s"]) ? $context["%s"] : null)', $name, $name);
    }
}
