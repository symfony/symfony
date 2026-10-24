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
#[ORM\Table(name: 'named_record')]
class PhysicalNamedRecord
{
    #[ORM\Id, ORM\Column]
    public int $id = 1;

    #[ORM\Column(name: 'private_value')]
    #[Encrypted]
    public string $secret = '';

    #[ORM\Column(name: 'delivery_postal_code')]
    #[Encrypted]
    public string $deliverySecret = '';

    #[ORM\Column(name: 'billing_postal_code')]
    #[Encrypted]
    public string $billingSecret = '';
}
