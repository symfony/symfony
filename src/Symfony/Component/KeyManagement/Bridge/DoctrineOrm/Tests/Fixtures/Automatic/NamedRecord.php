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
class NamedRecord
{
    #[ORM\Id, ORM\Column]
    public int $id = 1;

    #[ORM\Column]
    #[Encrypted]
    public string $privateValue = '';

    #[ORM\Embedded(class: SecretAddress::class, columnPrefix: 'delivery_')]
    public SecretAddress $delivery;

    #[ORM\Embedded(class: SecretAddress::class, columnPrefix: 'billing_')]
    public SecretAddress $billing;

    public function __construct()
    {
        $this->delivery = new SecretAddress();
        $this->billing = new SecretAddress();
    }
}
