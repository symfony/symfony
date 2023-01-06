<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

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

    public function testVerifyOutdatedTokenAfterParallelRequest()
    {
        $provider = $this->bootstrapProvider();
        $series = base64_encode(random_bytes(64));
        $oldValue = 'oldValue';
        $newValue = 'newValue';

        // setup existing token
        $token = new PersistentToken('someClass', 'someUser', $series, $oldValue, new \DateTime('2013-01-26T18:23:51'));
        $provider->createNewToken($token);

        // new request comes in requiring remember-me auth, which updates the token
        $provider->updateExistingToken($token, $newValue, new \DateTime('-5 seconds'));
        $provider->updateToken($series, $newValue, new \DateTime('-5 seconds'));

        // parallel request comes in with the old remember-me cookie and session, which also requires reauth
        $token = $provider->loadTokenBySeries($series);
        $this->assertEquals($newValue, $token->getTokenValue());

        // new token is valid
        $this->assertTrue($provider->verifyToken($token, $newValue));
        // old token is still valid
        $this->assertTrue($provider->verifyToken($token, $oldValue));
    }

    public function testVerifyOutdatedTokenAfterParallelRequestFailsAfter60Seconds()
    {
        $provider = $this->bootstrapProvider();
        $series = base64_encode(random_bytes(64));
        $oldValue = 'oldValue';
        $newValue = 'newValue';

        // setup existing token
        $token = new PersistentToken('someClass', 'someUser', $series, $oldValue, new \DateTime('2013-01-26T18:23:51'));
        $provider->createNewToken($token);

        // new request comes in requiring remember-me auth, which updates the token
        $provider->updateExistingToken($token, $newValue, new \DateTime('-61 seconds'));
        $provider->updateToken($series, $newValue, new \DateTime('-5 seconds'));

        // parallel request comes in with the old remember-me cookie and session, which also requires reauth
        $token = $provider->loadTokenBySeries($series);
        $this->assertEquals($newValue, $token->getTokenValue());

        // new token is valid
        $this->assertTrue($provider->verifyToken($token, $newValue));
        // old token is not valid anymore after 60 seconds
        $this->assertFalse($provider->verifyToken($token, $oldValue));
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
