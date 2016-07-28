<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Tests\Fixtures;

class Author
{
    public $firstName;
    private $lastName;
    private $australian;
    public $child;
    private $readPermissions;

    public function __construct($firstName = null, $lastName = null)
    {
        $this->firstName = $firstName;
        $this->lastName = $lastName;
    }

    public function setLastName($lastName)
    {
        $this->lastName = $lastName;
    }

    public function getLastName()
    {
        return $this->lastName;
    }

    public function setAustralian($australian)
    {
        $this->australian = $australian;
    }

    public function isAustralian()
    {
        return $this->australian;
    }

    public function setReadPermissions($bool)
    {
        $this->readPermissions = $bool;
    }

    public function hasReadPermissions()
    {
        return $this->readPermissions;
    }

    public function getPrivateSetter()
    {
    }
}
