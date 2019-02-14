<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\AutoMapper\Tests\Fixtures;

class UserDTO
{
    /**
     * @var int
     */
    public $id;
    /**
     * @var string
     */
    public $name;
    /**
     * @var int
     */
    public $age;
    /**
     * @var int
     */
    public $yearOfBirth;
    /**
     * @var string
     */
    public $email;
    /**
     * @var AddressDTO|null
     */
    public $address;
    /**
     * @var AddressDTO[]
     */
    public $addresses = [];
    /**
     * @var \DateTime|null
     */
    public $createdAt;
}
