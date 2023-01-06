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

use Doctrine\Common\Annotations\AnnotationReader;
use Doctrine\ORM\Configuration;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Mapping\Driver\AnnotationDriver;
use Doctrine\ORM\Mapping\Driver\XmlDriver;
use Doctrine\Persistence\Mapping\Driver\MappingDriverChain;
use Doctrine\Persistence\Mapping\Driver\SymfonyFileLocator;
use PHPUnit\Framework\TestCase;

/**
 * Provides utility functions needed in tests.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
final class DoctrineTestHelper
{
    /**
     * Returns an entity manager for testing.
     */
    public static function createTestEntityManager(Configuration $config = null): EntityManager
    {
        if (!\extension_loaded('pdo_sqlite')) {
            TestCase::markTestSkipped('Extension pdo_sqlite is required.');
        }

        $params = [
            'driver' => 'pdo_sqlite',
            'memory' => true,
        ];

        return EntityManager::create($params, $config ?? self::createTestConfiguration());
    }

    public static function createTestConfiguration(): Configuration
    {
        $config = new Configuration();
        $config->setEntityNamespaces(['SymfonyTestsDoctrine' => 'Symfony\Bridge\Doctrine\Tests\Fixtures']);
        $config->setAutoGenerateProxyClasses(true);
        $config->setProxyDir(sys_get_temp_dir());
        $config->setProxyNamespace('SymfonyTests\Doctrine');
        $config->setMetadataDriverImpl(new AnnotationDriver(new AnnotationReader()));

        return $config;
    }

    public static function createTestConfigurationWithXmlLoader(): Configuration
    {
        $config = self::createTestConfiguration();

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
