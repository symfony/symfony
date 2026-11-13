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

    public function testOutdatedTokenCannotBeUsedWithTheSeriesOfItsCopy()
    {
        $provider = $this->bootstrapProvider();
        $series = base64_encode(random_bytes(64));

        $token = new PersistentToken('someClass', 'someUser', $series, 'oldValue', new \DateTime('2013-01-26T18:23:51'));
        $provider->createNewToken($token);
        $provider->updateExistingToken($token, 'newValue', new \DateTime('-5 seconds'));
        $provider->updateToken($series, 'newValue', new \DateTime('-5 seconds'));

        $copy = $provider->loadTokenBySeries(preg_replace('{=+$}', '_', $series));

        $this->assertFalse($provider->verifyToken($copy, 'oldValue'));
    }

    public function testDeleteTokenAlsoDeletesTheCopyOfTheOutdatedToken()
    {
        $provider = $this->bootstrapProvider();
        $series = base64_encode(random_bytes(64));

        $token = new PersistentToken('someClass', 'someUser', $series, 'oldValue', new \DateTime('2013-01-26T18:23:51'));
        $provider->createNewToken($token);
        $provider->updateExistingToken($token, 'newValue', new \DateTime('-5 seconds'));
        $provider->updateToken($series, 'newValue', new \DateTime('-5 seconds'));
        $provider->deleteTokenBySeries($series);

        $this->expectException(TokenNotFoundException::class);

        $provider->loadTokenBySeries(preg_replace('{=+$}', '_', $series));
    }

    /**
     * @dataProvider provideSeriesGeneratedByTheHandler
     */
    public function testVerifyTokenAcceptsTheSeriesGeneratedByTheHandler(string $series)
    {
        $provider = $this->bootstrapProvider();
        $provider->createNewToken(new PersistentToken('someClass', 'someUser', $series, 'tokenValue', new \DateTime()));

        $this->assertTrue($provider->verifyToken($provider->loadTokenBySeries($series), 'tokenValue'));
    }

    public static function provideSeriesGeneratedByTheHandler(): iterable
    {
        yield 'current format' => [strtr(base64_encode(random_bytes(33)), '+/=', '-_~')];
        yield 'format before Symfony 5.4.19' => [base64_encode(random_bytes(64))];
    }

    /**
     * @dataProvider provideSeriesNotGeneratedByTheHandler
     */
    public function testVerifyTokenRefusesTheSeriesNotGeneratedByTheHandler(string $series)
    {
        $provider = $this->bootstrapProvider();
        // Stands for the row of a copy that the collation of the series column could match
        $provider->createNewToken(new PersistentToken('someClass', 'someUser', $series, 'tokenValue', new \DateTime()));

        $this->assertFalse($provider->verifyToken($provider->loadTokenBySeries($series), 'tokenValue'));
    }

    public static function provideSeriesNotGeneratedByTheHandler(): iterable
    {
        $series = strtr(base64_encode(random_bytes(33)), '+/=', '-_~');
        yield 'copy followed by a space' => [$series.'_ '];
        yield 'copy with an accented letter' => ['é'.substr($series, 1).'_'];
        yield 'copy of a copy' => [$series.'__'];
        yield 'series followed by a newline' => [$series."\n"];

        $series = preg_replace('{=+$}', '_', base64_encode(random_bytes(64)));
        yield 'copy of a series created before Symfony 5.4.19, followed by a space' => [$series.' '];
        yield 'copy of a series created before Symfony 5.4.19, with an accented letter' => ['é'.substr($series, 1)];
    }

    /**
     * @return DoctrineTokenProvider
     */
    protected function bootstrapProvider()
    {
        $config = class_exists(ORMSetup::class) ? ORMSetup::createConfiguration(true) : new Configuration();
        if (class_exists(DefaultSchemaManagerFactory::class)) {
            $config->setSchemaManagerFactory(new DefaultSchemaManagerFactory());
        }

        $connection = DriverManager::getConnection([
            'driver' => 'pdo_sqlite',
            'memory' => true,
        ], $config);
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
