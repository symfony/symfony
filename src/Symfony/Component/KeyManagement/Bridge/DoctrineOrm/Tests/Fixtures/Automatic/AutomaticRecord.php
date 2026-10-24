<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\KeyManagement\Bridge\DoctrineOrm\Tests\Fixtures\Automatic;

use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\Id;
use Doctrine\ORM\Mapping\Table;
use Symfony\Component\KeyManagement\Bridge\DoctrineOrm\Attribute\Encrypted;

#[Entity]
#[Table(name: 'automatic_record')]
class AutomaticRecord
{
    #[Id, Column]
    public int $id = 1;

    #[Column(name: 'first_value')]
    #[Encrypted]
    public string $first = '';

    #[Column(name: 'second_value')]
    #[Encrypted]
    public string $second = '';
}
