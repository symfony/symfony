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
use Symfony\Component\Form\ChoiceList\ChoiceListInterface;
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
        $list = new LazyChoiceList($choiceLoader = new UsageTrackingChoiceLoader($choices), function ($choice) use ($choices) {
            return array_search($choice, $choices);
        });

        $this->assertSame(['RESULT'], $list->getChoices());
        $this->assertSame(['RESULT'], $list->getChoices());
        $this->assertSame(1, $choiceLoader->loadChoiceListCalls);
    }

    public function testGetValuesLoadsLoadedListOnFirstCall()
    {
        $list = new LazyChoiceList($choiceLoader = new UsageTrackingChoiceLoader(['RESULT']), function ($choice) {
            return $choice;
        });

        $this->assertSame(['RESULT'], $list->getValues());
        $this->assertSame(['RESULT'], $list->getValues());
        $this->assertSame(1, $choiceLoader->loadChoiceListCalls);
    }

    public function testGetStructuredValuesLoadsLoadedListOnFirstCall()
    {
        $list = new LazyChoiceList($choiceLoader = new UsageTrackingChoiceLoader(['RESULT']), function ($choice) {
            return $choice;
        });

        $this->assertSame(['RESULT'], $list->getStructuredValues());
        $this->assertSame(['RESULT'], $list->getStructuredValues());
        $this->assertSame(2, $choiceLoader->loadChoiceListCalls);
    }

    public function testGetOriginalKeysLoadsLoadedListOnFirstCall()
    {
        $choices = [
            'a' => 'foo',
            'b' => 'bar',
            'c' => 'baz',
        ];
        $list = new LazyChoiceList($choiceLoader = new UsageTrackingChoiceLoader($choices), function ($choice) {
            return $choice;
        });

        $this->assertSame(['foo' => 'a', 'bar' => 'b', 'baz' => 'c'], $list->getOriginalKeys());
        $this->assertSame(['foo' => 'a', 'bar' => 'b', 'baz' => 'c'], $list->getOriginalKeys());
        $this->assertSame(2, $choiceLoader->loadChoiceListCalls);
    }

    public function testGetChoicesForValuesForwardsCallIfListNotLoaded()
    {
        $choices = [
            'a' => 'foo',
            'b' => 'bar',
            'c' => 'baz',
        ];
        $list = new LazyChoiceList($choiceLoader = new UsageTrackingChoiceLoader($choices), function ($choice) use ($choices) {
            return array_search($choice, $choices);
        });

        $this->assertSame(['foo', 'bar'], $list->getChoicesForValues(['a', 'b']));
        $this->assertSame(['foo', 'bar'], $list->getChoicesForValues(['a', 'b']));
        $this->assertSame(2, $choiceLoader->loadChoiceListCalls);
    }

    public function testGetChoicesForValuesUsesLoadedList()
    {
        $choices = [
            'a' => 'foo',
            'b' => 'bar',
            'c' => 'baz',
        ];
        $list = new LazyChoiceList($choiceLoader = new UsageTrackingChoiceLoader($choices), function ($choice) use ($choices) {
            return array_search($choice, $choices);
        });

        // load choice list
        $list->getChoices();

        $this->assertSame(['foo', 'bar'], $list->getChoicesForValues(['a', 'b']));
        $this->assertSame(['foo', 'bar'], $list->getChoicesForValues(['a', 'b']));
        $this->assertSame(1, $choiceLoader->loadChoiceListCalls);
    }

    public function testGetValuesForChoicesUsesLoadedList()
    {
        $choices = [
            'a' => 'foo',
            'b' => 'bar',
            'c' => 'baz',
        ];
        $list = new LazyChoiceList($choiceLoader = new UsageTrackingChoiceLoader($choices), function ($choice) use ($choices) {
            return array_search($choice, $choices);
        });

        // load choice list
        $list->getChoices();

        $this->assertSame(['a', 'b'], $list->getValuesForChoices(['foo', 'bar']));
        $this->assertSame(['a', 'b'], $list->getValuesForChoices(['foo', 'bar']));
        $this->assertSame(1, $choiceLoader->loadChoiceListCalls);
    }
}

class UsageTrackingChoiceLoader extends ArrayChoiceLoader
{
    public $loadChoiceListCalls = 0;

    public function loadChoiceList($value = null): ChoiceListInterface
    {
        ++$this->loadChoiceListCalls;

        return parent::loadChoiceList($value);
    }
}
