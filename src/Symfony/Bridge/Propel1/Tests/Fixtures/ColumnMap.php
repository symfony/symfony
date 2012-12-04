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

class ColumnMap
{
    // Propel type of the column
    protected $type;

    // The TableMap for this column
    protected $table;

    // The name of the column
    protected $columnName;

    public function __construct($name, $containingTable)
    {
        $this->columnName = $name;
        $this->table = $containingTable;
    }

    /**
    * Set the Propel type of this column.
    *
    * @param      string $type A string representing the Propel type (e.g. PropelColumnTypes::DATE).
    * @return     void
    */
    public function setType($type)
    {
      $this->type = $type;
    }

    /**
     * Get the Propel type of this column.
     *
     * @return     string A string representing the Propel type (e.g. PropelColumnTypes::DATE).
     */
    public function getType()
    {
      return $this->type;
    }

   /**
   * Get the PDO type of this column.
   *
   * @return     int The PDO::PARMA_* value
   */
   public function getPdoType()
   {
    return \PropelColumnTypes::getPdoType($this->type);
   }
}
