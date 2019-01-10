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
use Symfony\Bridge\Twig\Tests\Extension\RuntimeLoaderProvider;
use Symfony\Component\Form\FormRenderer;
use Symfony\Component\Form\FormRendererEngineInterface;
use Twig\Compiler;
use Twig\Environment;
use Twig\Node\Expression\ArrayExpression;
use Twig\Node\Expression\ConstantExpression;
use Twig\Node\Expression\NameExpression;
use Twig\Node\Node;

class FormThemeTest extends TestCase
{
    use RuntimeLoaderProvider;

    public function testConstructor()
    {
        $form = new NameExpression('form', 0);
        $resources = new Node([
            new ConstantExpression('tpl1', 0),
            new ConstantExpression('tpl2', 0),
        ]);

        $node = new FormThemeNode($form, $resources, 0);

        $this->assertEquals($form, $node->getNode('form'));
        $this->assertEquals($resources, $node->getNode('resources'));
        $this->assertFalse($node->getAttribute('only'));
    }

    public function testCompile()
    {
        $form = new NameExpression('form', 0);
        $resources = new ArrayExpression([
            new ConstantExpression(0, 0),
            new ConstantExpression('tpl1', 0),
            new ConstantExpression(1, 0),
            new ConstantExpression('tpl2', 0),
        ], 0);

        $node = new FormThemeNode($form, $resources, 0);

        $environment = new Environment($this->getMockBuilder('Twig\Loader\LoaderInterface')->getMock());
        $formRenderer = new FormRenderer($this->getMockBuilder(FormRendererEngineInterface::class)->getMock());
        $this->registerTwigRuntimeLoader($environment, $formRenderer);
        $compiler = new Compiler($environment);

        $this->assertEquals(
            sprintf(
                '$this->env->getRuntime("Symfony\\\\Component\\\\Form\\\\FormRenderer")->setTheme(%s, [0 => "tpl1", 1 => "tpl2"], true);',
                $this->getVariableGetter('form')
             ),
            trim($compiler->compile($node)->getSource())
        );

        $node = new FormThemeNode($form, $resources, 0, null, true);

        $this->assertEquals(
            sprintf(
                '$this->env->getRuntime("Symfony\\\\Component\\\\Form\\\\FormRenderer")->setTheme(%s, [0 => "tpl1", 1 => "tpl2"], false);',
                $this->getVariableGetter('form')
             ),
            trim($compiler->compile($node)->getSource())
        );

        $resources = new ConstantExpression('tpl1', 0);

        $node = new FormThemeNode($form, $resources, 0);

        $this->assertEquals(
            sprintf(
                '$this->env->getRuntime("Symfony\\\\Component\\\\Form\\\\FormRenderer")->setTheme(%s, "tpl1", true);',
                $this->getVariableGetter('form')
             ),
            trim($compiler->compile($node)->getSource())
        );

        $node = new FormThemeNode($form, $resources, 0, null, true);

        $this->assertEquals(
            sprintf(
                '$this->env->getRuntime("Symfony\\\\Component\\\\Form\\\\FormRenderer")->setTheme(%s, "tpl1", false);',
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
