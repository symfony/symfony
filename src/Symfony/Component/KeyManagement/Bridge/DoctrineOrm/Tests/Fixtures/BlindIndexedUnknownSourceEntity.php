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
use Symfony\Component\KeyManagement\BlindIndex\Email;
use Symfony\Component\KeyManagement\Bridge\DoctrineOrm\Attribute\BlindIndexed;

#[Entity]
class BlindIndexedUnknownSourceEntity
{
    #[Id, Column(type: 'integer'), GeneratedValue]
    public ?int $id = null;

    #[Column(type: 'string', nullable: true)]
    #[BlindIndexed('mail', Email::class)]
    public ?string $emailIndex = null;
}
