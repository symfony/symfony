<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\DoctrineMongoDBBundle\Tests;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;
use Symfony\Bundle\DoctrineMongoDBBundle\DependencyInjection\DoctrineMongoDBExtension;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;

class ContainerTest extends TestCase
{
    public function getContainer()
    {
        require_once __DIR__.'/DependencyInjection/Fixtures/Bundles/YamlBundle/YamlBundle.php';

        $container = new ContainerBuilder(new ParameterBag(array(
            'kernel.bundles'     => array('YamlBundle' => 'DoctrineMongoDBBundle\Tests\DependencyInjection\Fixtures\Bundles\YamlBundle\YamlBundle'),
            'kernel.cache_dir'   => sys_get_temp_dir(),
            'kernel.debug'       => false,
        )));
        $loader = new DoctrineMongoDBExtension();
        $container->registerExtension($loader);

        $configs = array();
        $configs[] = array('connections' => array('default' => array()), 'document_managers' => array('default' => array('mappings' => array('YamlBundle' => array()))));
        $loader->load($configs, $container);

        return $container;
    }

    public function testContainer()
    {
        $container = $this->getContainer();
        $this->assertInstanceOf('Doctrine\ODM\MongoDB\Mapping\Driver\DriverChain', $container->get('doctrine.odm.mongodb.metadata.chain'));
        $this->assertInstanceOf('Doctrine\ODM\MongoDB\Mapping\Driver\AnnotationDriver', $container->get('doctrine.odm.mongodb.metadata.annotation'));
        $this->assertInstanceOf('Doctrine\Common\Annotations\AnnotationReader', $container->get('doctrine.odm.mongodb.metadata.annotation_reader'));
        $this->assertInstanceOf('Doctrine\ODM\MongoDB\Mapping\Driver\XmlDriver', $container->get('doctrine.odm.mongodb.metadata.xml'));
        $this->assertInstanceOf('Doctrine\ODM\MongoDB\Mapping\Driver\YamlDriver', $container->get('doctrine.odm.mongodb.metadata.yml'));
        $this->assertInstanceOf('Doctrine\Common\Cache\ArrayCache', $container->get('doctrine.odm.mongodb.cache.array'));
        $this->assertInstanceOf('Symfony\Bundle\DoctrineMongoDBBundle\Logger\DoctrineMongoDBLogger', $container->get('doctrine.odm.mongodb.logger'));
        $this->assertInstanceOf('Symfony\Bundle\DoctrineMongoDBBundle\DataCollector\DoctrineMongoDBDataCollector', $container->get('doctrine.odm.mongodb.data_collector'));
        $this->assertInstanceOf('Doctrine\MongoDB\Connection', $container->get('doctrine.odm.mongodb.default_connection'));
        $this->assertInstanceOf('Doctrine\ODM\MongoDB\Configuration', $container->get('doctrine.odm.mongodb.default_configuration'));
        $this->assertInstanceOf('Doctrine\ODM\MongoDB\Mapping\Driver\DriverChain', $container->get('doctrine.odm.mongodb.default_metadata_driver'));
        $this->assertInstanceOf('Doctrine\Common\Cache\ArrayCache', $container->get('doctrine.odm.mongodb.default_metadata_cache'));
        $this->assertInstanceOf('Doctrine\ODM\MongoDB\DocumentManager', $container->get('doctrine.odm.mongodb.default_document_manager'));
        $this->assertInstanceOf('Doctrine\Common\Cache\ArrayCache', $container->get('doctrine.odm.mongodb.cache'));
        $this->assertInstanceOf('Doctrine\ODM\MongoDB\DocumentManager', $container->get('doctrine.odm.mongodb.document_manager'));
        $this->assertInstanceof('Doctrine\Common\EventManager', $container->get('doctrine.odm.mongodb.event_manager'));
    }
}
