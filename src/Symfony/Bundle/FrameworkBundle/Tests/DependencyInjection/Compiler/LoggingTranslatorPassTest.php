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

use PHPUnit\Framework\TestCase;
use Symfony\Bundle\FrameworkBundle\DependencyInjection\Compiler\LoggingTranslatorPass;

class LoggingTranslatorPassTest extends TestCase
{
    public function testProcess()
    {
        $definition = $this->getMockBuilder('Symfony\Component\DependencyInjection\Definition')->getMock();
        $container = $this->getMockBuilder('Symfony\Component\DependencyInjection\ContainerBuilder')->getMock();
        $parameterBag = $this->getMockBuilder('Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface')->getMock();

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
            ->will($this->returnValue('Symfony\Bundle\FrameworkBundle\Translation\Translator'));

        $parameterBag->expects($this->once())
            ->method('resolveValue')
            ->will($this->returnValue("Symfony\Bundle\FrameworkBundle\Translation\Translator"));

        $container->expects($this->once())
            ->method('getParameterBag')
            ->will($this->returnValue($parameterBag));

        $container->expects($this->once())
            ->method('getReflectionClass')
            ->with('Symfony\Bundle\FrameworkBundle\Translation\Translator')
            ->will($this->returnValue(new \ReflectionClass('Symfony\Bundle\FrameworkBundle\Translation\Translator')));

        $pass = new LoggingTranslatorPass();
        $pass->process($container);
    }

    public function testThatCompilerPassIsIgnoredIfThereIsNotLoggerDefinition()
    {
        $container = $this->getMockBuilder('Symfony\Component\DependencyInjection\ContainerBuilder')->getMock();
        $container->expects($this->once())
            ->method('hasAlias')
            ->will($this->returnValue(false));

        $pass = new LoggingTranslatorPass();
        $pass->process($container);
    }

    public function testThatCompilerPassIsIgnoredIfThereIsNotTranslatorDefinition()
    {
        $container = $this->getMockBuilder('Symfony\Component\DependencyInjection\ContainerBuilder')->getMock();
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
