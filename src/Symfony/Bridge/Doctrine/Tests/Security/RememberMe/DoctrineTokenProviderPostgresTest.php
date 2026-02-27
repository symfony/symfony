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

use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Schema\DefaultSchemaManagerFactory;
use Doctrine\ORM\ORMSetup;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use Symfony\Bridge\Doctrine\Security\RememberMe\DoctrineTokenProvider;

#[Group('integration')]
#[RequiresPhpExtension('pdo_pgsql')]
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
        $config = ORMSetup::createConfiguration(true);
        $config->setSchemaManagerFactory(new DefaultSchemaManagerFactory());
        $config->enableNativeLazyObjects(true);

        $connection = DriverManager::getConnection([
            'driver' => 'pdo_pgsql',
            'host' => getenv('POSTGRES_HOST'),
            'user' => 'postgres',
            'password' => 'password',
        ], $config);
        $connection->executeStatement(<<<'SQL'
            DROP TABLE IF EXISTS rememberme_token;
        SQL);

        $connection->executeStatement(<<<'SQL'
            CREATE TABLE rememberme_token (
                series   CHAR(88)     UNIQUE PRIMARY KEY NOT NULL,
                value    VARCHAR(88)  NOT NULL, -- CHAR(88) adds spaces at the end
                lastUsed TIMESTAMP    NOT NULL,
                class    VARCHAR(100) NOT NULL,
                username VARCHAR(200) NOT NULL
            );
        SQL);

        return new DoctrineTokenProvider($connection);
    }
}
