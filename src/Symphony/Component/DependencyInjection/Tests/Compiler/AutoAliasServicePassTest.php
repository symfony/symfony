<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\DependencyInjection\Tests\Compiler;

use PHPUnit\Framework\TestCase;
use Symphony\Component\DependencyInjection\Compiler\AutoAliasServicePass;
use Symphony\Component\DependencyInjection\ContainerBuilder;

class AutoAliasServicePassTest extends TestCase
{
    /**
     * @expectedException \Symphony\Component\DependencyInjection\Exception\ParameterNotFoundException
     */
    public function testProcessWithMissingParameter()
    {
        $container = new ContainerBuilder();

        $container->register('example')
            ->addTag('auto_alias', array('format' => '%non_existing%.example'));

        $pass = new AutoAliasServicePass();
        $pass->process($container);
    }

    /**
     * @expectedException \Symphony\Component\DependencyInjection\Exception\InvalidArgumentException
     */
    public function testProcessWithMissingFormat()
    {
        $container = new ContainerBuilder();

        $container->register('example')
            ->addTag('auto_alias', array());
        $container->setParameter('existing', 'mysql');

        $pass = new AutoAliasServicePass();
        $pass->process($container);
    }

    public function testProcessWithNonExistingAlias()
    {
        $container = new ContainerBuilder();

        $container->register('example', 'Symphony\Component\DependencyInjection\Tests\Compiler\ServiceClassDefault')
            ->addTag('auto_alias', array('format' => '%existing%.example'));
        $container->setParameter('existing', 'mysql');

        $pass = new AutoAliasServicePass();
        $pass->process($container);

        $this->assertEquals('Symphony\Component\DependencyInjection\Tests\Compiler\ServiceClassDefault', $container->getDefinition('example')->getClass());
    }

    public function testProcessWithExistingAlias()
    {
        $container = new ContainerBuilder();

        $container->register('example', 'Symphony\Component\DependencyInjection\Tests\Compiler\ServiceClassDefault')
            ->addTag('auto_alias', array('format' => '%existing%.example'));

        $container->register('mysql.example', 'Symphony\Component\DependencyInjection\Tests\Compiler\ServiceClassMysql');
        $container->setParameter('existing', 'mysql');

        $pass = new AutoAliasServicePass();
        $pass->process($container);

        $this->assertTrue($container->hasAlias('example'));
        $this->assertEquals('mysql.example', $container->getAlias('example'));
        $this->assertSame('Symphony\Component\DependencyInjection\Tests\Compiler\ServiceClassMysql', $container->getDefinition('mysql.example')->getClass());
    }

    public function testProcessWithManualAlias()
    {
        $container = new ContainerBuilder();

        $container->register('example', 'Symphony\Component\DependencyInjection\Tests\Compiler\ServiceClassDefault')
            ->addTag('auto_alias', array('format' => '%existing%.example'));

        $container->register('mysql.example', 'Symphony\Component\DependencyInjection\Tests\Compiler\ServiceClassMysql');
        $container->register('mariadb.example', 'Symphony\Component\DependencyInjection\Tests\Compiler\ServiceClassMariaDb');
        $container->setAlias('example', 'mariadb.example');
        $container->setParameter('existing', 'mysql');

        $pass = new AutoAliasServicePass();
        $pass->process($container);

        $this->assertTrue($container->hasAlias('example'));
        $this->assertEquals('mariadb.example', $container->getAlias('example'));
        $this->assertSame('Symphony\Component\DependencyInjection\Tests\Compiler\ServiceClassMariaDb', $container->getDefinition('mariadb.example')->getClass());
    }
}

class ServiceClassDefault
{
}

class ServiceClassMysql extends ServiceClassDefault
{
}

class ServiceClassMariaDb extends ServiceClassMysql
{
}
