<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Tests\DependencyInjection\Compiler;

use Symfony\Bundle\FrameworkBundle\DependencyInjection\Compiler\LoggingTranslatorPass;

class LoggingTranslatorPassTest extends \PHPUnit_Framework_TestCase
{
    public function testProcess()
    {
        $definition = $this->getMock('Symfony\Component\DependencyInjection\Definition');
        $container = $this->getMock('Symfony\Component\DependencyInjection\ContainerBuilder');
        $parameterBag = $this->getMock('Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface');

        $container->expects($this->exactly(2))
            ->method('hasAlias')
            ->will($this->returnValue(true));

        $container->expects($this->once())
            ->method('getParameter')
            ->will($this->returnValue(true));

        $container->expects($this->once())
            ->method('getAlias')
            ->will($this->returnValue('translation.default'));

        $container->expects($this->exactly(3))
            ->method('getDefinition')
            ->will($this->returnValue($definition));

        $container->expects($this->once())
            ->method('hasParameter')
            ->with('translator.logging')
            ->will($this->returnValue(true));

        $definition->expects($this->once())
            ->method('getClass')
            ->will($this->returnValue('%translator.class%'));

        $parameterBag->expects($this->once())
            ->method('resolveValue')
            ->will($this->returnValue("Symfony\Bundle\FrameworkBundle\Translation\Translator"));

        $container->expects($this->once())
            ->method('getParameterBag')
            ->will($this->returnValue($parameterBag));

        $pass = new LoggingTranslatorPass();
        $pass->process($container);
    }

    public function testThatCompilerPassIsIgnoredIfThereIsNotLoggerDefinition()
    {
        $container = $this->getMock('Symfony\Component\DependencyInjection\ContainerBuilder');
        $container->expects($this->once())
            ->method('hasAlias')
            ->will($this->returnValue(false));

        $pass = new LoggingTranslatorPass();
        $pass->process($container);
    }

    public function testThatCompilerPassIsIgnoredIfThereIsNotTranslatorDefinition()
    {
        $container = $this->getMock('Symfony\Component\DependencyInjection\ContainerBuilder');
        $container->expects($this->at(0))
            ->method('hasAlias')
            ->will($this->returnValue(true));

        $container->expects($this->at(0))
            ->method('hasAlias')
            ->will($this->returnValue(false));

        $pass = new LoggingTranslatorPass();
        $pass->process($container);
    }
}
