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
use Symfony\Component\Form\ChoiceList\Loader\IntlCallbackChoiceLoader;

/**
 * @author Jules Pietri <jules@heahprod.com>
 * @author Yonel Ceruto <yonelceruto@gmail.com>
 */
class IntlCallbackChoiceLoaderTest extends TestCase
{
    /**
     * @var \Symfony\Component\Form\ChoiceList\Loader\IntlCallbackChoiceLoader
     */
    private static $loader;

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

    /**
     * @var \Symfony\Component\Form\ChoiceList\LazyChoiceList
     */
    private static $lazyChoiceList;

    public static function setUpBeforeClass(): void
    {
        self::$loader = new IntlCallbackChoiceLoader(fn () => self::$choices);
        self::$value = fn ($choice) => $choice->value ?? null;
        self::$choices = [
            (object) ['value' => 'choice_one'],
            (object) ['value' => 'choice_two'],
        ];
        self::$choiceValues = ['choice_one', 'choice_two'];
        self::$lazyChoiceList = new LazyChoiceList(self::$loader, self::$value);
    }

    public function testLoadChoiceList()
    {
        $this->assertInstanceOf(ChoiceListInterface::class, self::$loader->loadChoiceList(self::$value));
    }

    public function testLoadChoicesOnlyOnce()
    {
        $calls = 0;
        $loader = new IntlCallbackChoiceLoader(function () use (&$calls) {
            ++$calls;

            return self::$choices;
        });

        $loadedChoiceList = $loader->loadChoiceList(self::$value);

        $this->assertNotSame($loadedChoiceList, $loader->loadChoiceList(self::$value));
        $this->assertSame(1, $calls);
    }

    public function testLoadChoicesForValuesLoadsChoiceListOnFirstCall()
    {
        $this->assertSame(
            self::$loader->loadChoicesForValues(self::$choiceValues, self::$value),
            self::$lazyChoiceList->getChoicesForValues(self::$choiceValues),
            'Choice list should not be reloaded.'
        );
    }

    public function testLoadChoicesForValuesDropsNonExistentChoices()
    {
        $this->assertSame([], self::$loader->loadChoicesForValues(['foo']));
    }

    public function testLoadValuesForChoicesLoadsChoiceListOnFirstCall()
    {
        $this->assertSame(
            self::$loader->loadValuesForChoices(self::$choices, self::$value),
            self::$lazyChoiceList->getValuesForChoices(self::$choices),
            'Choice list should not be reloaded.'
        );
    }

    public static function tearDownAfterClass(): void
    {
        self::$loader = null;
        self::$value = null;
        self::$choices = [];
        self::$choiceValues = [];
        self::$lazyChoiceList = null;
    }
}
