<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Doctrine\Tests\SchemaListener;

use Doctrine\DBAL\Configuration;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\Event\GenerateSchemaEventArgs;
use PHPUnit\Framework\TestCase;
use Symfony\Bridge\Doctrine\SchemaListener\RememberMeTokenProviderDoctrineSchemaListener;
use Symfony\Bridge\Doctrine\Security\RememberMe\DoctrineTokenProvider;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\User\UserProviderInterface;
use Symfony\Component\Security\Http\RememberMe\PersistentRememberMeHandler;

class RememberMeTokenProviderDoctrineSchemaListenerTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        if (!class_exists(PersistentRememberMeHandler::class)) {
            self::markTestSkipped('This test requires symfony/security-http.');
        }
    }

    public function testPostGenerateSchema()
    {
        $schema = new Schema();
        $dbalConnection = $this->createStub(Connection::class);
        $dbalConnection->method('getConfiguration')->willReturn(new Configuration());
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->once())
            ->method('getConnection')
            ->willReturn($dbalConnection);
        $event = new GenerateSchemaEventArgs($entityManager, $schema);

        $tokenProvider = new DoctrineTokenProvider($dbalConnection);
        $rememberMeHandler = new PersistentRememberMeHandler(
            $tokenProvider,
            $this->createStub(UserProviderInterface::class),
            new RequestStack(),
            []
        );

        $listener = new RememberMeTokenProviderDoctrineSchemaListener([$rememberMeHandler]);
        $listener->postGenerateSchema($event);

        $this->assertTrue($schema->hasTable('rememberme_token'));
    }

    public function testPostGenerateSchemaRespectsSchemaFilter()
    {
        $schema = new Schema();

        $configuration = new Configuration();
        $configuration->setSchemaAssetsFilter(static fn (string $tableName) => 'rememberme_token' !== $tableName);

        $dbalConnection = $this->createStub(Connection::class);
        $dbalConnection->method('getConfiguration')->willReturn($configuration);

        $entityManager = $this->createStub(EntityManagerInterface::class);
        $entityManager->method('getConnection')->willReturn($dbalConnection);
        $event = new GenerateSchemaEventArgs($entityManager, $schema);

        $tokenProvider = new DoctrineTokenProvider($dbalConnection);
        $rememberMeHandler = new PersistentRememberMeHandler(
            $tokenProvider,
            $this->createStub(UserProviderInterface::class),
            new RequestStack(),
            []
        );

        $listener = new RememberMeTokenProviderDoctrineSchemaListener([$rememberMeHandler]);
        $listener->postGenerateSchema($event);

        $this->assertFalse($schema->hasTable('rememberme_token'));
    }
}
