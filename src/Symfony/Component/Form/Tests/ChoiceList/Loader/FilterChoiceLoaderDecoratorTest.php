<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Tests\ChoiceList\Loader;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\ChoiceList\ArrayChoiceList;
use Symfony\Component\Form\ChoiceList\Loader\FilterChoiceLoaderDecorator;
use Symfony\Component\Form\Tests\Fixtures\ArrayChoiceLoader;

class FilterChoiceLoaderDecoratorTest extends TestCase
{
    public function testLoadChoiceList()
    {
        $filter = fn ($choice) => 0 === $choice % 2;

        $loader = new FilterChoiceLoaderDecorator(new ArrayChoiceLoader(range(1, 4)), $filter);

        $this->assertEquals(new ArrayChoiceList([1 => 2, 3 => 4]), $loader->loadChoiceList());
    }

    public function testLoadChoiceListWithGroupedChoices()
    {
        $filter = fn ($choice) => $choice < 9 && 0 === $choice % 2;

        $loader = new FilterChoiceLoaderDecorator(new ArrayChoiceLoader(['units' => range(1, 9), 'tens' => range(10, 90, 10)]), $filter);

        $this->assertEquals(new ArrayChoiceList([
            'units' => [
                1 => 2,
                3 => 4,
                5 => 6,
                7 => 8,
            ],
        ]), $loader->loadChoiceList());
    }

    public function testLoadChoiceListMixedWithGroupedAndNonGroupedChoices()
    {
        $filter = fn ($choice) => 0 === $choice % 2;

        $choices = array_merge(range(1, 9), ['grouped' => range(10, 40, 5)]);
        $loader = new FilterChoiceLoaderDecorator(new ArrayChoiceLoader($choices), $filter);

        $this->assertEquals(new ArrayChoiceList([
            1 => 2,
            3 => 4,
            5 => 6,
            7 => 8,
            'grouped' => [
                0 => 10,
                2 => 20,
                4 => 30,
                6 => 40,
            ],
        ]), $loader->loadChoiceList());
    }

    public function testLoadValuesForChoices()
    {
        $evenValues = [1 => '2', 3 => '4'];

        $filter = fn ($choice) => 0 === $choice % 2;

        $loader = new FilterChoiceLoaderDecorator(new ArrayChoiceLoader([range(1, 4)]), $filter);

        $this->assertSame($evenValues, $loader->loadValuesForChoices(range(1, 4)));
    }

    public function testLoadChoicesForValues()
    {
        $evenChoices = [1 => 2, 3 => 4];
        $values = array_map('strval', range(1, 4));

        $filter = fn ($choice) => 0 === $choice % 2;

        $loader = new FilterChoiceLoaderDecorator(new ArrayChoiceLoader(range(1, 4)), $filter);

        $this->assertEquals($evenChoices, $loader->loadChoicesForValues($values));
    }
}
