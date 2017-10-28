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
use Twig\Compiler;
use Twig\Environment;
use Twig\Node\Expression\ArrayExpression;
use Twig\Node\Expression\ConstantExpression;
use Twig\Node\Expression\NameExpression;
use Twig\Node\Node;

class FormThemeTest extends TestCase
{
    public function testConstructor()
    {
        $form = new NameExpression('form', 0);
        $resources = new Node(array(
            new ConstantExpression('tpl1', 0),
            new ConstantExpression('tpl2', 0),
        ));

        $node = new FormThemeNode($form, $resources, 0);

        $this->assertEquals($form, $node->getNode('form'));
        $this->assertEquals($resources, $node->getNode('resources'));
    }

    public function testCompile()
    {
        $form = new NameExpression('form', 0);
        $resources = new ArrayExpression(array(
            new ConstantExpression(0, 0),
            new ConstantExpression('tpl1', 0),
            new ConstantExpression(1, 0),
            new ConstantExpression('tpl2', 0),
        ), 0);

        $node = new FormThemeNode($form, $resources, 0);

        $compiler = new Compiler(new Environment($this->getMockBuilder('Twig\Loader\LoaderInterface')->getMock()));

        $this->assertEquals(
            sprintf(
                '$this->env->getRuntime(\'Symfony\Bridge\Twig\Form\TwigRenderer\')->setTheme(%s, array(0 => "tpl1", 1 => "tpl2"));',
                $this->getVariableGetter('form')
             ),
            trim($compiler->compile($node)->getSource())
        );

        $resources = new ConstantExpression('tpl1', 0);

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
        if (\PHP_VERSION_ID >= 70000) {
            return sprintf('($context["%s"] ?? null)', $name);
        }

        return sprintf('(isset($context["%s"]) ? $context["%1$s"] : null)', $name);
    }
}
