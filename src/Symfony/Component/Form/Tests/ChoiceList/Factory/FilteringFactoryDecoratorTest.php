<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Tests\ChoiceList\Factory;

use Symfony\Component\Form\ChoiceList\ArrayChoiceList;
use Symfony\Component\Form\ChoiceList\Factory\FilteringFactoryDecorator;
use Symfony\Component\Form\ChoiceList\LazyChoiceList;

/**
 * @author Jules Pietri <jules@heahprod.com>
 */
class FilteringFactoryDecoratorTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var \PHPUnit_Framework_MockObject_MockObject
     */
    private $decoratedFactory;

    /**
     * @var FilteringFactoryDecorator
     */
    private $factory;

    protected function setUp()
    {
        $this->decoratedFactory = $this->getMock('Symfony\Component\Form\ChoiceList\Factory\ChoiceListFactoryInterface');
        $this->factory = new FilteringFactoryDecorator($this->decoratedFactory);
    }

    /**
     * @dataProvider provideChoicesAndFilter
     */
    public function testCreateFilteredChoiceListFromChoices($choices, $choiceList, $filter, $filteredChoices)
    {
        $this->decoratedFactory->expects($this->at(0))
            ->method('createListFromChoices')
            ->with($choices)
            ->willReturn($choiceList);
        $this->decoratedFactory->expects($this->at(1))
            ->method('createListFromChoices')
            ->with($filteredChoices)
            ->willReturn('RESULT');

        $this->assertSame('RESULT', $this->factory->createFilteredListFromChoices($choices, null, $filter));
    }

    /**
     * @dataProvider provideChoicesAndFilter
     */
    public function testCreateFilteredChoiceListFromLoader($choices, $choiceList, $filter, $filteredChoices)
    {
        $choiceLoader = $this->getMock('Symfony\Component\Form\ChoiceList\Loader\ChoiceLoaderInterface');
        $lazyChoiceList = new LazyChoiceList($choiceLoader);

        $choiceLoader->expects($this->once())
            ->method('loadChoiceList')
            ->willReturn($choiceList);

        $this->decoratedFactory->expects($this->at(0))
            ->method('createListFromLoader')
            ->with($choiceLoader)
            ->willReturn($lazyChoiceList);

        $this->decoratedFactory->expects($this->at(1))
            ->method('createListFromChoices')
            ->with($filteredChoices)
            ->willReturn('RESULT');

        $this->assertSame('RESULT', $this->factory->createFilteredListFromLoader($choiceLoader, null, $filter));
    }

    /**
     * @dataProvider provideChoicesAndFilter
     */
    public function testCreateFilteredChoiceListFromLoaderLoadsOriginalListOnFirstCall($choices, $choiceList, $filter, $filteredChoices)
    {
        $choiceLoader = $this->getMock('Symfony\Component\Form\ChoiceList\Loader\ChoiceLoaderInterface');
        $lazyChoiceList = new LazyChoiceList($choiceLoader);

        $choiceLoader->expects($this->once())
            ->method('loadChoiceList')
            ->willReturn($choiceList);

        $this->decoratedFactory->expects($this->once())
            ->method('createListFromLoader')
            ->with($choiceLoader)
            ->willReturn($lazyChoiceList);

        $this->decoratedFactory->expects($this->at(1))
            ->method('createListFromChoices')
            ->with($filteredChoices)
            ->willReturn('RESULT');

        $this->decoratedFactory->expects($this->at(2))
            ->method('createListFromChoices')
            ->with($filteredChoices)
            ->willReturn('RESULT');

        $this->assertSame('RESULT', $this->factory->createFilteredListFromLoader($choiceLoader, null, $filter));
        $this->assertSame('RESULT', $this->factory->createFilteredListFromLoader($choiceLoader, null, $filter));
    }

    public function provideChoicesAndFilter()
    {
        return array(
            'Filtered by choices' => array(
                array(range(1, 10)),
                // Choice list from choices by values as string
                new ArrayChoiceList(array_combine(range('1', '10'), range(1, 10))),
                function ($choice) {
                    return 5 < $choice;
                },
                // Filtered choices by values
                array_combine(range('6', '10'), range(6, 10)),
            ),
            'Filtered by values' => array(
                array(range(1, 10)),
                new ArrayChoiceList(array_combine(range('1', '10'), range(1, 10))),
                function ($choice, $value) {
                    return '5' > $value;
                },
                array_combine(range('1', '4'), range(1, 4)),
            ),
        );
    }
}
