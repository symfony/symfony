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
use Symfony\Component\Form\Tests\Fixtures\ArrayChoiceLoader;

/**
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class LazyChoiceListTest extends TestCase
{
    public function testGetChoiceLoadersLoadsLoadedListOnFirstCall()
    {
        $choices = ['RESULT'];
        $calls = 0;
        $list = new LazyChoiceList(new ArrayChoiceLoader($choices), function ($choice) use ($choices, &$calls) {
            ++$calls;

            return array_search($choice, $choices);
        });

        $this->assertSame(['RESULT'], $list->getChoices());
        $this->assertSame(['RESULT'], $list->getChoices());
        $this->assertSame(2, $calls);
    }

    public function testGetValuesLoadsLoadedListOnFirstCall()
    {
        $calls = 0;
        $list = new LazyChoiceList(new ArrayChoiceLoader(['RESULT']), function ($choice) use (&$calls) {
            ++$calls;

            return $choice;
        });

        $this->assertSame(['RESULT'], $list->getValues());
        $this->assertSame(['RESULT'], $list->getValues());
        $this->assertSame(2, $calls);
    }

    public function testGetStructuredValuesLoadsLoadedListOnFirstCall()
    {
        $calls = 0;
        $list = new LazyChoiceList(new ArrayChoiceLoader(['RESULT']), function ($choice) use (&$calls) {
            ++$calls;

            return $choice;
        });

        $this->assertSame(['RESULT'], $list->getStructuredValues());
        $this->assertSame(['RESULT'], $list->getStructuredValues());
        $this->assertSame(2, $calls);
    }

    public function testGetOriginalKeysLoadsLoadedListOnFirstCall()
    {
        $calls = 0;
        $choices = [
            'a' => 'foo',
            'b' => 'bar',
            'c' => 'baz',
        ];
        $list = new LazyChoiceList(new ArrayChoiceLoader($choices), function ($choice) use (&$calls) {
            ++$calls;

            return $choice;
        });

        $this->assertSame(['foo' => 'a', 'bar' => 'b', 'baz' => 'c'], $list->getOriginalKeys());
        $this->assertSame(['foo' => 'a', 'bar' => 'b', 'baz' => 'c'], $list->getOriginalKeys());
        $this->assertSame(6, $calls);
    }

    public function testGetChoicesForValuesForwardsCallIfListNotLoaded()
    {
        $calls = 0;
        $choices = [
            'a' => 'foo',
            'b' => 'bar',
            'c' => 'baz',
        ];
        $list = new LazyChoiceList(new ArrayChoiceLoader($choices), function ($choice) use ($choices, &$calls) {
            ++$calls;

            return array_search($choice, $choices);
        });

        $this->assertSame(['foo', 'bar'], $list->getChoicesForValues(['a', 'b']));
        $this->assertSame(['foo', 'bar'], $list->getChoicesForValues(['a', 'b']));
        $this->assertSame(6, $calls);
    }

    public function testGetChoicesForValuesUsesLoadedList()
    {
        $choices = [
            'a' => 'foo',
            'b' => 'bar',
            'c' => 'baz',
        ];
        $list = new LazyChoiceList(new ArrayChoiceLoader($choices), fn ($choice) => array_search($choice, $choices));

        // load choice list
        $list->getChoices();

        $this->assertSame(['foo', 'bar'], $list->getChoicesForValues(['a', 'b']));
        $this->assertSame(['foo', 'bar'], $list->getChoicesForValues(['a', 'b']));
    }

    public function testGetValuesForChoicesUsesLoadedList()
    {
        $choices = [
            'a' => 'foo',
            'b' => 'bar',
            'c' => 'baz',
        ];
        $list = new LazyChoiceList(new ArrayChoiceLoader($choices), fn ($choice) => array_search($choice, $choices));

        // load choice list
        $list->getChoices();

        $this->assertSame(['a', 'b'], $list->getValuesForChoices(['foo', 'bar']));
        $this->assertSame(['a', 'b'], $list->getValuesForChoices(['foo', 'bar']));
    }
}
