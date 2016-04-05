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
use Doctrine\Common\Annotations\CachedReader;
use Symfony\Component\Templating\EngineInterface as ComponentEngineInterface;
use Symfony\Bundle\FrameworkBundle\Templating\EngineInterface as FrameworkBundleEngineInterface;

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

    protected static function createKernel(array $options = array())
    {
        return parent::createKernel(array('test_case' => 'AutowiringTypes') + $options);
    }
}
