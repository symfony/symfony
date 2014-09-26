<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Tests\ChoiceList;

use Symfony\Component\Form\ChoiceList\ArrayChoiceList;

/**
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class ArrayChoiceListTest extends AbstractChoiceListTest
{
    private $object;

    protected function setUp()
    {
        parent::setUp();

        $this->object = new \stdClass();
    }

    protected function createChoiceList()
    {
        return new ArrayChoiceList($this->getChoices(), $this->getValues());
    }

    protected function getChoices()
    {
        return array(0, 1, '1', 'a', false, true, $this->object);
    }

    protected function getValues()
    {
        return array('0', '1', '2', '3', '4', '5', '6');
    }

    /**
     * @expectedException \Symfony\Component\Form\Exception\InvalidArgumentException
     */
    public function testFailIfKeyMismatch()
    {
        new ArrayChoiceList(array(0 => 'a', 1 => 'b'), array(1 => 'a', 2 => 'b'));
    }
}
