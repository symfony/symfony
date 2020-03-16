<?php

namespace Symfony\Component\Form\Tests\ChoiceList\Loader;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\ChoiceList\ArrayChoiceList;
use Symfony\Component\Form\ChoiceList\Loader\ChoiceLoaderInterface;
use Symfony\Component\Form\ChoiceList\Loader\FilterChoiceLoaderDecorator;

class FilterChoiceLoaderDecoratorTest extends TestCase
{
    public function testLoadChoiceList()
    {
        $decorated = $this->getMockBuilder(ChoiceLoaderInterface::class)->getMock();
        $decorated->expects($this->once())
            ->method('loadChoiceList')
            ->willReturn(new ArrayChoiceList(range(1, 4)))
        ;

        $filter = function ($choice) {
            return 0 === $choice % 2;
        };

        $loader = new FilterChoiceLoaderDecorator($decorated, $filter);

        $this->assertEquals(new ArrayChoiceList([1 => 2, 3 => 4]), $loader->loadChoiceList());
    }

    public function testLoadChoiceListWithGroupedChoices()
    {
        $decorated = $this->getMockBuilder(ChoiceLoaderInterface::class)->getMock();
        $decorated->expects($this->once())
            ->method('loadChoiceList')
            ->willReturn(new ArrayChoiceList(['units' => range(1, 9), 'tens' => range(10, 90, 10)]))
        ;

        $filter = function ($choice) {
            return $choice < 9 && 0 === $choice % 2;
        };

        $loader = new FilterChoiceLoaderDecorator($decorated, $filter);

        $this->assertEquals(new ArrayChoiceList([
            'units' => [
                1 => 2,
                3 => 4,
                5 => 6,
                7 => 8,
            ],
        ]), $loader->loadChoiceList());
    }

    public function testLoadValuesForChoices()
    {
        $evenValues = [1 => '2', 3 => '4'];

        $decorated = $this->getMockBuilder(ChoiceLoaderInterface::class)->getMock();
        $decorated->expects($this->never())
            ->method('loadChoiceList')
        ;
        $decorated->expects($this->once())
            ->method('loadValuesForChoices')
            ->with([1 => 2, 3 => 4])
            ->willReturn($evenValues)
        ;

        $filter = function ($choice) {
            return 0 === $choice % 2;
        };

        $loader = new FilterChoiceLoaderDecorator($decorated, $filter);

        $this->assertSame($evenValues, $loader->loadValuesForChoices(range(1, 4)));
    }

    public function testLoadChoicesForValues()
    {
        $evenChoices = [1 => 2, 3 => 4];
        $values = array_map('strval', range(1, 4));

        $decorated = $this->getMockBuilder(ChoiceLoaderInterface::class)->getMock();
        $decorated->expects($this->never())
            ->method('loadChoiceList')
        ;
        $decorated->expects($this->once())
            ->method('loadChoicesForValues')
            ->with($values)
            ->willReturn(range(1, 4))
        ;

        $filter = function ($choice) {
            return 0 === $choice % 2;
        };

        $loader = new FilterChoiceLoaderDecorator($decorated, $filter);

        $this->assertEquals($evenChoices, $loader->loadChoicesForValues($values));
    }
}
