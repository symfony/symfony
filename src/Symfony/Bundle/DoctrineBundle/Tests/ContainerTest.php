<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\DoctrineBundle\Tests;

use Symfony\Component\DependencyInjection\Dumper\PhpDumper;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;
use Symfony\Bundle\DoctrineBundle\DependencyInjection\DoctrineExtension;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;

class ContainerTest extends TestCase
{
    public function getContainer()
    {
        $container = new ContainerBuilder(new ParameterBag(array(
            'kernel.bundle_dirs' => array(
                'DoctrineBundle\Tests\DependencyInjection\Fixtures\Bundles' =>
                __DIR__ . "/DependencyInjection/Fixtures/Bundles"
            ),
            'kernel.bundles'     => array(
                'DoctrineBundle\Tests\DependencyInjection\Fixtures\Bundles\YamlBundle\YamlBundle',
            ),
            'kernel.cache_dir'   => sys_get_temp_dir(),
        )));
        $loader = new DoctrineExtension();
        $container->registerExtension($loader);
        $loader->dbalLoad(array(
            'connections' => array(
                'default' => array(
                    'driver' => 'pdo_mysql',
                    'charset' => 'UTF-8',
                    'platform-service' => 'my.platform',
                )
            )
        ), $container);
        $loader->ormLoad(array('bundles' => array('YamlBundle' => array())), $container);

        $container->setDefinition('my.platform', new \Symfony\Component\DependencyInjection\Definition('Doctrine\DBAL\Platforms\MySqlPlatform'));

        $dumper = new PhpDumper($container);
        $code = $dumper->dump(array('class' => 'DoctrineBundleTestsProjectServiceContainer'));
        eval(str_replace('<?php', null, $code));
        return new \DoctrineBundleTestsProjectServiceContainer;
    }

    public function testContainer()
    {
        $container = $this->getContainer();
        $this->assertInstanceOf('Doctrine\DBAL\Logging\DebugStack', $container->get('doctrine.dbal.logger.debug'));
        $this->assertInstanceOf('Doctrine\DBAL\Logging\DebugStack', $container->get('doctrine.dbal.logger'));
        $this->assertInstanceOf('Symfony\Bundle\DoctrineBundle\DataCollector\DoctrineDataCollector', $container->get('doctrine.data_collector'));
        $this->assertInstanceOf('Doctrine\DBAL\Configuration', $container->get('doctrine.dbal.default_connection.configuration'));
        $this->assertInstanceOf('Doctrine\Common\EventManager', $container->get('doctrine.dbal.default_connection.event_manager'));
        $this->assertInstanceOf('Doctrine\DBAL\Connection', $container->get('doctrine.dbal.default_connection'));
        $this->assertInstanceOf('Doctrine\Common\Annotations\AnnotationReader', $container->get('doctrine.orm.metadata.annotation_reader'));
        $this->assertInstanceOf('Doctrine\ORM\Configuration', $container->get('doctrine.orm.default_configuration'));
        $this->assertInstanceOf('Doctrine\ORM\Mapping\Driver\DriverChain', $container->get('doctrine.orm.default_metadata_driver'));
        $this->assertInstanceOf('Doctrine\Common\Cache\ArrayCache', $container->get('doctrine.orm.default_metadata_cache'));
        $this->assertInstanceOf('Doctrine\Common\Cache\ArrayCache', $container->get('doctrine.orm.default_query_cache'));
        $this->assertInstanceOf('Doctrine\Common\Cache\ArrayCache', $container->get('doctrine.orm.default_result_cache'));
        $this->assertInstanceOf('Doctrine\ORM\EntityManager', $container->get('doctrine.orm.default_entity_manager'));
        $this->assertInstanceOf('Doctrine\DBAL\Connection', $container->get('database_connection'));
        $this->assertInstanceOf('Doctrine\ORM\EntityManager', $container->get('doctrine.orm.entity_manager'));
        $this->assertInstanceOf('Doctrine\Common\EventManager', $container->get('doctrine.orm.default_entity_manager.event_manager'));
        $this->assertInstanceOf('Doctrine\Common\EventManager', $container->get('doctrine.dbal.event_manager'));
        $this->assertInstanceOf('Doctrine\DBAL\Event\Listeners\MysqlSessionInit', $container->get('doctrine.dbal.default_connection.events.mysqlsessioninit'));

        $this->assertSame($container->get('my.platform'), $container->get('doctrine.dbal.default_connection')->getDatabasePlatform());
    }
}