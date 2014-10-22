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

class ItemQuery
{
    private $map = array(
        'id' => \PropelColumnTypes::INTEGER,
        'value' => \PropelColumnTypes::VARCHAR,
        'price' => \PropelColumnTypes::FLOAT,
        'is_active' => \PropelColumnTypes::BOOLEAN,
        'enabled' => \PropelColumnTypes::BOOLEAN_EMU,
        'updated_at' => \PropelColumnTypes::TIMESTAMP,
    );

    public static $result = array();

    public function find()
    {
        return self::$result;
    }

    public function filterById($id)
    {
        return $this;
    }

    public function getTableMap()
    {
        // Allows to define methods in this class
        // to avoid a lot of mock classes
        return $this;
    }

    public function getPrimaryKeys()
    {
        $cm = new \ColumnMap('id', new \TableMap());
        $cm->setType('INTEGER');
        $cm->setPhpName('Id');

        return array('id' => $cm);
    }

    /**
     * Method from the TableMap API
     */
    public function hasColumn($column)
    {
        return in_array($column, array_keys($this->map));
    }

    /**
     * Method from the TableMap API
     */
    public function getColumn($column)
    {
        if ($this->hasColumn($column)) {
            return new Column($column, $this->map[$column]);
        }
    }

    /**
     * Method from the TableMap API
     */
    public function getRelations()
    {
        // table maps
        $authorTable = new \TableMap();
        $authorTable->setClassName('\Foo\Author');

        $resellerTable = new \TableMap();
        $resellerTable->setClassName('\Foo\Reseller');

        // relations
        $mainAuthorRelation = new \RelationMap('MainAuthor');
        $mainAuthorRelation->setType(\RelationMap::MANY_TO_ONE);
        $mainAuthorRelation->setForeignTable($authorTable);

        $authorRelation = new \RelationMap('Author');
        $authorRelation->setType(\RelationMap::ONE_TO_MANY);
        $authorRelation->setForeignTable($authorTable);

        $resellerRelation = new \RelationMap('Reseller');
        $resellerRelation->setType(\RelationMap::MANY_TO_MANY);
        $resellerRelation->setLocalTable($resellerTable);

        return array(
            $mainAuthorRelation,
            $authorRelation,
            $resellerRelation,
        );
    }
}
