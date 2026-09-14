<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\KeyManagement\Bridge\DoctrineOrm\Tests\Fixtures;

use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\GeneratedValue;
use Doctrine\ORM\Mapping\Id;

/**
 * An entity carrying no index at all, which is what most of the ones a flush walks look like.
 */
#[Entity]
class PlainEntity
{
    #[Id, Column(type: 'integer'), GeneratedValue]
    public ?int $id = null;

    #[Column(type: 'string', nullable: true)]
    public ?string $name = null;
}
