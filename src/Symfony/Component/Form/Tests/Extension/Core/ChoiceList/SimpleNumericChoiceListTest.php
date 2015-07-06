<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Tests\Extension\Core\ChoiceList;

use Symfony\Component\Form\Extension\Core\ChoiceList\SimpleChoiceList;

/**
 * @group legacy
 */
class SimpleNumericChoiceListTest extends AbstractChoiceListTest
{
    public function testGetIndicesForChoicesDealsWithNumericChoices()
    {
        // Pass choices as strings although they are integers
        $choices = array('0', '1');
        $this->assertSame(array(0, 1), $this->list->getIndicesForChoices($choices));
    }

    public function testGetIndicesForValuesDealsWithNumericValues()
    {
        // Pass values as strings although they are integers
        $values = array('0', '1');
        $this->assertSame(array(0, 1), $this->list->getIndicesForValues($values));
    }

    public function testGetChoicesForValuesDealsWithNumericValues()
    {
        // Pass values as strings although they are integers
        $values = array('0', '1');
        $this->assertSame(array(0, 1), $this->list->getChoicesForValues($values));
    }

    public function testGetValuesForChoicesDealsWithNumericValues()
    {
        // Pass values as strings although they are integers
        $values = array('0', '1');

        $this->assertSame(array('0', '1'), $this->list->getValuesForChoices($values));
    }

    /**
     * @return \Symfony\Component\Form\Extension\Core\ChoiceList\ChoiceListInterface
     */
    protected function createChoiceList()
    {
        return new SimpleChoiceList(array(
            'Group 1' => array(0 => 'A', 1 => 'B'),
            'Group 2' => array(2 => 'C', 3 => 'D'),
        ), array(1, 2));
    }

    protected function getChoices()
    {
        return array(0 => 0, 1 => 1, 2 => 2, 3 => 3);
    }

    protected function getLabels()
    {
        return array(0 => 'A', 1 => 'B', 2 => 'C', 3 => 'D');
    }

    protected function getValues()
    {
        return array(0 => '0', 1 => '1', 2 => '2', 3 => '3');
    }

    protected function getIndices()
    {
        return array(0, 1, 2, 3);
    }
}
