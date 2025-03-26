<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Doctrine\Tests\Security\RememberMe;

use Doctrine\DBAL\Configuration;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Schema\DefaultSchemaManagerFactory;
use Doctrine\ORM\ORMSetup;
use Symfony\Bridge\Doctrine\Security\RememberMe\DoctrineTokenProvider;

/**
 * @requires extension pdo_pgsql
 *
 * @group integration
 */
class DoctrineTokenProviderPostgresTest extends DoctrineTokenProviderTest
{
    public static function setUpBeforeClass(): void
    {
        if (!getenv('POSTGRES_HOST')) {
            self::markTestSkipped('Missing POSTGRES_HOST env variable');
        }
    }

    protected function bootstrapProvider(): DoctrineTokenProvider
    {
        $config = class_exists(ORMSetup::class) ? ORMSetup::createConfiguration(true) : new Configuration();
        if (class_exists(DefaultSchemaManagerFactory::class)) {
            $config->setSchemaManagerFactory(new DefaultSchemaManagerFactory());
        }

        $connection = DriverManager::getConnection([
            'driver' => 'pdo_pgsql',
            'host' => getenv('POSTGRES_HOST'),
            'user' => 'postgres',
            'password' => 'password',
        ], $config);
        $connection->{method_exists($connection, 'executeStatement') ? 'executeStatement' : 'executeUpdate'}(<<<'SQL'
            DROP TABLE IF EXISTS rememberme_token;
SQL
        );

        $connection->{method_exists($connection, 'executeStatement') ? 'executeStatement' : 'executeUpdate'}(<<<'SQL'
            CREATE TABLE rememberme_token (
                series   CHAR(88)     UNIQUE PRIMARY KEY NOT NULL,
                value    VARCHAR(88)  NOT NULL, -- CHAR(88) adds spaces at the end
                lastUsed TIMESTAMP    NOT NULL,
                class    VARCHAR(100) NOT NULL,
                username VARCHAR(200) NOT NULL
            );
SQL
        );

        return new DoctrineTokenProvider($connection);
    }
}
