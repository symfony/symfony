<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Tests\Functional;

use Doctrine\Common\Annotations\AnnotationReader;
use Doctrine\Common\Annotations\PsrCachedReader;
use Symfony\Bridge\PhpUnit\ExpectDeprecationTrait;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpKernel\Debug\TraceableEventDispatcher;
use Symfony\Component\HttpKernel\KernelInterface;

class AutowiringTypesTest extends AbstractWebTestCase
{
    use ExpectDeprecationTrait;

    /**
     * @group legacy
     */
    public function testAnnotationReaderAutowiring()
    {
        $this->expectDeprecation('Since symfony/framework-bundle 6.4: Enabling the integration of Doctrine annotations is deprecated. Set the "framework.annotations.enabled" config option to false.');

        static::bootKernel(['root_config' => 'no_annotations_cache.yml', 'environment' => 'no_annotations_cache']);

        $annotationReader = self::getContainer()->get('test.autowiring_types.autowired_services')->getAnnotationReader();
        $this->assertInstanceOf(AnnotationReader::class, $annotationReader);
    }

    /**
     * @group legacy
     */
    public function testCachedAnnotationReaderAutowiring()
    {
        $this->expectDeprecation('Since symfony/framework-bundle 6.4: Enabling the integration of Doctrine annotations is deprecated. Set the "framework.annotations.enabled" config option to false.');

        static::bootKernel(['root_config' => 'with_annotations.yml', 'environment' => 'with_annotations']);

        $annotationReader = self::getContainer()->get('test.autowiring_types.autowired_services')->getAnnotationReader();
        $this->assertInstanceOf(PsrCachedReader::class, $annotationReader);
    }

    public function testEventDispatcherAutowiring()
    {
        static::bootKernel(['debug' => false]);

        $autowiredServices = self::getContainer()->get('test.autowiring_types.autowired_services');
        $this->assertInstanceOf(EventDispatcher::class, $autowiredServices->getDispatcher(), 'The event_dispatcher service should be injected if the debug is not enabled');

        static::bootKernel(['debug' => true]);

        $autowiredServices = self::getContainer()->get('test.autowiring_types.autowired_services');
        $this->assertInstanceOf(TraceableEventDispatcher::class, $autowiredServices->getDispatcher(), 'The debug.event_dispatcher service should be injected if the debug is enabled');
    }

    public function testCacheAutowiring()
    {
        static::bootKernel();

        $autowiredServices = self::getContainer()->get('test.autowiring_types.autowired_services');
        $this->assertInstanceOf(FilesystemAdapter::class, $autowiredServices->getCachePool());
    }

    protected static function createKernel(array $options = []): KernelInterface
    {
        return parent::createKernel(['test_case' => 'AutowiringTypes'] + $options);
    }
}
