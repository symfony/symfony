<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\KeyManagement\Tests\Fixtures\Customer;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\KeyManagement\Bridge\DoctrineOrm\Attribute\Encrypted;

#[ORM\Entity]
class Customer
{
    #[ORM\Id, ORM\Column, ORM\GeneratedValue]
    public ?int $id = null;

    #[ORM\Column(nullable: true)]
    #[Encrypted(key: 'customer.phone')]
    public ?string $phone = null;

    #[ORM\Column(type: 'customer_text', nullable: true)]
    #[Encrypted(key: 'customer.notes')]
    public ?string $notes = null;
}
