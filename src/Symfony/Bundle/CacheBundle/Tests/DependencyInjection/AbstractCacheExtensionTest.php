<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\CacheBundle\Tests\DependencyInjection;

use Symfony\Bundle\CacheBundle\DependencyInjection\CacheExtension;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;
use Symfony\Bundle\CacheBundle\CacheBundle;

abstract class AbstractCacheExtensionTest extends \PHPUnit_Framework_TestCase
{
    abstract protected function loadFromFile(ContainerBuilder $container, $file);

//    public function testCsrfProtection()
//    {
//        $container = $this->createContainerFromFile('full');
//
//        $def = $container->getDefinition('form.type_extension.csrf');
//
//        $this->assertTrue($container->getParameter('form.type_extension.csrf.enabled'));
//        $this->assertEquals('%form.type_extension.csrf.enabled%', $def->getArgument(0));
//        $this->assertEquals('_csrf', $container->getParameter('form.type_extension.csrf.field_name'));
//        $this->assertEquals('%form.type_extension.csrf.field_name%', $def->getArgument(1));
//        $this->assertEquals('s3cr3t', $container->getParameterBag()->resolveValue($container->findDefinition('form.csrf_provider')->getArgument(1)));
//    }

    public function testRootOptions()
    {
        $container = $this->createContainerFromFile('full');

        $this->assertEquals($container->getParameter('kernel.debug'), $container->getParameter('cache.debug'));
    }

    public function testOnlyOneBackedIsAllowedPerKey()
    {
        $this->markTestIncomplete('only one be allowed per key');
    }

    public function testAddABackendsFromABundle()
    {
        $this->markTestIncomplete();
    }

    // todo move the test below to backend specific files, create a container for each

    public function testMemcachedSingleServer()
    {
        $container = $this->createContainerFromFile('full');

        $definition = $container->getDefinition('cache.backend.concrete.memcached_be');

        $this->assertEquals('Memcached', $definition->getClass());
        $this->assertTrue($definition->isPublic());
        $calls = $definition->getMethodCalls();
        $this->assertEquals(array(array('addServer' , array('localhost', '11211', '0'))), $calls);
    }

    public function testMemcachedMultipleServers()
    {
        $this->markTestIncomplete();
    }

    public function testMemcachedDefaults()
    {
        $this->markTestIncomplete('todo pending config fixes');
    }

    public function testMemcachedMerge()
    {
        $this->markTestIncomplete('todo merging when same config');
    }

    protected function createContainer()
    {
        return new ContainerBuilder(new ParameterBag(array(
            'kernel.bundles'          => array('CacheBundle' => 'Symfony\\Bundle\\CacheBundle\\CacheBundle'),
            'kernel.cache_dir'        => __DIR__,
            'kernel.compiled_classes' => array(),
            'kernel.debug'            => true,
        )));
    }

    protected function createContainerFromFile($file, $data = array())
    {
        $container = $this->createContainer($data);
        $container->registerExtension(new CacheExtension());
        $this->loadFromFile($container, $file);

        $bundle = new CacheBundle();
        $bundle->build($container);

        $container->compile();

        return $container;
    }
}
