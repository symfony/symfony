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

/**
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
abstract class AbstractChoiceListTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var \Symfony\Component\Form\Extension\Core\ChoiceList\ChoiceListInterface
     */
    protected $list;

    /**
     * @var array
     */
    protected $choices;

    /**
     * @var array
     */
    protected $values;

    /**
     * @var mixed
     */
    protected $choice1;

    /**
     * @var mixed
     */
    protected $choice2;

    /**
     * @var mixed
     */
    protected $choice3;

    /**
     * @var mixed
     */
    protected $choice4;

    /**
     * @var string
     */
    protected $value1;

    /**
     * @var string
     */
    protected $value2;

    /**
     * @var string
     */
    protected $value3;

    /**
     * @var string
     */
    protected $value4;

    protected function setUp()
    {
        parent::setUp();

        $this->list = $this->createChoiceList();

        $this->choices = $this->getChoices();
        $this->values = $this->getValues();

        // allow access to the individual entries without relying on their indices
        reset($this->choices);
        reset($this->values);

        for ($i = 1; $i <= 4; ++$i) {
            $this->{'choice'.$i} = current($this->choices);
            $this->{'value'.$i} = current($this->values);

            next($this->choices);
            next($this->values);
        }
    }

    public function testGetChoices()
    {
        $this->assertSame($this->choices, $this->list->getChoices());
    }

    public function testGetValues()
    {
        $this->assertSame($this->values, $this->list->getValues());
    }

    public function testGetChoicesForValues()
    {
        $values = array($this->value1, $this->value2);
        $this->assertSame(array($this->choice1, $this->choice2), $this->list->getChoicesForValues($values));
    }

    public function testGetChoicesForValuesPreservesKeys()
    {
        $values = array(5 => $this->value1, 8 => $this->value2);
        $this->assertSame(array(5 => $this->choice1, 8 => $this->choice2), $this->list->getChoicesForValues($values));
    }

    public function testGetChoicesForValuesPreservesOrder()
    {
        $values = array($this->value2, $this->value1);
        $this->assertSame(array($this->choice2, $this->choice1), $this->list->getChoicesForValues($values));
    }

    public function testGetChoicesForValuesIgnoresNonExistingValues()
    {
        $values = array($this->value1, $this->value2, 'foobar');
        $this->assertSame(array($this->choice1, $this->choice2), $this->list->getChoicesForValues($values));
    }

    // https://github.com/symfony/symfony/issues/3446
    public function testGetChoicesForValuesEmpty()
    {
        $this->assertSame(array(), $this->list->getChoicesForValues(array()));
    }

    public function testGetValuesForChoices()
    {
        $choices = array($this->choice1, $this->choice2);
        $this->assertSame(array($this->value1, $this->value2), $this->list->getValuesForChoices($choices));
    }

    public function testGetValuesForChoicesPreservesKeys()
    {
        $choices = array(5 => $this->choice1, 8 => $this->choice2);
        $this->assertSame(array(5 => $this->value1, 8 => $this->value2), $this->list->getValuesForChoices($choices));
    }

    public function testGetValuesForChoicesPreservesOrder()
    {
        $choices = array($this->choice2, $this->choice1);
        $this->assertSame(array($this->value2, $this->value1), $this->list->getValuesForChoices($choices));
    }

    public function testGetValuesForChoicesIgnoresNonExistingChoices()
    {
        $choices = array($this->choice1, $this->choice2, 'foobar');
        $this->assertSame(array($this->value1, $this->value2), $this->list->getValuesForChoices($choices));
    }

    public function testGetValuesForChoicesEmpty()
    {
        $this->assertSame(array(), $this->list->getValuesForChoices(array()));
    }

    /**
     * @return \Symfony\Component\Form\Extension\Core\ChoiceList\ChoiceListInterface
     */
    abstract protected function createChoiceList();

    abstract protected function getChoices();

    abstract protected function getValues();
}
