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
use Symfony\Component\Form\ChoiceList\ChoiceListInterface;
use Symfony\Component\Form\ChoiceList\LazyChoiceList;
use Symfony\Component\Form\ChoiceList\Loader\CallbackChoiceLoader;
use Symfony\Component\Form\ChoiceList\Loader\FilterChoiceLoader;

/**
 * @author Roland Franssen <franssen.roland@gmail.com>
 */
class FilterChoiceLoaderTest extends TestCase
{
    /**
     * @var callable
     */
    private static $value;

    /**
     * @var array
     */
    private static $choices;

    /**
     * @var string[]
     */
    private static $choiceValues;

    public static function setUpBeforeClass()
    {
        self::$value = function (\stdClass $choice) {
            return $choice->value;
        };
        self::$choices = [
            (object) ['value' => 'choice_one'],
            (object) ['value' => 'choice_two'],
            (object) ['value' => 'choice_three'],
            (object) ['value' => 'choice_four'],
        ];
        self::$choiceValues = ['choice_one', 'choice_two', 'choice_three', 'choice_four'];
    }

    public function testLoadChoiceList()
    {
        $loader = $this->createLoader();

        $this->assertInstanceOf(ChoiceListInterface::class, $loader->loadChoiceList(self::$value));
    }

    public function testLoadChoiceListOnlyOnce()
    {
        $loader = $this->createLoader();
        $loadedChoiceList = $loader->loadChoiceList(self::$value);

        $this->assertSame($loadedChoiceList, $loader->loadChoiceList(self::$value));
    }

    public function testLoadChoicesForValuesLoadsChoiceListOnFirstCall()
    {
        $loader = $this->createLoader();
        $lazyList = new LazyChoiceList($loader, self::$value);

        $this->assertSame(
            $loader->loadChoicesForValues(self::$choiceValues, self::$value),
            $lazyList->getChoicesForValues(self::$choiceValues),
            'Choice list should not be reloaded.'
        );
    }

    public function testLoadValuesForChoicesLoadsChoiceListOnFirstCall()
    {
        $loader = $this->createLoader();
        $lazyList = new LazyChoiceList($loader, self::$value);

        $this->assertSame(
            $loader->loadValuesForChoices(self::$choices, self::$value),
            $lazyList->getValuesForChoices(self::$choices),
            'Choice list should not be reloaded.'
        );
    }

    public function testLoadChoiceListFilters()
    {
        $choiceList = $this->createLoader()->loadChoiceList();

        $this->assertSame([self::$choices[2], self::$choices[3]], $choiceList->getChoices());
        $this->assertSame(['0', '1'], $choiceList->getValues());
        $this->assertSame([1, 'key'], $choiceList->getOriginalKeys());
        $this->assertSame([1 => '0', 'Group' => ['key' => '1']], $choiceList->getStructuredValues());
        $this->assertSame([self::$choices[3]], $choiceList->getChoicesForValues(['1', '2']));
        $this->assertSame([], $choiceList->getChoicesForValues(['foo']));
        $this->assertSame([1 => '0'], $choiceList->getValuesForChoices([self::$choices[1], self::$choices[2]]));
        $this->assertSame([], $choiceList->getValuesForChoices(['foo']));
    }

    public function testLoadChoicesForValuesFilters()
    {
        $loader = $this->createLoader();
        $choices = $loader->loadChoicesForValues(['choice_one', 'choice_three'], self::$value);

        $this->assertSame([1 => self::$choices[2]], $choices);
    }

    public function testLoadValuesForChoicesFilters()
    {
        $loader = $this->createLoader();
        $values = $loader->loadValuesForChoices([
            self::$choices[0],
            self::$choices[2],
        ], self::$value);

        $this->assertSame([1 => 'choice_three'], $values);
    }

    public static function tearDownAfterClass()
    {
        self::$value = null;
        self::$choices = [];
        self::$choiceValues = [];
    }

    private function createLoader(): FilterChoiceLoader
    {
        return new FilterChoiceLoader(new CallbackChoiceLoader(function () {
            return [
                self::$choices[0],
                self::$choices[2],
                'Group' => [
                    self::$choices[1],
                    'key' => self::$choices[3],
                ],
            ];
        }), function (\stdClass $choice) {
            return \in_array($choice->value, ['choice_three', 'choice_four'], true);
        });
    }
}
