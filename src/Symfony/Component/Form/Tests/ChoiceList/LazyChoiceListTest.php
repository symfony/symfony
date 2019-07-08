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
use Symfony\Component\Form\ChoiceList\LazyChoiceList;

/**
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class LazyChoiceListTest extends TestCase
{
    /**
     * @var LazyChoiceList
     */
    private $list;

    /**
     * @var \PHPUnit_Framework_MockObject_MockObject
     */
    private $loadedList;

    /**
     * @var \PHPUnit_Framework_MockObject_MockObject
     */
    private $loader;

    private $value;

    protected function setUp()
    {
        $this->loadedList = $this->getMockBuilder('Symfony\Component\Form\ChoiceList\ChoiceListInterface')->getMock();
        $this->loader = $this->getMockBuilder('Symfony\Component\Form\ChoiceList\Loader\ChoiceLoaderInterface')->getMock();
        $this->value = function () {};
        $this->list = new LazyChoiceList($this->loader, $this->value);
    }

    public function testGetChoiceLoadersLoadsLoadedListOnFirstCall()
    {
        $this->loader->expects($this->exactly(2))
            ->method('loadChoiceList')
            ->with($this->value)
            ->willReturn($this->loadedList);

        // The same list is returned by the loader
        $this->loadedList->expects($this->exactly(2))
            ->method('getChoices')
            ->willReturn('RESULT');

        $this->assertSame('RESULT', $this->list->getChoices());
        $this->assertSame('RESULT', $this->list->getChoices());
    }

    public function testGetValuesLoadsLoadedListOnFirstCall()
    {
        $this->loader->expects($this->exactly(2))
            ->method('loadChoiceList')
            ->with($this->value)
            ->willReturn($this->loadedList);

        // The same list is returned by the loader
        $this->loadedList->expects($this->exactly(2))
            ->method('getValues')
            ->willReturn('RESULT');

        $this->assertSame('RESULT', $this->list->getValues());
        $this->assertSame('RESULT', $this->list->getValues());
    }

    public function testGetStructuredValuesLoadsLoadedListOnFirstCall()
    {
        $this->loader->expects($this->exactly(2))
            ->method('loadChoiceList')
            ->with($this->value)
            ->willReturn($this->loadedList);

        // The same list is returned by the loader
        $this->loadedList->expects($this->exactly(2))
            ->method('getStructuredValues')
            ->willReturn('RESULT');

        $this->assertSame('RESULT', $this->list->getStructuredValues());
        $this->assertSame('RESULT', $this->list->getStructuredValues());
    }

    public function testGetOriginalKeysLoadsLoadedListOnFirstCall()
    {
        $this->loader->expects($this->exactly(2))
            ->method('loadChoiceList')
            ->with($this->value)
            ->willReturn($this->loadedList);

        // The same list is returned by the loader
        $this->loadedList->expects($this->exactly(2))
            ->method('getOriginalKeys')
            ->willReturn('RESULT');

        $this->assertSame('RESULT', $this->list->getOriginalKeys());
        $this->assertSame('RESULT', $this->list->getOriginalKeys());
    }

    public function testGetChoicesForValuesForwardsCallIfListNotLoaded()
    {
        $this->loader->expects($this->exactly(2))
            ->method('loadChoicesForValues')
            ->with(['a', 'b'])
            ->willReturn('RESULT');

        $this->assertSame('RESULT', $this->list->getChoicesForValues(['a', 'b']));
        $this->assertSame('RESULT', $this->list->getChoicesForValues(['a', 'b']));
    }

    public function testGetChoicesForValuesUsesLoadedList()
    {
        $this->loader->expects($this->exactly(1))
            ->method('loadChoiceList')
            ->with($this->value)
            ->willReturn($this->loadedList);

        $this->loader->expects($this->exactly(2))
            ->method('loadChoicesForValues')
            ->with(['a', 'b'])
            ->willReturn('RESULT');

        // load choice list
        $this->list->getChoices();

        $this->assertSame('RESULT', $this->list->getChoicesForValues(['a', 'b']));
        $this->assertSame('RESULT', $this->list->getChoicesForValues(['a', 'b']));
    }

    public function testGetValuesForChoicesUsesLoadedList()
    {
        $this->loader->expects($this->exactly(1))
            ->method('loadChoiceList')
            ->with($this->value)
            ->willReturn($this->loadedList);

        $this->loader->expects($this->exactly(2))
            ->method('loadValuesForChoices')
            ->with(['a', 'b'])
            ->willReturn('RESULT');

        // load choice list
        $this->list->getChoices();

        $this->assertSame('RESULT', $this->list->getValuesForChoices(['a', 'b']));
        $this->assertSame('RESULT', $this->list->getValuesForChoices(['a', 'b']));
    }
}
