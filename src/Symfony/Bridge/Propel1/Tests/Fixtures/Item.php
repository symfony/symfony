<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Propel1\Tests\Fixtures;

class Item implements \Persistent
{
    private $id;
    private $value;
    private $groupName;
    private $price;

    private $slug;

    public function __construct($id = null, $value = null, $groupName = null, $price = null, $slug = null)
    {
        $this->id = $id;
        $this->value = $value;
        $this->groupName = $groupName;
        $this->price = $price;
        $this->slug = $slug;
    }

    public function getId()
    {
        return $this->id;
    }

    public function setId($id)
    {
        $this->id = $id;
    }

    public function getValue()
    {
        return $this->value;
    }

    public function getGroupName()
    {
        return $this->groupName;
    }

    public function getPrice()
    {
        return $this->price;
    }

    public function getSlug()
    {
        return $this->slug;
    }

    public function getPrimaryKey()
    {
        return $this->getId();
    }

    public function setPrimaryKey($primaryKey)
    {
        $this->setId($primaryKey);
    }

    public function isModified()
    {
        return false;
    }

    public function isColumnModified($col)
    {
        return false;
    }

    public function isNew()
    {
        return false;
    }

    public function setNew($b)
    {
    }

    public function resetModified()
    {
    }

    public function isDeleted()
    {
        return false;
    }

    public function setDeleted($b)
    {
    }

    public function delete(\PropelPDO $con = null)
    {
    }

    public function save(\PropelPDO $con = null)
    {
    }
}
