<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\VarDumper\Tests\Caster;

use Symfony\Component\VarDumper\Test\VarDumperTestCase;

class SplCasterTest extends VarDumperTestCase
{
    /**
     * @dataProvider provideCastSplDoublyLinkedList
     */
    public function testCastSplDoublyLinkedList($modeValue, $modeDump)
    {
        $var = new \SplDoublyLinkedList();
        $var->setIteratorMode($modeValue);
        $dump = <<<EOTXT
SplDoublyLinkedList {
%Amode: $modeDump
  dllist: []
}
EOTXT;
        $this->assertDumpMatchesFormat($dump, $var);
    }

    public function provideCastSplDoublyLinkedList()
    {
        return array(
            array(\SplDoublyLinkedList::IT_MODE_FIFO, 'IT_MODE_FIFO | IT_MODE_KEEP'),
            array(\SplDoublyLinkedList::IT_MODE_LIFO, 'IT_MODE_LIFO | IT_MODE_KEEP'),
            array(\SplDoublyLinkedList::IT_MODE_FIFO | \SplDoublyLinkedList::IT_MODE_DELETE, 'IT_MODE_FIFO | IT_MODE_DELETE'),
            array(\SplDoublyLinkedList::IT_MODE_LIFO | \SplDoublyLinkedList::IT_MODE_DELETE, 'IT_MODE_LIFO | IT_MODE_DELETE'),
        );
    }
}
