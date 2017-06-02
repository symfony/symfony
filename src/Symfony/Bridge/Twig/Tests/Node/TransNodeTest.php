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
use Twig\Node\Expression\NameExpression;
use Twig\Node\TextNode;

/**
 * @author Asmir Mustafic <goetas@gmail.com>
 */
class TransNodeTest extends TestCase
{
    public function testCompileStrict()
    {
        $body = new TextNode('trans %var%', 0);
        $vars = new NameExpression('foo', 0);
        $node = new TransNode($body, null, null, $vars);

        $env = new Environment($this->getMockBuilder('Twig\Loader\LoaderInterface')->getMock(), array('strict_variables' => true));
        $compiler = new Compiler($env);

        $this->assertEquals(
            sprintf(
                'echo $this->env->getExtension(\'Symfony\Bridge\Twig\Extension\TranslationExtension\')->getTranslator()->trans("trans %%var%%", array_merge(array("%%var%%" => %s), %s), "messages");',
                $this->getVariableGetterWithoutStrictCheck('var'),
                $this->getVariableGetterWithStrictCheck('foo')
             ),
             trim($compiler->compile($node)->getSource())
        );
    }

    protected function getVariableGetterWithoutStrictCheck($name)
    {
        if (\PHP_VERSION_ID >= 70000) {
            return sprintf('($context["%s"] ?? null)', $name, $name);
        }

        return sprintf('(isset($context["%s"]) ? $context["%s"] : null)', $name, $name);
    }

    protected function getVariableGetterWithStrictCheck($name)
    {
        if (Environment::MAJOR_VERSION >= 2) {
            return sprintf('(isset($context["%s"]) || array_key_exists("%s", $context) ? $context["%s"] : (function () { throw new Twig_Error_Runtime(\'Variable "%s" does not exist.\', 0, $this->getSourceContext()); })())', $name, $name, $name, $name);
        }

        if (\PHP_VERSION_ID >= 70000) {
            return sprintf('($context["%s"] ?? $this->getContext($context, "%s"))', $name, $name, $name);
        }

        return sprintf('(isset($context["%s"]) ? $context["%s"] : $this->getContext($context, "%s"))', $name, $name, $name);
    }
}
