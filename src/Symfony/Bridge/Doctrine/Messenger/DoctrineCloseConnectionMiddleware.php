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

use Doctrine\Common\Persistence\ManagerRegistry;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;
use Symfony\Component\Messenger\Middleware\StackInterface;

/**
 * Closes connection and therefore saves number of connections.
 *
 * @author Fuong <insidestyles@gmail.com>
 *
 * @experimental in 4.3
 */
class DoctrineCloseConnectionMiddleware implements MiddlewareInterface
{
    private $managerRegistry;
    private $entityManagerName;

    public function __construct(ManagerRegistry $managerRegistry, string $entityManagerName = null)
    {
        $this->managerRegistry = $managerRegistry;
        $this->entityManagerName = $entityManagerName;
    }

    /**
     * {@inheritdoc}
     */
    public function handle(Envelope $envelope, StackInterface $stack): Envelope
    {
        $entityManager = $this->managerRegistry->getManager($this->entityManagerName);

        if (!$entityManager instanceof EntityManagerInterface) {
            throw new \InvalidArgumentException(sprintf('The ObjectManager with name "%s" must be an instance of EntityManagerInterface', $this->entityManagerName));
        }

        try {
            $connection = $entityManager->getConnection();

            return $stack->next()->handle($envelope, $stack);
        } finally {
            $connection->close();
        }
    }
}
