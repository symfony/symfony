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
 * An encrypted column next to a unique one, so that a flush can fail after the column was
 * converted.
 */
#[Entity]
class EncryptedColumnEntity
{
    public const string TYPE = 'key_management_test_encrypted';

    #[Id, Column(type: 'integer'), GeneratedValue]
    public ?int $id = null;

    #[Column(type: 'string', unique: true)]
    public string $username = '';

    #[Column(type: self::TYPE)]
    public string $email = '';
}
