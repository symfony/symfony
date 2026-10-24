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

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\KeyManagement\Bridge\DoctrineOrm\Attribute\Encrypted;

#[ORM\Entity]
#[ORM\Table(name: 'automatic_record')]
class RenamedRecord
{
    #[ORM\Id, ORM\Column]
    public int $id = 1;

    #[ORM\Column(name: 'first_value')]
    #[Encrypted]
    public string $renamedFirst = '';

    #[ORM\Column(name: 'second_value')]
    #[Encrypted]
    public string $renamedSecond = '';
}
