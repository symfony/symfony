<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Doctrine\Messenger;

use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;
use Symfony\Component\Messenger\Middleware\StackInterface;

/**
 * @internal
 */
abstract class AbstractDbalMiddleware implements MiddlewareInterface
{
    public function __construct(
        protected ManagerRegistry $managerRegistry,
        protected ?string $entityManagerName = null,
        protected ?string $connectionName = null,
    ) {
        if (null === $this->connectionName) {
            trigger_deprecation('symfony/doctrine-bridge', '8.2', 'Instantiating "%s" without a connection name is deprecated.', static::class);
        }

        if (self::class !== ($childClass = new \ReflectionMethod($this, 'handleForManager')->getDeclaringClass()->getName())) {
            trigger_deprecation('symfony/doctrine-bridge', '8.2', 'Overriding "%s::handleForManager()" in "%s" is deprecated, override "handleForConnection()" instead.', self::class, $childClass);
        }
    }

    final public function handle(Envelope $envelope, StackInterface $stack): Envelope
    {
        try {
            if (null === $this->connectionName) {
                if (!($entityManager = $this->managerRegistry->getManager($this->entityManagerName)) instanceof EntityManagerInterface) {
                    throw new \InvalidArgumentException(\sprintf('Expected "%s" to be an entity manager, but got a "%s".', $this->entityManagerName ?? $this->managerRegistry->getDefaultManagerName(), $entityManager::class));
                }

                return $this->handleForManager($entityManager, $envelope, $stack);
            }

            if (!($connection = $this->managerRegistry->getConnection($this->connectionName)) instanceof Connection) {
                throw new \InvalidArgumentException(\sprintf('Expected "%s" to be a DBAL connection, but got a "%s".', $this->connectionName, $connection::class));
            }

            return $this->handleForConnection($connection, $envelope, $stack);
        } catch (\InvalidArgumentException $e) {
            throw new UnrecoverableMessageHandlingException($e->getMessage(), 0, $e);
        }
    }

    abstract protected function handleForConnection(Connection $connection, Envelope $envelope, StackInterface $stack): Envelope;

    /**
     * @deprecated since Symfony 8.2, use handleForConnection() instead
     */
    protected function handleForManager(EntityManagerInterface $entityManager, Envelope $envelope, StackInterface $stack): Envelope
    {
        return $this->handleForConnection($entityManager->getConnection(), $envelope, $stack);
    }
}
