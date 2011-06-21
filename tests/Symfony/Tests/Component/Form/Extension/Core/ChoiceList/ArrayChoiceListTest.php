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

use Symfony\Component\Form\Extension\Core\ChoiceList\ArrayChoiceList;

class ArrayChoiceListTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @expectedException Symfony\Component\Form\Exception\UnexpectedTypeException
     */
    public function testConstructorExpectsArrayOrClosure()
    {
        new ArrayChoiceList('foobar');
    }

    public function testGetChoices()
    {
        $choices = array('a' => 'A', 'b' => 'B');
        $list = new ArrayChoiceList($choices);

        $this->assertSame($choices, $list->getChoices());
    }

    public function testGetChoicesFromClosure()
    {
        $choices = array('a' => 'A', 'b' => 'B');
        $closure = function () use ($choices) { return $choices; };
        $list = new ArrayChoiceList($closure);

        $this->assertSame($choices, $list->getChoices());
    }

    /**
     * @expectedException Symfony\Component\Form\Exception\UnexpectedTypeException
     */
    public function testClosureShouldReturnArray()
    {
        $closure = function () { return 'foobar'; };
        $list = new ArrayChoiceList($closure);

        $list->getChoices();
    }
}
