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

use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\Extension\Core\ChoiceList\SimpleChoiceList;
use Symfony\Component\Form\Extension\Core\View\ChoiceView;
use Symfony\Component\Form\Tests\Extension\Core\ChoiceList\Fixtures\LazyChoiceListImpl;
use Symfony\Component\Form\Tests\Extension\Core\ChoiceList\Fixtures\LazyChoiceListInvalidImpl;

/**
 * @group legacy
 */
class LazyChoiceListTest extends TestCase
{
    /**
     * @var LazyChoiceListImpl
     */
    private $list;

    protected function setUp()
    {
        parent::setUp();

        $this->list = new LazyChoiceListImpl(new SimpleChoiceList(array(
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

    public function testGetChoices()
    {
        $this->assertSame(array(0 => 'a', 1 => 'b', 2 => 'c'), $this->list->getChoices());
    }

    public function testGetValues()
    {
        $this->assertSame(array(0 => 'a', 1 => 'b', 2 => 'c'), $this->list->getValues());
    }

    public function testGetPreferredViews()
    {
        $this->assertEquals(array(1 => new ChoiceView('b', 'b', 'B')), $this->list->getPreferredViews());
    }

    public function testGetRemainingViews()
    {
        $this->assertEquals(array(0 => new ChoiceView('a', 'a', 'A'), 2 => new ChoiceView('c', 'c', 'C')), $this->list->getRemainingViews());
    }

    public function testGetIndicesForChoices()
    {
        $choices = array('b', 'c');
        $this->assertSame(array(1, 2), $this->list->getIndicesForChoices($choices));
    }

    public function testGetIndicesForValues()
    {
        $values = array('b', 'c');
        $this->assertSame(array(1, 2), $this->list->getIndicesForValues($values));
    }

    public function testGetChoicesForValues()
    {
        $values = array('b', 'c');
        $this->assertSame(array('b', 'c'), $this->list->getChoicesForValues($values));
    }

    public function testGetValuesForChoices()
    {
        $choices = array('b', 'c');
        $this->assertSame(array('b', 'c'), $this->list->getValuesForChoices($choices));
    }

    /**
     * @expectedException \Symfony\Component\Form\Exception\InvalidArgumentException
     */
    public function testLoadChoiceListShouldReturnChoiceList()
    {
        $list = new LazyChoiceListInvalidImpl();

        $list->getChoices();
    }
}
