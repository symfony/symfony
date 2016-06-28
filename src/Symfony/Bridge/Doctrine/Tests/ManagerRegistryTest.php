<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Doctrine\Tests;

use Symfony\Bridge\Doctrine\ManagerRegistry;
use Symfony\Bridge\ProxyManager\Tests\LazyProxy\Dumper\PhpDumperTest;

class ManagerRegistryTest extends \PHPUnit_Framework_TestCase
{
    public static function setUpBeforeClass()
    {
        $test = new PhpDumperTest();
        $test->testDumpContainerWithProxyServiceWillShareProxies();
    }

    public function testResetService()
    {
        $container = new \LazyServiceProjectServiceContainer();

        $registry = new TestManagerRegistry('name', array(), array('defaultManager' => 'foo'), 'defaultConnection', 'defaultManager', 'proxyInterfaceName');
        $registry->setContainer($container);

        $foo = $container->get('foo');
        $foo->bar = 123;
        $this->assertTrue(isset($foo->bar));

        $registry->resetManager();

        $this->assertSame($foo, $container->get('foo'));
        $this->assertFalse(isset($foo->bar));
    }
}

class TestManagerRegistry extends ManagerRegistry
{
    public function getAliasNamespace($alias)
    {
        return 'Foo';
    }
}
