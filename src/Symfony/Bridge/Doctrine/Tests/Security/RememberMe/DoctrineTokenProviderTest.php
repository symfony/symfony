<?php

namespace Security\RememberMe;

use Doctrine\DBAL\DriverManager;
use PHPUnit\Framework\TestCase;
use Symfony\Bridge\Doctrine\Security\RememberMe\DoctrineTokenProvider;
use Symfony\Component\Security\Core\Authentication\RememberMe\PersistentToken;
use Symfony\Component\Security\Core\Exception\TokenNotFoundException;

/**
 * @requires extension pdo_sqlite
 */
class DoctrineTokenProviderTest extends TestCase
{
    public function testCreateNewToken()
    {
        $provider = $this->bootstrapProvider();

        $token = new PersistentToken('someClass', 'someUser', 'someSeries', 'tokenValue', new \DateTime('2013-01-26T18:23:51'));
        $provider->createNewToken($token);

        $this->assertEquals($provider->loadTokenBySeries('someSeries'), $token);
    }

    public function testLoadTokenBySeriesThrowsNotFoundException()
    {
        $provider = $this->bootstrapProvider();

        $this->expectException(TokenNotFoundException::class);
        $provider->loadTokenBySeries('someSeries');
    }

    public function testUpdateToken()
    {
        $provider = $this->bootstrapProvider();

        $token = new PersistentToken('someClass', 'someUser', 'someSeries', 'tokenValue', new \DateTime('2013-01-26T18:23:51'));
        $provider->createNewToken($token);
        $provider->updateToken('someSeries', 'newValue', $lastUsed = new \DateTime('2014-06-26T22:03:46'));
        $token = $provider->loadTokenBySeries('someSeries');

        $this->assertEquals('newValue', $token->getTokenValue());
        $this->assertEquals($token->getLastUsed(), $lastUsed);
    }

    public function testDeleteToken()
    {
        $provider = $this->bootstrapProvider();
        $token = new PersistentToken('someClass', 'someUser', 'someSeries', 'tokenValue', new \DateTime('2013-01-26T18:23:51'));
        $provider->createNewToken($token);
        $provider->deleteTokenBySeries('someSeries');

        $this->expectException(TokenNotFoundException::class);

        $provider->loadTokenBySeries('someSeries');
    }

    /**
     * @return DoctrineTokenProvider
     */
    private function bootstrapProvider()
    {
        $connection = DriverManager::getConnection([
            'driver' => 'pdo_sqlite',
            'url' => 'sqlite:///:memory:',
        ]);
        $connection->{method_exists($connection, 'executeStatement') ? 'executeStatement' : 'executeUpdate'}(<<< 'SQL'
            CREATE TABLE rememberme_token (
                series   char(88)     UNIQUE PRIMARY KEY NOT NULL,
                value    char(88)     NOT NULL,
                lastUsed datetime     NOT NULL,
                class    varchar(100) NOT NULL,
                username varchar(200) NOT NULL
            );
SQL
        );

        return new DoctrineTokenProvider($connection);
    }
}
