<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Doctrine\IdGenerator;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Id\AbstractIdGenerator;
use Symfony\Component\Uid\Factory\UlidFactory;
use Symfony\Component\Uid\Ulid;

final class UlidGenerator extends AbstractIdGenerator
{
    public function __construct(
        private readonly ?UlidFactory $factory = null,
    ) {
    }

    public function generateId(EntityManagerInterface $em, $entity): Ulid
    {
        if ($this->factory) {
            return $this->factory->create();
        }

        return new Ulid();
    }
}
