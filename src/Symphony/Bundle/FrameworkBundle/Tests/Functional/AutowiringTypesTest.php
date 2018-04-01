<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Bundle\FrameworkBundle\Tests\Functional;

use Doctrine\Common\Annotations\AnnotationReader;
use Doctrine\Common\Annotations\CachedReader;
use Symphony\Component\Cache\Adapter\FilesystemAdapter;
use Symphony\Component\EventDispatcher\EventDispatcher;
use Symphony\Component\Templating\EngineInterface as ComponentEngineInterface;
use Symphony\Component\HttpKernel\Debug\TraceableEventDispatcher;
use Symphony\Bundle\FrameworkBundle\Templating\EngineInterface as FrameworkBundleEngineInterface;

class AutowiringTypesTest extends WebTestCase
{
    public function testAnnotationReaderAutowiring()
    {
        static::bootKernel(array('root_config' => 'no_annotations_cache.yml', 'environment' => 'no_annotations_cache'));
        $container = static::$kernel->getContainer();

        $annotationReader = $container->get('test.autowiring_types.autowired_services')->getAnnotationReader();
        $this->assertInstanceOf(AnnotationReader::class, $annotationReader);
    }

    public function testCachedAnnotationReaderAutowiring()
    {
        static::bootKernel();
        $container = static::$kernel->getContainer();

        $annotationReader = $container->get('test.autowiring_types.autowired_services')->getAnnotationReader();
        $this->assertInstanceOf(CachedReader::class, $annotationReader);
    }

    public function testTemplatingAutowiring()
    {
        static::bootKernel();
        $container = static::$kernel->getContainer();

        $autowiredServices = $container->get('test.autowiring_types.autowired_services');
        $this->assertInstanceOf(FrameworkBundleEngineInterface::class, $autowiredServices->getFrameworkBundleEngine());
        $this->assertInstanceOf(ComponentEngineInterface::class, $autowiredServices->getEngine());
    }

    public function testEventDispatcherAutowiring()
    {
        static::bootKernel(array('debug' => false));
        $container = static::$kernel->getContainer();

        $autowiredServices = $container->get('test.autowiring_types.autowired_services');
        $this->assertInstanceOf(EventDispatcher::class, $autowiredServices->getDispatcher(), 'The event_dispatcher service should be injected if the debug is not enabled');

        static::bootKernel(array('debug' => true));
        $container = static::$kernel->getContainer();

        $autowiredServices = $container->get('test.autowiring_types.autowired_services');
        $this->assertInstanceOf(TraceableEventDispatcher::class, $autowiredServices->getDispatcher(), 'The debug.event_dispatcher service should be injected if the debug is enabled');
    }

    public function testCacheAutowiring()
    {
        static::bootKernel();
        $container = static::$kernel->getContainer();

        $autowiredServices = $container->get('test.autowiring_types.autowired_services');
        $this->assertInstanceOf(FilesystemAdapter::class, $autowiredServices->getCachePool());
    }

    protected static function createKernel(array $options = array())
    {
        return parent::createKernel(array('test_case' => 'AutowiringTypes') + $options);
    }
}
