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

use Symfony\Component\Console\Helper\TableCell;

class TableCellTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @expectedException        \Symfony\Component\Console\Exception\InvalidArgumentException
     * @expectedExceptionMessage The TableCell does not support the following options: 'invalid'.
     */
    public function testConstructorWithInvalidOptions()
    {
        new TableCell('', array('invalid' => 'invalid'));
    }
}
