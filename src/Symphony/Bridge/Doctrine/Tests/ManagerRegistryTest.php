<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Bridge\Doctrine\Tests;

use PHPUnit\Framework\TestCase;
use Symphony\Bridge\Doctrine\ManagerRegistry;
use Symphony\Bridge\ProxyManager\Tests\LazyProxy\Dumper\PhpDumperTest;

class ManagerRegistryTest extends TestCase
{
    public static function setUpBeforeClass()
    {
        if (!class_exists('PHPUnit_Framework_TestCase')) {
            self::markTestSkipped('proxy-manager-bridge is not yet compatible with namespaced phpunit versions.');
        }
        $test = new PhpDumperTest();
        $test->testDumpContainerWithProxyServiceWillShareProxies();
    }

    public function testResetService()
    {
        $container = new \LazyServiceProjectServiceContainer();

        $registry = new TestManagerRegistry('name', array(), array('defaultManager' => 'foo'), 'defaultConnection', 'defaultManager', 'proxyInterfaceName');
        $registry->setTestContainer($container);

        $foo = $container->get('foo');
        $foo->bar = 123;
        $this->assertTrue(isset($foo->bar));

        $registry->resetManager();

        $this->assertSame($foo, $container->get('foo'));
        $this->assertObjectNotHasAttribute('bar', $foo);
    }
}

class TestManagerRegistry extends ManagerRegistry
{
    public function setTestContainer($container)
    {
        $this->container = $container;
    }

    public function getAliasNamespace($alias)
    {
        return 'Foo';
    }
}
