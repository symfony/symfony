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
use Symfony\Bridge\Twig\Node\TransNode;
use Twig\Compiler;
use Twig\Environment;
use Twig\Loader\LoaderInterface;
use Twig\Node\Expression\NameExpression;
use Twig\Node\Expression\Variable\ContextVariable;
use Twig\Node\TextNode;

/**
 * @author Asmir Mustafic <goetas@gmail.com>
 */
class TransNodeTest extends TestCase
{
    public function testCompileStrict()
    {
        $body = new TextNode('trans %var%', 0);
        $vars = class_exists(ContextVariable::class) ? new ContextVariable('foo', 0) : new NameExpression('foo', 0);
        $node = new TransNode($body, null, null, $vars);

        $env = new Environment($this->createMock(LoaderInterface::class), ['strict_variables' => true]);
        $compiler = new Compiler($env);

        $this->assertEquals(
            \sprintf(
                'yield $this->env->getExtension(\'Symfony\Bridge\Twig\Extension\TranslationExtension\')->trans("trans %%var%%", array_merge(["%%var%%" => %s], %s), "messages");',
                $this->getVariableGetterWithoutStrictCheck('var'),
                $this->getVariableGetterWithStrictCheck('foo')
            ),
            trim($compiler->compile($node)->getSource())
        );
    }

    protected function getVariableGetterWithoutStrictCheck($name)
    {
        return \sprintf('($context["%s"] ?? null)', $name);
    }

    protected function getVariableGetterWithStrictCheck($name)
    {
        return \sprintf('(isset($context["%1$s"]) || array_key_exists("%1$s", $context) ? $context["%1$s"] : (function () { throw new RuntimeError(\'Variable "%1$s" does not exist.\', 0, $this->source); })())', $name);
    }
}
