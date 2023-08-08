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
use Doctrine\Common\EventManager;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Schema\DefaultSchemaManagerFactory;
use Doctrine\ORM\Configuration;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Mapping\Driver\AnnotationDriver;
use Doctrine\ORM\Mapping\Driver\AttributeDriver;
use Doctrine\ORM\Mapping\Driver\XmlDriver;
use Doctrine\ORM\ORMSetup;
use Doctrine\Persistence\Mapping\Driver\MappingDriverChain;
use Doctrine\Persistence\Mapping\Driver\SymfonyFileLocator;
use PHPUnit\Framework\TestCase;
use Symfony\Component\VarExporter\LazyGhostTrait;

/**
 * Provides utility functions needed in tests.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 *
 * @deprecated since Symfony 5.3
 */
class DoctrineTestHelper
{
    /**
     * Returns an entity manager for testing.
     *
     * @return EntityManager
     */
    public static function createTestEntityManager(Configuration $config = null)
    {
        if (!\extension_loaded('pdo_sqlite')) {
            TestCase::markTestSkipped('Extension pdo_sqlite is required.');
        }

        if (__CLASS__ === static::class) {
            trigger_deprecation('symfony/doctrine-bridge', '5.3', '"%s" is deprecated and will be removed in 6.0.', __CLASS__);
        }

        if (null === $config) {
            $config = self::createTestConfiguration();
        }

        $params = [
            'driver' => 'pdo_sqlite',
            'memory' => true,
        ];

        if (!(new \ReflectionMethod(EntityManager::class, '__construct'))->isPublic()) {
            return EntityManager::create($params, $config);
        }

        $eventManager = new EventManager();

        return new EntityManager(DriverManager::getConnection($params, $config, $eventManager), $config, $eventManager);
    }

    /**
     * @return Configuration
     */
    public static function createTestConfiguration()
    {
        if (__CLASS__ === static::class) {
            trigger_deprecation('symfony/doctrine-bridge', '5.3', '"%s" is deprecated and will be removed in 6.0.', __CLASS__);
        }

        $config = class_exists(ORMSetup::class) ? ORMSetup::createConfiguration(true) : new Configuration();
        $config->setEntityNamespaces(['SymfonyTestsDoctrine' => 'Symfony\Bridge\Doctrine\Tests\Fixtures']);
        $config->setAutoGenerateProxyClasses(true);
        $config->setProxyDir(sys_get_temp_dir());
        $config->setProxyNamespace('SymfonyTests\Doctrine');
        if (\PHP_VERSION_ID >= 80000 && class_exists(AttributeDriver::class)) {
            $config->setMetadataDriverImpl(new AttributeDriver([__DIR__.'/../Tests/Fixtures' => 'Symfony\Bridge\Doctrine\Tests\Fixtures'], true));
        } else {
            $config->setMetadataDriverImpl(new AnnotationDriver(new AnnotationReader(), null, true));
        }
        if (class_exists(DefaultSchemaManagerFactory::class)) {
            $config->setSchemaManagerFactory(new DefaultSchemaManagerFactory());
        }

        if (\PHP_VERSION_ID >= 80100 && method_exists(Configuration::class, 'setLazyGhostObjectEnabled') && trait_exists(LazyGhostTrait::class)) {
            $config->setLazyGhostObjectEnabled(true);
        }

        return $config;
    }

    /**
     * @return Configuration
     */
    public static function createTestConfigurationWithXmlLoader()
    {
        if (__CLASS__ === static::class) {
            trigger_deprecation('symfony/doctrine-bridge', '5.3', '"%s" is deprecated and will be removed in 6.0.', __CLASS__);
        }

        $config = static::createTestConfiguration();

        $driverChain = new MappingDriverChain();
        $driverChain->addDriver(
            new XmlDriver(
                new SymfonyFileLocator(
                    [__DIR__.'/../Tests/Resources/orm' => 'Symfony\\Bridge\\Doctrine\\Tests\\Fixtures'], '.orm.xml'
                ),
                '.orm.xml',
                true
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
