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

class TranslatableItem implements \Persistent
{
    private $id;
    private $currentTranslations;
    private $groupName;
    private $price;

    public function __construct($id = null, $translations = array())
    {
        $this->id = $id;
        $this->currentTranslations = $translations;
    }

    public function getId()
    {
        return $this->id;
    }

    public function setId($id)
    {
        $this->id = $id;
    }

    public function getGroupName()
    {
        return $this->groupName;
    }

    public function getPrice()
    {
        return $this->price;
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

    public function getTranslation($locale = 'de', \PropelPDO $con = null)
    {
        if (!isset($this->currentTranslations[$locale])) {
            $translation = new TranslatableItemI18n();
            $translation->setLocale($locale);
            $this->currentTranslations[$locale] = $translation;
        }

        return $this->currentTranslations[$locale];
    }

    public function addTranslatableItemI18n(TranslatableItemI18n $i)
    {
        if (!in_array($i, $this->currentTranslations)) {
            $this->currentTranslations[$i->getLocale()] = $i;
            $i->setItem($this);
        }
    }

    public function removeTranslatableItemI18n(TranslatableItemI18n $i)
    {
        unset($this->currentTranslations[$i->getLocale()]);
    }

    public function getTranslatableItemI18ns()
    {
        return $this->currentTranslations;
    }
}
