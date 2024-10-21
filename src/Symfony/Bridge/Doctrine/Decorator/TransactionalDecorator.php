<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Doctrine\Decorator;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Decorator\DecoratorInterface;

/**
 * @author Yonel Ceruto <open@yceruto.dev>
 */
class TransactionalDecorator implements DecoratorInterface
{
    public function __construct(
        private readonly ManagerRegistry $managerRegistry,
    ) {
    }

    public function decorate(\Closure $func, Transactional $transactional = new Transactional()): \Closure
    {
        $entityManager = $this->managerRegistry->getManager($transactional->name);

        if (!$entityManager instanceof EntityManagerInterface) {
            throw new \RuntimeException(\sprintf('The manager "%s" is not an entity manager.', $transactional->name));
        }

        return static function (mixed ...$args) use ($func, $entityManager) {
            $entityManager->getConnection()->beginTransaction();

            try {
                $return = $func(...$args);

                $entityManager->flush();
                $entityManager->getConnection()->commit();

                return $return;
            } catch (\Throwable $e) {
                $entityManager->close();
                $entityManager->getConnection()->rollBack();

                throw $e;
            }
        };
    }
}
