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

use Symfony\Component\DependencyInjection\Alias;
use Symfony\Bundle\FrameworkBundle\DependencyInjection\Compiler\ServiceReplacementsPass;

class ServiceReplacementsPassTest extends \PHPUnit_Framework_TestCase
{
    public function testReplacemntOfAlias()
    {
        $service = array(
            'my_replacemnt.service' => array(0 => array('replaces' => 'service.alias')),
        );

        $alias = new Alias('service.alias', false);
        $container = $this->getMock('Symfony\Component\DependencyInjection\ContainerBuilder');

        $container->expects($this->once())
            ->method('findTaggedServiceIds')
            ->with('framework.service_replacer')
            ->will($this->returnValue($service));
        $container->expects($this->once())
            ->method('hasAlias')
            ->with('service.alias')
            ->will($this->returnValue(true));
        $container->expects($this->at(2))
            ->method('setAlias')
            ->with(
                'service.alias.orig', $alias
            );
        $container->expects($this->at(3))
            ->method('setAlias')
            ->with(
                'service.alias', 'my_replacemnt.service'
            );

        $pass = new ServiceReplacementsPass();
        $pass->process($container);
    }

    public function testReplacemntOfAliasWithRename()
    {
        $service = array(
            'my_replacemnt.service' => array(0 => array(
                'replaces' => 'service.alias',
                'renameTo' => 'old.service.alias',
            )),
        );

        $alias = new Alias('service.alias', false);
        $container = $this->getMock('Symfony\Component\DependencyInjection\ContainerBuilder');

        $container->expects($this->once())
            ->method('findTaggedServiceIds')
            ->with('framework.service_replacer')
            ->will($this->returnValue($service));
        $container->expects($this->once())
            ->method('hasAlias')
            ->with('service.alias')
            ->will($this->returnValue(true));
        $container->expects($this->at(2))
            ->method('setAlias')
            ->with(
                'old.service.alias', $alias
            );
        $container->expects($this->at(3))
            ->method('setAlias')
            ->with(
                'service.alias', 'my_replacemnt.service'
            );

        $pass = new ServiceReplacementsPass();
        $pass->process($container);
    }

    public function testReplacemntOfService()
    {
        $service = array(
            'my_replacemnt.service' => array(0 => array('replaces' => 'service.to.replace')),
        );

        $container  = $this->getMock('Symfony\Component\DependencyInjection\ContainerBuilder');
        $definition = $this->getMock('Symfony\Component\DependencyInjection\Definition', array(
            'clearTags', 'setPublic'
        ), array('service.to.replace'));

        $container->expects($this->once())
            ->method('findTaggedServiceIds')
            ->with('framework.service_replacer')
            ->will($this->returnValue($service));
        $container->expects($this->once())
            ->method('hasAlias')
            ->with('service.to.replace')
            ->will($this->returnValue(false));
        $container->expects($this->once())
            ->method('getDefinition')
            ->with('service.to.replace')
            ->will($this->returnValue($definition));
        $container->expects($this->once())
            ->method('setDefinition')
            ->with(
                'service.to.replace.orig', $definition
            );
        $container->expects($this->once())
            ->method('setAlias')
            ->with(
                'service.to.replace', 'my_replacemnt.service'
            );

        $definition->expects($this->once())
            ->method('setPublic')
            ->with(false);
        $definition->expects($this->once())
            ->method('clearTags');

        $pass = new ServiceReplacementsPass();
        $pass->process($container);
    }

    public function testReplacemntOfServiceWithRename()
    {
        $service = array(
            'my_replacemnt.service' => array(0 => array(
                'replaces' => 'service.to.replace',
                'renameTo' => 'old.service.to.replace'
            )),
        );

        $container  = $this->getMock('Symfony\Component\DependencyInjection\ContainerBuilder');
        $definition = $this->getMock('Symfony\Component\DependencyInjection\Definition', array(
            'clearTags', 'setPublic'
        ), array('service.to.replace'));

        $container->expects($this->once())
            ->method('findTaggedServiceIds')
            ->with('framework.service_replacer')
            ->will($this->returnValue($service));
        $container->expects($this->once())
            ->method('hasAlias')
            ->with('service.to.replace')
            ->will($this->returnValue(false));
        $container->expects($this->once())
            ->method('getDefinition')
            ->with('service.to.replace')
            ->will($this->returnValue($definition));
        $container->expects($this->once())
            ->method('setDefinition')
            ->with(
                'old.service.to.replace', $definition
            );
        $container->expects($this->once())
            ->method('setAlias')
            ->with(
                'service.to.replace', 'my_replacemnt.service'
            );

        $definition->expects($this->once())
            ->method('setPublic')
            ->with(false);
        $definition->expects($this->once())
            ->method('clearTags');

        $pass = new ServiceReplacementsPass();
        $pass->process($container);
    }
}
