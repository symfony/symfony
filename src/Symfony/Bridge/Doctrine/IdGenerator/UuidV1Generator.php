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
use Symfony\Component\Uid\UuidV1;

final class UuidV1Generator extends AbstractIdGenerator
{
    public function generate(EntityManager $em, $entity): UuidV1
    {
        return new UuidV1();
    }
}
