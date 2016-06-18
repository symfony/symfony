<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Console\Tests\Helper;

use Symfony\Component\Console\Exception\InvalidArgumentException;
use Symfony\Component\Console\Helper\TableCell;

class TableCellTest extends \PHPUnit_Framework_TestCase
{
    public function testTableCellColspanWithValidValue()
    {
        $tableCell = new TableCell('', array('colspan' => 2));

        $this->assertEquals(2, $tableCell->getColspan());
    }

    public function testTableCellRowspanWithValidValue()
    {
        $tableCell = new TableCell('', array('rowspan' => 4));

        $this->assertEquals(4, $tableCell->getRowspan());
    }

    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage The colspan value must be a positive integer ("0" given).
     */
    public function testTableCellColspanWithInvalidValue()
    {
        new TableCell('', array('colspan' => 0));
    }

    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage The rowspan value must be a positive integer ("-1" given).
     */
    public function testTableCellRowspanWithInvalidValue()
    {
        new TableCell('', array('rowspan' => -1));
    }
}
