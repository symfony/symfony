<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Tests\Extension\Core\ChoiceList;

use Symfony\Component\Form\Extension\Core\ChoiceList\SimpleChoiceList;
use Symfony\Component\Form\Extension\Core\ChoiceList\LazyChoiceList;
use Symfony\Component\Form\Extension\Core\View\ChoiceView;

/**
 * @group legacy
 */
class LazyChoiceListTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var LazyChoiceListTest_Impl
     */
    private $list;

    protected function setUp()
    {
        parent::setUp();

        $this->list = new LazyChoiceListTest_Impl(new SimpleChoiceList(array(
            'a' => 'A',
            'b' => 'B',
            'c' => 'C',
        ), array('b')));
    }

    protected function tearDown()
    {
        parent::tearDown();

        $this->list = null;
    }

    public function testLegacyGetChoices()
    {
        $this->assertSame(array(0 => 'a', 1 => 'b', 2 => 'c'), $this->list->getChoices());
    }

    public function testLegacyGetValues()
    {
        $this->assertSame(array(0 => 'a', 1 => 'b', 2 => 'c'), $this->list->getValues());
    }

    public function testLegacyGetPreferredViews()
    {
        $this->assertEquals(array(1 => new ChoiceView('b', 'b', 'B')), $this->list->getPreferredViews());
    }

    public function testLegacyGetRemainingViews()
    {
        $this->assertEquals(array(0 => new ChoiceView('a', 'a', 'A'), 2 => new ChoiceView('c', 'c', 'C')), $this->list->getRemainingViews());
    }

    /**
     * @group legacy
     */
    public function testLegacyGetIndicesForChoices()
    {
        $this->iniSet('error_reporting', -1 & ~E_USER_DEPRECATED);

        $choices = array('b', 'c');
        $this->assertSame(array(1, 2), $this->list->getIndicesForChoices($choices));
    }

    /**
     * @group legacy
     */
    public function testLegacyGetIndicesForValues()
    {
        $this->iniSet('error_reporting', -1 & ~E_USER_DEPRECATED);

        $values = array('b', 'c');
        $this->assertSame(array(1, 2), $this->list->getIndicesForValues($values));
    }

    public function testLegacyGetChoicesForValues()
    {
        $values = array('b', 'c');
        $this->assertSame(array('b', 'c'), $this->list->getChoicesForValues($values));
    }

    public function testLegacyGetValuesForChoices()
    {
        $choices = array('b', 'c');
        $this->assertSame(array('b', 'c'), $this->list->getValuesForChoices($choices));
    }

    /**
     * @expectedException \Symfony\Component\Form\Exception\InvalidArgumentException
     */
    public function testLegacyLoadChoiceListShouldReturnChoiceList()
    {
        $list = new LazyChoiceListTest_InvalidImpl();

        $list->getChoices();
    }
}

class LazyChoiceListTest_Impl extends LazyChoiceList
{
    private $choiceList;

    public function __construct($choiceList)
    {
        $this->choiceList = $choiceList;
    }

    protected function loadChoiceList()
    {
        return $this->choiceList;
    }
}

class LazyChoiceListTest_InvalidImpl extends LazyChoiceList
{
    protected function loadChoiceList()
    {
        return new \stdClass();
    }
}
