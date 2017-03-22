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
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Bundle\FrameworkBundle\DependencyInjection\Compiler\TranslatorPass;
use Symfony\Component\DependencyInjection\ServiceLocator;

class TranslatorPassTest extends TestCase
{
    public function testValidCollector()
    {
        $definition = $this->getMockBuilder('Symfony\Component\DependencyInjection\Definition')->getMock();
        $definition->expects($this->at(0))
            ->method('addMethodCall')
            ->with('addLoader', array('xliff', new Reference('xliff')));
        $definition->expects($this->at(1))
            ->method('addMethodCall')
            ->with('addLoader', array('xlf', new Reference('xliff')));

        $container = $this->getMockBuilder('Symfony\Component\DependencyInjection\ContainerBuilder')->setMethods(array('hasDefinition', 'getDefinition', 'findTaggedServiceIds', 'findDefinition'))->getMock();
        $container->expects($this->any())
            ->method('hasDefinition')
            ->will($this->returnValue(true));
        $container->expects($this->once())
            ->method('getDefinition')
            ->will($this->returnValue($definition));
        $container->expects($this->once())
            ->method('findTaggedServiceIds')
            ->will($this->returnValue(array('xliff' => array(array('alias' => 'xliff', 'legacy-alias' => 'xlf')))));
        $container->expects($this->once())
            ->method('findDefinition')
            ->will($this->returnValue($translator = $this->getMockBuilder('Symfony\Component\DependencyInjection\Definition')->getMock()));
        $translator->expects($this->at(0))
            ->method('replaceArgument')
            ->with(0, $this->equalTo((new Definition(ServiceLocator::class, array(array('xliff' => new Reference('xliff')))))->addTag('container.service_locator')))
            ->willReturn($translator);
        $translator->expects($this->at(1))
            ->method('replaceArgument')
            ->with(3, array('xliff' => array('xliff', 'xlf')))
            ->willReturn($translator);
        $pass = new TranslatorPass();
        $pass->process($container);
    }
}
