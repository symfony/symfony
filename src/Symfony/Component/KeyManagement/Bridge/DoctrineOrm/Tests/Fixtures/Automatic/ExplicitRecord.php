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
#[ORM\Table(name: 'explicit_record')]
class ExplicitRecord
{
    #[ORM\Id, ORM\Column]
    public int $id = 1;

    #[ORM\Column]
    #[Encrypted(key: '0')]
    public string $secret = '';

    #[ORM\Column(name: 'automatic_secret')]
    #[Encrypted]
    public string $automatic = '';
}
