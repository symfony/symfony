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

use Doctrine\ORM\EntityManager;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;
use Symfony\Bundle\DoctrineBundle\DependencyInjection\DoctrineExtension;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;

class TestCase extends \PHPUnit_Framework_TestCase
{
    protected function setUp()
    {
        if (!class_exists('Doctrine\\Common\\Version')) {
            $this->markTestSkipped('Doctrine is not available.');
        }
    }

    /**
     * @return EntityManager
     */
    protected function createTestEntityManager()
    {
        $config = new \Doctrine\ORM\Configuration();
        $config->setAutoGenerateProxyClasses(true);
        $config->setProxyDir(\sys_get_temp_dir());
        $config->setProxyNamespace('SymfonyTests\Doctrine');
        $config->setMetadataDriverImpl($config->newDefaultAnnotationDriver());
        $config->setQueryCacheImpl(new \Doctrine\Common\Cache\ArrayCache());
        $config->setMetadataCacheImpl(new \Doctrine\Common\Cache\ArrayCache());

        $params = array(
            'driver' => 'pdo_sqlite',
            'memory' => true,
        );

        return EntityManager::create($params, $config);
    }

    public function createYamlBundleTestContainer()
    {
        $container = new ContainerBuilder(new ParameterBag(array(
            'kernel.bundles'     => array('YamlBundle' => 'DoctrineBundle\Tests\DependencyInjection\Fixtures\Bundles\YamlBundle\YamlBundle'),
            'kernel.cache_dir'   => sys_get_temp_dir(),
            'kernel.root_dir'    => __DIR__ . "/../../../../" // src dir
        )));
        $loader = new DoctrineExtension();
        $container->registerExtension($loader);
        $loader->dbalLoad(array(array(
            'connections' => array(
                'default' => array(
                    'driver' => 'pdo_mysql',
                    'charset' => 'UTF-8',
                    'platform-service' => 'my.platform',
                )
            )
        )), $container);
        $loader->ormLoad(array(
            array(
                'mappings' => array('YamlBundle' => array(
                    'type' => 'yml',
                    'dir' => __DIR__ . "/DependencyInjection/Fixtures/Bundles/YamlBundle/Resources/config/doctrine/metadata/orm",
                    'prefix' => 'DoctrineBundle\Tests\DependencyInjection\Fixtures\Bundles\YamlBundle',
            )))), $container);

        $container->setDefinition('my.platform', new \Symfony\Component\DependencyInjection\Definition('Doctrine\DBAL\Platforms\MySqlPlatform'));

        return $container;
    }
}
