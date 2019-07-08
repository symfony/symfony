<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Doctrine\Test;

use Doctrine\Common\Annotations\AnnotationReader;
use Doctrine\Common\Cache\ArrayCache;
use Doctrine\Common\Persistence\Mapping\Driver\MappingDriverChain;
use Doctrine\Common\Persistence\Mapping\Driver\SymfonyFileLocator;
use Doctrine\ORM\Configuration;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Mapping\Driver\AnnotationDriver;
use Doctrine\ORM\Mapping\Driver\XmlDriver;
use PHPUnit\Framework\TestCase;

/**
 * Provides utility functions needed in tests.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class DoctrineTestHelper
{
    /**
     * Returns an entity manager for testing.
     *
     * @param Configuration|null $config
     *
     * @return EntityManager
     */
    public static function createTestEntityManager(Configuration $config = null)
    {
        if (!\extension_loaded('pdo_sqlite')) {
            TestCase::markTestSkipped('Extension pdo_sqlite is required.');
        }

        if (null === $config) {
            $config = self::createTestConfiguration();
        }

        $params = [
            'driver' => 'pdo_sqlite',
            'memory' => true,
        ];

        return EntityManager::create($params, $config);
    }

    /**
     * @return Configuration
     */
    public static function createTestConfiguration()
    {
        $config = new Configuration();
        $config->setEntityNamespaces(['SymfonyTestsDoctrine' => 'Symfony\Bridge\Doctrine\Tests\Fixtures']);
        $config->setAutoGenerateProxyClasses(true);
        $config->setProxyDir(sys_get_temp_dir());
        $config->setProxyNamespace('SymfonyTests\Doctrine');
        $config->setMetadataDriverImpl(new AnnotationDriver(new AnnotationReader()));
        $config->setQueryCacheImpl(new ArrayCache());
        $config->setMetadataCacheImpl(new ArrayCache());

        return $config;
    }

    /**
     * @return Configuration
     */
    public static function createTestConfigurationWithXmlLoader()
    {
        $config = static::createTestConfiguration();

        $driverChain = new MappingDriverChain();
        $driverChain->addDriver(
            new XmlDriver(
                new SymfonyFileLocator(
                    [__DIR__.'/../Tests/Resources/orm' => 'Symfony\\Bridge\\Doctrine\\Tests\\Fixtures'], '.orm.xml'
                )
            ),
            'Symfony\\Bridge\\Doctrine\\Tests\\Fixtures'
        );

        $config->setMetadataDriverImpl($driverChain);

        return $config;
    }

    /**
     * This class cannot be instantiated.
     */
    private function __construct()
    {
    }
}
