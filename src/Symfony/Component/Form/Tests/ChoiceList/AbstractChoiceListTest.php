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

/**
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
abstract class AbstractChoiceListTest extends TestCase
{
    /**
     * @var \Symfony\Component\Form\ChoiceList\ChoiceListInterface
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
     * @var array
     */
    protected $structuredValues;

    /**
     * @var array
     */
    protected $keys;

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

    /**
     * @var string
     */
    protected $key1;

    /**
     * @var string
     */
    protected $key2;

    /**
     * @var string
     */
    protected $key3;

    /**
     * @var string
     */
    protected $key4;

    protected function setUp(): void
    {
        parent::setUp();

        $this->list = $this->createChoiceList();

        $choices = $this->getChoices();

        $this->values = $this->getValues();
        $this->structuredValues = array_combine(array_keys($choices), $this->values);
        $this->choices = array_combine($this->values, $choices);
        $this->keys = array_combine($this->values, array_keys($choices));

        // allow access to the individual entries without relying on their indices
        reset($this->choices);
        reset($this->values);
        reset($this->keys);

        for ($i = 1; $i <= 4; ++$i) {
            $this->{'choice'.$i} = current($this->choices);
            $this->{'value'.$i} = current($this->values);
            $this->{'key'.$i} = current($this->keys);

            next($this->choices);
            next($this->values);
            next($this->keys);
        }
    }

    public function testGetChoices(): void
    {
        $this->assertSame($this->choices, $this->list->getChoices());
    }

    public function testGetValues(): void
    {
        $this->assertSame($this->values, $this->list->getValues());
    }

    public function testGetStructuredValues(): void
    {
        $this->assertSame($this->values, $this->list->getStructuredValues());
    }

    public function testGetOriginalKeys(): void
    {
        $this->assertSame($this->keys, $this->list->getOriginalKeys());
    }

    public function testGetChoicesForValues(): void
    {
        $values = array($this->value1, $this->value2);
        $this->assertSame(array($this->choice1, $this->choice2), $this->list->getChoicesForValues($values));
    }

    public function testGetChoicesForValuesPreservesKeys(): void
    {
        $values = array(5 => $this->value1, 8 => $this->value2);
        $this->assertSame(array(5 => $this->choice1, 8 => $this->choice2), $this->list->getChoicesForValues($values));
    }

    public function testGetChoicesForValuesPreservesOrder(): void
    {
        $values = array($this->value2, $this->value1);
        $this->assertSame(array($this->choice2, $this->choice1), $this->list->getChoicesForValues($values));
    }

    public function testGetChoicesForValuesIgnoresNonExistingValues(): void
    {
        $values = array($this->value1, $this->value2, 'foobar');
        $this->assertSame(array($this->choice1, $this->choice2), $this->list->getChoicesForValues($values));
    }

    // https://github.com/symfony/symfony/issues/3446
    public function testGetChoicesForValuesEmpty(): void
    {
        $this->assertSame(array(), $this->list->getChoicesForValues(array()));
    }

    public function testGetValuesForChoices(): void
    {
        $choices = array($this->choice1, $this->choice2);
        $this->assertSame(array($this->value1, $this->value2), $this->list->getValuesForChoices($choices));
    }

    public function testGetValuesForChoicesPreservesKeys(): void
    {
        $choices = array(5 => $this->choice1, 8 => $this->choice2);
        $this->assertSame(array(5 => $this->value1, 8 => $this->value2), $this->list->getValuesForChoices($choices));
    }

    public function testGetValuesForChoicesPreservesOrder(): void
    {
        $choices = array($this->choice2, $this->choice1);
        $this->assertSame(array($this->value2, $this->value1), $this->list->getValuesForChoices($choices));
    }

    public function testGetValuesForChoicesIgnoresNonExistingChoices(): void
    {
        $choices = array($this->choice1, $this->choice2, 'foobar');
        $this->assertSame(array($this->value1, $this->value2), $this->list->getValuesForChoices($choices));
    }

    public function testGetValuesForChoicesEmpty(): void
    {
        $this->assertSame(array(), $this->list->getValuesForChoices(array()));
    }

    public function testGetChoicesForValuesWithNull(): void
    {
        $values = $this->list->getValuesForChoices(array(null));

        $this->assertNotEmpty($this->list->getChoicesForValues($values));
    }

    /**
     * @return \Symfony\Component\Form\ChoiceList\ChoiceListInterface
     */
    abstract protected function createChoiceList(): \Symfony\Component\Form\ChoiceList\ChoiceListInterface;

    abstract protected function getChoices(): void;

    abstract protected function getValues(): void;
}
