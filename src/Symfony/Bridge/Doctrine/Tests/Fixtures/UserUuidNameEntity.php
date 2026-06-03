<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Doctrine\Tests\Fixtures;

use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\Id;
use Symfony\Component\Uid\Uuid;

#[Entity]
class UserUuidNameEntity
{
    public function __construct(
        #[Id, Column]
        public ?Uuid $id = null,
        #[Column(unique: true)]
        public ?string $fullName = null,
    ) {
    }
}
