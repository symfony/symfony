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
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;

/**
 * Wraps all handlers in a single doctrine transaction.
 *
 * @author Tobias Nyholm <tobias.nyholm@gmail.com>
 */
class DoctrineTransactionMiddleware implements MiddlewareInterface
{
    private $managerRegistry;
    private $entityManagerName;

    public function __construct(ManagerRegistry $managerRegistry, ?string $entityManagerName)
    {
        $this->managerRegistry = $managerRegistry;
        $this->entityManagerName = $entityManagerName;
    }

    public function handle($message, callable $next)
    {
        $entityManager = $this->managerRegistry->getManager($this->entityManagerName);

        if (!$entityManager instanceof EntityManagerInterface) {
            throw new \InvalidArgumentException(sprintf('The ObjectManager with name "%s" must be an instance of EntityManagerInterface', $this->entityManagerName));
        }

        $entityManager->getConnection()->beginTransaction();
        try {
            $result = $next($message);
            $entityManager->flush();
            $entityManager->getConnection()->commit();
        } catch (\Throwable $exception) {
            $entityManager->getConnection()->rollBack();

            throw $exception;
        }

        return $result;
    }
}
