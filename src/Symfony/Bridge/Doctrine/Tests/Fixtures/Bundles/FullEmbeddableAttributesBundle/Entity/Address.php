<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Fixtures\Bundles\FullEmbeddableAttributesBundle\Entity;

use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\Embeddable;
use Doctrine\ORM\Mapping\Id;

#[Embeddable]
class Address
{

    #[Column(type: 'string')]
    public $street;

    #[Column(type: 'string')]
    public $zipCode;

    #[Column(type: 'string')]
    public $city;

    public function __construct($street, $zipCode, $city)
    {
        $this->street = $street;
        $this->zipCode = $zipCode;
        $this->city = $city;
    }

    public function __toString(): string
    {
        return sprintf('%s %s %s', $this->street, $this->zipCode, $this->city);
    }
}
