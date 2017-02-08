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

use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\ChoiceList\LegacyChoiceListAdapter;
use Symfony\Component\Form\Extension\Core\ChoiceList\ChoiceListInterface;

/**
 * @author Bernhard Schussek <bschussek@gmail.com>
 * @group legacy
 */
class LegacyChoiceListAdapterTest extends TestCase
{
    /**
     * @var LegacyChoiceListAdapter
     */
    private $list;

    /**
     * @var \PHPUnit_Framework_MockObject_MockObject|ChoiceListInterface
     */
    private $adaptedList;

    protected function setUp()
    {
        $this->adaptedList = $this->getMockBuilder('Symfony\Component\Form\Extension\Core\ChoiceList\ChoiceListInterface')->getMock();
        $this->list = new LegacyChoiceListAdapter($this->adaptedList);
    }

    public function testGetChoices()
    {
        $this->adaptedList->expects($this->once())
            ->method('getChoices')
            ->willReturn(array(1 => 'a', 4 => 'b', 7 => 'c'));
        $this->adaptedList->expects($this->once())
            ->method('getValues')
            ->willReturn(array(1 => ':a', 4 => ':b', 7 => ':c'));

        $this->assertSame(array(':a' => 'a', ':b' => 'b', ':c' => 'c'), $this->list->getChoices());
    }

    public function testGetValues()
    {
        $this->adaptedList->expects($this->once())
            ->method('getChoices')
            ->willReturn(array(1 => 'a', 4 => 'b', 7 => 'c'));
        $this->adaptedList->expects($this->once())
            ->method('getValues')
            ->willReturn(array(1 => ':a', 4 => ':b', 7 => ':c'));

        $this->assertSame(array(':a', ':b', ':c'), $this->list->getValues());
    }

    public function testGetStructuredValues()
    {
        $this->adaptedList->expects($this->once())
            ->method('getChoices')
            ->willReturn(array(1 => 'a', 4 => 'b', 7 => 'c'));
        $this->adaptedList->expects($this->once())
            ->method('getValues')
            ->willReturn(array(1 => ':a', 4 => ':b', 7 => ':c'));

        $this->assertSame(array(1 => ':a', 4 => ':b', 7 => ':c'), $this->list->getStructuredValues());
    }

    public function testGetOriginalKeys()
    {
        $this->adaptedList->expects($this->once())
            ->method('getChoices')
            ->willReturn(array(1 => 'a', 4 => 'b', 7 => 'c'));
        $this->adaptedList->expects($this->once())
            ->method('getValues')
            ->willReturn(array(1 => ':a', 4 => ':b', 7 => ':c'));

        $this->assertSame(array(':a' => 1, ':b' => 4, ':c' => 7), $this->list->getOriginalKeys());
    }

    public function testGetChoicesForValues()
    {
        $this->adaptedList->expects($this->once())
            ->method('getChoicesForValues')
            ->with(array(1 => ':a', 4 => ':b', 7 => ':c'))
            ->willReturn(array(1 => 'a', 4 => 'b', 7 => 'c'));

        $this->assertSame(array(1 => 'a', 4 => 'b', 7 => 'c'), $this->list->getChoicesForValues(array(1 => ':a', 4 => ':b', 7 => ':c')));
    }

    public function testGetValuesForChoices()
    {
        $this->adaptedList->expects($this->once())
            ->method('getValuesForChoices')
            ->with(array(1 => 'a', 4 => 'b', 7 => 'c'))
            ->willReturn(array(1 => ':a', 4 => ':b', 7 => ':c'));

        $this->assertSame(array(1 => ':a', 4 => ':b', 7 => ':c'), $this->list->getValuesForChoices(array(1 => 'a', 4 => 'b', 7 => 'c')));
    }

    public function testGetAdaptedList()
    {
        $this->assertSame($this->adaptedList, $this->list->getAdaptedList());
    }
}
