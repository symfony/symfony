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

use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Id\AbstractIdGenerator;
use Symfony\Component\Uid\UuidV4;

/**
 * @experimental in 5.2
 */
final class UuidV4Generator extends AbstractIdGenerator
{
    public function generate(EntityManager $em, $entity): UuidV4
    {
        return new UuidV4();
    }
}
