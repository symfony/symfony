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

use Symfony\Bridge\Twig\Node\TransNode;

/**
 * @author Asmir Mustafic <goetas@gmail.com>
 */
class TransNodeTest extends \PHPUnit_Framework_TestCase
{
    public function testCompileStrict()
    {
        $body = new \Twig_Node_Text('trans %var%', 0);
        $vars = new \Twig_Node_Expression_Name('foo', 0);
        $node = new TransNode($body, null, null, $vars);

        $env = new \Twig_Environment($this->getMockBuilder('Twig_LoaderInterface')->getMock(), array('strict_variables' => true));
        $compiler = new \Twig_Compiler($env);

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
        if (PHP_VERSION_ID >= 70000) {
            return sprintf('($context["%s"] ?? null)', $name, $name);
        }

        return sprintf('(isset($context["%s"]) ? $context["%s"] : null)', $name, $name);
    }

    protected function getVariableGetterWithStrictCheck($name)
    {
        if (\Twig_Environment::MAJOR_VERSION >= 2) {
            return sprintf('(isset($context["%s"]) || array_key_exists("%s", $context) ? $context["%s"] : (function () { throw new Twig_Error_Runtime(\'Variable "%s" does not exist.\', 0, $this->getSourceContext()); })())', $name, $name, $name, $name);
        }

        if (PHP_VERSION_ID >= 70000) {
            return sprintf('($context["%s"] ?? $this->getContext($context, "%s"))', $name, $name, $name);
        }

        return sprintf('(isset($context["%s"]) ? $context["%s"] : $this->getContext($context, "%s"))', $name, $name, $name);
    }
}
