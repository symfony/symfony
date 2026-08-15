<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Doctrine\Tests\DependencyInjection\CompilerPass;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Tools\DsnParser;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Mapping\DefaultTypedFieldMapper;
use Doctrine\ORM\ORMSetup;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;
use Symfony\Bridge\Doctrine\DependencyInjection\CompilerPass\RegisterDatePointTypePass;
use Symfony\Bridge\Doctrine\Tests\Fixtures\DatePointEntity;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;

#[RequiresPhpExtension('pdo_sqlite')]
class DatePointMappingTest extends TestCase
{
    private ?Connection $driverConnection = null;

    protected function tearDown(): void
    {
        if ($this->driverConnection?->isConnected()) {
            $this->driverConnection->close();
        }
    }

    public function testDatePointTypeMapper()
    {
        if (method_exists(ORMSetup::class, 'createAttributeMetadataConfig')) {
            $config = ORMSetup::createAttributeMetadataConfig(paths: [__DIR__], isDevMode: true);
        } else {
            $config = ORMSetup::createAttributeMetadataConfiguration(paths: [__DIR__], isDevMode: true);
        }
        $config->enableNativeLazyObjects(true);

        $container = new ContainerBuilder();
        $container->setParameter('doctrine.dbal.connection_factory.types', []);

        $definition = new Definition();
        $container->setDefinition('doctrine.orm.default_configuration', $definition);

        (new RegisterDatePointTypePass())->process($container);

        $calls = $container
            ->getDefinition('doctrine.orm.default_configuration')
            ->getMethodCalls();

        foreach ($calls as [$method, $args]) {
            if ('setTypedFieldMapper' === $method) {
                $mapperDef = $args[0];

                $mapper = new DefaultTypedFieldMapper($mapperDef->getArguments()[0]);

                $config->setTypedFieldMapper($mapper);
            }
        }

        $dsn = 'pdo-sqlite://:memory:';
        $params = (new DsnParser())->parse($dsn);
        $this->driverConnection = DriverManager::getConnection($params, $config);

        $em = new EntityManager($this->driverConnection, $config);

        $metadata = $em->getClassMetadata(DatePointEntity::class);

        $this->assertTrue($metadata->hasField('activityDate'));

        $fieldMapping = $metadata->activityDate ?? $metadata->getFieldMapping('activityDate');

        $this->assertSame('date_point', $fieldMapping->type ?? $fieldMapping['type']);
    }
}
