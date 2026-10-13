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
use Symfony\Component\KeyManagement\Bridge\DoctrineOrm\Attribute\Encrypted;

/**
 * The attribute on a property that is not a column, which nothing would encrypt.
 */
#[Entity]
class EncryptedUnmappedPropertyEntity
{
    #[Id, Column, GeneratedValue]
    public ?int $id = null;

    #[Encrypted(key: 'person.secret')]
    public ?string $secret = null;
}
