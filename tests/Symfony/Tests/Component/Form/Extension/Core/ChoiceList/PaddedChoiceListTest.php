<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Tests\Component\Form\Extension\Core\ChoiceList;

use Symfony\Component\Form\Extension\Core\ChoiceList\PaddedChoiceList;

class PaddedChoiceListTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @expectedException Symfony\Component\Form\Exception\UnexpectedTypeException
     */
    public function testConstructorExpectsArrayOrClosure()
    {
        $list = new PaddedChoiceList('foobar', 3, '-', STR_PAD_RIGHT);
    }

    public function testPaddingDirections()
    {
        $list = new PaddedChoiceList(array('a' => 'C', 'b' => 'D'), 3, '-', STR_PAD_RIGHT);
        $this->assertSame(array('a' => 'C--', 'b' => 'D--'), $list->getChoices());
        $list = new PaddedChoiceList(array('a' => 'C', 'b' => 'D'), 3, '-', STR_PAD_LEFT);
        $this->assertSame(array('a' => '--C', 'b' => '--D'), $list->getChoices());
        $list = new PaddedChoiceList(array('a' => 'C', 'b' => 'D'), 3, '-', STR_PAD_BOTH);
        $this->assertSame(array('a' => '-C-', 'b' => '-D-'), $list->getChoices());
    }

    public function testGetChoicesFromClosure()
    {
        $closure = function () { return array('a' => 'C', 'b' => 'D'); };
        $list = new PaddedChoiceList($closure, 3, '-', STR_PAD_RIGHT);

        $this->assertSame(array('a' => 'C--', 'b' => 'D--'), $list->getChoices());
    }

    /**
     * @expectedException Symfony\Component\Form\Exception\UnexpectedTypeException
     */
    public function testClosureShouldReturnArray()
    {
        $closure = function () { return 'foobar'; };
        $list = new PaddedChoiceList($closure, 3, '-', STR_PAD_RIGHT);

        $list->getChoices();
    }
}
