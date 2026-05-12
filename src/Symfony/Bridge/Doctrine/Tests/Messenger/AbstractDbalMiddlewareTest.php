<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Doctrine\Tests\Messenger;

use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\Persistence\ObjectManager;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\IgnoreDeprecations;
use Symfony\Bridge\Doctrine\Messenger\AbstractDbalMiddleware;
use Symfony\Bridge\PhpUnit\ExpectUserDeprecationMessageTrait;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;
use Symfony\Component\Messenger\Middleware\StackInterface;
use Symfony\Component\Messenger\Test\Middleware\MiddlewareTestCase;

class AbstractDbalMiddlewareTest extends MiddlewareTestCase
{
    use ExpectUserDeprecationMessageTrait;

    public function testNamedConnection()
    {
        $connection = $this->createStub(Connection::class);

        $managerRegistry = $this->createStub(ManagerRegistry::class);
        $managerRegistry->method('getConnection')->willReturnMap([['connectionName', $connection]]);

        $middleware = new DummyMiddleware($managerRegistry, connectionName: 'connectionName');

        $middleware->handle(new Envelope(new \stdClass()), $this->getStackMock());

        $this->assertSame($connection, $middleware->handledConnection);
    }

    public function testMissingConnection()
    {
        $managerRegistry = $this->createStub(ManagerRegistry::class);
        $managerRegistry->method('getConnection')->willThrowException(new \InvalidArgumentException('Doctrine default Connection named "unknownConnectionName" does not exist.'));

        $middleware = new DummyMiddleware($managerRegistry, connectionName: 'unknownConnectionName');

        $this->expectException(UnrecoverableMessageHandlingException::class);
        $this->expectExceptionMessage('Doctrine default Connection named "unknownConnectionName" does not exist.');

        $middleware->handle(new Envelope(new \stdClass()), $this->getStackMock(false));
    }

    public function testInvalidConnection()
    {
        $managerRegistry = $this->createStub(ManagerRegistry::class);
        $managerRegistry->method('getConnection')->willReturn(new \stdClass());

        $middleware = new DummyMiddleware($managerRegistry, connectionName: 'invalidConnectionName');

        $this->expectException(UnrecoverableMessageHandlingException::class);
        $this->expectExceptionMessage('Expected "invalidConnectionName" to be a DBAL connection, but got a "stdClass".');

        $middleware->handle(new Envelope(new \stdClass()), $this->getStackMock(false));
    }

    #[Group('legacy')]
    #[IgnoreDeprecations]
    public function testDefaultEntityManager()
    {
        $connection = $this->createStub(Connection::class);

        $entityManager = $this->createStub(EntityManagerInterface::class);
        $entityManager->method('getConnection')->willReturn($connection);

        $managerRegistry = $this->createStub(ManagerRegistry::class);
        $managerRegistry->method('getManager')->willReturnMap([[null, $entityManager]]);

        $middleware = new DummyMiddleware($managerRegistry);

        $this->expectUserDeprecationMessage('Since symfony/doctrine-bridge 8.2: Instantiating "Symfony\Bridge\Doctrine\Tests\Messenger\DummyMiddleware" without a connection name is deprecated.');

        $middleware->handle(new Envelope(new \stdClass()), $this->getStackMock());

        $this->assertSame($connection, $middleware->handledConnection);
    }

    #[Group('legacy')]
    #[IgnoreDeprecations]
    public function testNamedEntityManager()
    {
        $connection = $this->createStub(Connection::class);

        $entityManager = $this->createStub(EntityManagerInterface::class);
        $entityManager->method('getConnection')->willReturn($connection);

        $managerRegistry = $this->createStub(ManagerRegistry::class);
        $managerRegistry->method('getManager')->willReturnMap([['entityManagerName', $entityManager]]);

        $middleware = new DummyMiddleware($managerRegistry, 'entityManagerName');

        $this->expectUserDeprecationMessage('Since symfony/doctrine-bridge 8.2: Instantiating "Symfony\Bridge\Doctrine\Tests\Messenger\DummyMiddleware" without a connection name is deprecated.');

        $middleware->handle(new Envelope(new \stdClass()), $this->getStackMock());

        $this->assertSame($connection, $middleware->handledConnection);
    }

    #[Group('legacy')]
    #[IgnoreDeprecations]
    public function testMissingEntityManager()
    {
        $managerRegistry = $this->createStub(ManagerRegistry::class);
        $managerRegistry->method('getManager')->willThrowException(new \InvalidArgumentException('Doctrine default Manager named "missingEntityManagerName" does not exist.'));

        $middleware = new DummyMiddleware($managerRegistry, 'missingEntityManagerName');

        $this->expectUserDeprecationMessage('Since symfony/doctrine-bridge 8.2: Instantiating "Symfony\Bridge\Doctrine\Tests\Messenger\DummyMiddleware" without a connection name is deprecated.');

        $this->expectException(UnrecoverableMessageHandlingException::class);
        $this->expectExceptionMessage('Doctrine default Manager named "missingEntityManagerName" does not exist.');

        $middleware->handle(new Envelope(new \stdClass()), $this->getStackMock(false));
    }

    #[Group('legacy')]
    #[IgnoreDeprecations]
    public function testInvalidEntityManager()
    {
        $manager = $this->createStub(ObjectManager::class);

        $managerRegistry = $this->createStub(ManagerRegistry::class);
        $managerRegistry->method('getManager')->willReturn($manager);

        $middleware = new DummyMiddleware($managerRegistry, 'invalidEntityManagerName');

        $this->expectUserDeprecationMessage('Since symfony/doctrine-bridge 8.2: Instantiating "Symfony\Bridge\Doctrine\Tests\Messenger\DummyMiddleware" without a connection name is deprecated.');

        $this->expectException(UnrecoverableMessageHandlingException::class);
        $this->expectExceptionMessage(\sprintf('Expected "invalidEntityManagerName" to be an entity manager, but got a "%s".', $manager::class));

        $middleware->handle(new Envelope(new \stdClass()), $this->getStackMock(false));
    }

    #[Group('legacy')]
    #[IgnoreDeprecations]
    public function testOverridingHandleForManagerIsDeprecated()
    {
        $this->expectUserDeprecationMessage('Since symfony/doctrine-bridge 8.2: Overriding "Symfony\Bridge\Doctrine\Messenger\AbstractDbalMiddleware::handleForManager()" in "Symfony\Bridge\Doctrine\Tests\Messenger\HandleForManagerMiddleware" is deprecated, override "handleForConnection()" instead.');

        new HandleForManagerMiddleware($this->createStub(ManagerRegistry::class), connectionName: 'whatever');
    }
}

class DummyMiddleware extends AbstractDbalMiddleware
{
    public ?Connection $handledConnection = null;

    protected function handleForConnection(Connection $connection, Envelope $envelope, StackInterface $stack): Envelope
    {
        $this->handledConnection = $connection;

        return $stack->next()->handle($envelope, $stack);
    }
}

class HandleForManagerMiddleware extends AbstractDbalMiddleware
{
    protected function handleForManager(EntityManagerInterface $entityManager, Envelope $envelope, StackInterface $stack): Envelope
    {
        return $stack->next()->handle($envelope, $stack);
    }

    protected function handleForConnection(Connection $connection, Envelope $envelope, StackInterface $stack): Envelope
    {
        return $stack->next()->handle($envelope, $stack);
    }
}
