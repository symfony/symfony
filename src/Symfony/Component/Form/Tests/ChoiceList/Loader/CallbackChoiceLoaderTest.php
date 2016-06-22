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

use Symfony\Component\Form\ChoiceList\LazyChoiceList;
use Symfony\Component\Form\ChoiceList\Loader\CallbackChoiceLoader;

/**
 * @author Jules Pietri <jules@heahprod.com>
 */
class CallbackChoiceLoaderTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var \Symfony\Component\Form\ChoiceList\Loader\CallbackChoiceLoader
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

    public static function setUpBeforeClass()
    {
        self::$loader = new CallbackChoiceLoader(function () {
            return self::$choices;
        });
        self::$value = function ($choice) {
            return isset($choice->value) ? $choice->value : null;
        };
        self::$choices = array(
            (object) array('value' => 'choice_one'),
            (object) array('value' => 'choice_two'),
        );
        self::$choiceValues = array('choice_one', 'choice_two');
        self::$lazyChoiceList = new LazyChoiceList(self::$loader, self::$value);
    }

    public function testLoadChoiceList()
    {
        $this->assertInstanceOf('\Symfony\Component\Form\ChoiceList\ChoiceListInterface', self::$loader->loadChoiceList(self::$value));
    }

    public function testLoadChoiceListOnlyOnce()
    {
        $loadedChoiceList = self::$loader->loadChoiceList(self::$value);

        $this->assertSame($loadedChoiceList, self::$loader->loadChoiceList(self::$value));
    }

    public function testLoadChoicesForValuesLoadsChoiceListOnFirstCall()
    {
        $this->assertSame(
            self::$loader->loadChoicesForValues(self::$choiceValues, self::$value),
            self::$lazyChoiceList->getChoicesForValues(self::$choiceValues),
            'Choice list should not be reloaded.'
        );
    }

    public function testLoadValuesForChoicesLoadsChoiceListOnFirstCall()
    {
        $this->assertSame(
            self::$loader->loadValuesForChoices(self::$choices, self::$value),
            self::$lazyChoiceList->getValuesForChoices(self::$choices),
            'Choice list should not be reloaded.'
        );
    }

    public static function tearDownAfterClass()
    {
        self::$loader = null;
        self::$value = null;
        self::$choices = array();
        self::$choiceValues = array();
        self::$lazyChoiceList = null;
    }
}
