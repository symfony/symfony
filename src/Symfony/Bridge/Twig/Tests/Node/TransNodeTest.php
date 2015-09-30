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

        $env = new \Twig_Environment($this->getMock('Twig_LoaderInterface'), array('strict_variables' => true));
        $compiler = new \Twig_Compiler($env);

        $this->assertEquals(
            sprintf(
                'echo $this->env->getExtension(\'translator\')->getTranslator()->trans("trans %%var%%", array_merge(array("%%var%%" => %s), %s), "messages");',
                $this->getVariableGetterWithoutStrictCheck('var'),
                $this->getVariableGetterWithStrictCheck('foo')
             ),
             trim($compiler->compile($node)->getSource())
        );
    }
    protected function getVariableGetterWithoutStrictCheck($name)
    {
        return sprintf('(isset($context["%s"]) ? $context["%s"] : null)', $name, $name);
    }

    protected function getVariableGetterWithStrictCheck($name)
    {
        if (version_compare(\Twig_Environment::VERSION, '2.0.0-DEV', '>=')) {
            return sprintf('(isset($context["%s"]) || array_key_exists("%s", $context) ? $context["%s"] : $this->notFound("%s", 0))', $name, $name, $name, $name);
        }

        return sprintf('(isset($context["%1$s"]) ? $context["%1$s"] : $this->getContext($context, "%1$s"))', $name);
    }
}
