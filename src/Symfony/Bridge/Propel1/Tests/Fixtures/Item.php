<?php

/*
 * this file is part of the symfony package.
 *
 * (c) fabien potencier <fabien@symfony.com>
 *
 * for the full copyright and license information, please view the license
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Propel1\Tests\Fixtures;

use \PropelPDO;

class Item implements \Persistent
{
    private $id;

    private $value;

    private $groupName;

    public function __construct($id = null, $value = null, $groupName = null)
    {
        $this->id = $id;
        $this->value = $value;
        $this->groupName = $groupName;
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

    public function delete(PropelPDO $con = null)
    {
    }

    public function save(PropelPDO $con = null)
    {
    }
}
