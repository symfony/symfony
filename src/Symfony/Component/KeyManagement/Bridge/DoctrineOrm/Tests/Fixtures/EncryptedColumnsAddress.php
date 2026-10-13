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
use Doctrine\ORM\Mapping\Embeddable;
use Symfony\Component\KeyManagement\Bridge\DoctrineOrm\Attribute\Encrypted;

#[Embeddable]
class EncryptedColumnsAddress
{
    #[Column(nullable: true)]
    #[Encrypted(key: 'person.address')]
    public ?string $street = null;
}
