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
     * @var array
     */
    protected $indices;

    /**
     * @var array
     */
    protected $labels;

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
     * @var int|string
     */
    protected $index1;

    /**
     * @var int|string
     */
    protected $index2;

    /**
     * @var int|string
     */
    protected $index3;

    /**
     * @var int|string
     */
    protected $index4;

    /**
     * @var string
     */
    protected $label1;

    /**
     * @var string
     */
    protected $label2;

    /**
     * @var string
     */
    protected $label3;

    /**
     * @var string
     */
    protected $label4;

    protected function setUp()
    {
        $this->iniSet('error_reporting', -1 & ~E_USER_DEPRECATED);

        parent::setUp();

        $this->list = $this->createChoiceList();

        $this->choices = $this->getChoices();
        $this->indices = $this->getIndices();
        $this->values = $this->getValues();
        $this->labels = $this->getLabels();

        // allow access to the individual entries without relying on their indices
        reset($this->choices);
        reset($this->indices);
        reset($this->values);
        reset($this->labels);

        for ($i = 1; $i <= 4; ++$i) {
            $this->{'choice'.$i} = current($this->choices);
            $this->{'index'.$i} = current($this->indices);
            $this->{'value'.$i} = current($this->values);
            $this->{'label'.$i} = current($this->labels);

            next($this->choices);
            next($this->indices);
            next($this->values);
            next($this->labels);
        }
    }

    public function testLegacyGetChoices()
    {
        $this->assertSame($this->choices, $this->list->getChoices());
    }

    public function testLegacyGetValues()
    {
        $this->assertSame($this->values, $this->list->getValues());
    }

    public function testLegacyGetIndicesForChoices()
    {
        $this->iniSet('error_reporting', -1 & ~E_USER_DEPRECATED);

        $choices = array($this->choice1, $this->choice2);
        $this->assertSame(array($this->index1, $this->index2), $this->list->getIndicesForChoices($choices));
    }

    public function testLegacyGetIndicesForChoicesPreservesKeys()
    {
        $this->iniSet('error_reporting', -1 & ~E_USER_DEPRECATED);

        $choices = array(5 => $this->choice1, 8 => $this->choice2);
        $this->assertSame(array(5 => $this->index1, 8 => $this->index2), $this->list->getIndicesForChoices($choices));
    }

    public function testLegacyGetIndicesForChoicesPreservesOrder()
    {
        $this->iniSet('error_reporting', -1 & ~E_USER_DEPRECATED);

        $choices = array($this->choice2, $this->choice1);
        $this->assertSame(array($this->index2, $this->index1), $this->list->getIndicesForChoices($choices));
    }

    public function testLegacyGetIndicesForChoicesIgnoresNonExistingChoices()
    {
        $this->iniSet('error_reporting', -1 & ~E_USER_DEPRECATED);

        $choices = array($this->choice1, $this->choice2, 'foobar');
        $this->assertSame(array($this->index1, $this->index2), $this->list->getIndicesForChoices($choices));
    }

    public function testLegacyGetIndicesForChoicesEmpty()
    {
        $this->iniSet('error_reporting', -1 & ~E_USER_DEPRECATED);

        $this->assertSame(array(), $this->list->getIndicesForChoices(array()));
    }

    public function testLegacyGetIndicesForValues()
    {
        $this->iniSet('error_reporting', -1 & ~E_USER_DEPRECATED);

        // values and indices are always the same
        $values = array($this->value1, $this->value2);
        $this->assertSame(array($this->index1, $this->index2), $this->list->getIndicesForValues($values));
    }

    public function testLegacyGetIndicesForValuesPreservesKeys()
    {
        $this->iniSet('error_reporting', -1 & ~E_USER_DEPRECATED);

        // values and indices are always the same
        $values = array(5 => $this->value1, 8 => $this->value2);
        $this->assertSame(array(5 => $this->index1, 8 => $this->index2), $this->list->getIndicesForValues($values));
    }

    public function testLegacyGetIndicesForValuesPreservesOrder()
    {
        $this->iniSet('error_reporting', -1 & ~E_USER_DEPRECATED);

        $values = array($this->value2, $this->value1);
        $this->assertSame(array($this->index2, $this->index1), $this->list->getIndicesForValues($values));
    }

    public function testLegacyGetIndicesForValuesIgnoresNonExistingValues()
    {
        $this->iniSet('error_reporting', -1 & ~E_USER_DEPRECATED);

        $values = array($this->value1, $this->value2, 'foobar');
        $this->assertSame(array($this->index1, $this->index2), $this->list->getIndicesForValues($values));
    }

    public function testLegacyGetIndicesForValuesEmpty()
    {
        $this->iniSet('error_reporting', -1 & ~E_USER_DEPRECATED);

        $this->assertSame(array(), $this->list->getIndicesForValues(array()));
    }

    public function testLegacyGetChoicesForValues()
    {
        $values = array($this->value1, $this->value2);
        $this->assertSame(array($this->choice1, $this->choice2), $this->list->getChoicesForValues($values));
    }

    public function testLegacyGetChoicesForValuesPreservesKeys()
    {
        $values = array(5 => $this->value1, 8 => $this->value2);
        $this->assertSame(array(5 => $this->choice1, 8 => $this->choice2), $this->list->getChoicesForValues($values));
    }

    public function testLegacyGetChoicesForValuesPreservesOrder()
    {
        $values = array($this->value2, $this->value1);
        $this->assertSame(array($this->choice2, $this->choice1), $this->list->getChoicesForValues($values));
    }

    public function testLegacyGetChoicesForValuesIgnoresNonExistingValues()
    {
        $values = array($this->value1, $this->value2, 'foobar');
        $this->assertSame(array($this->choice1, $this->choice2), $this->list->getChoicesForValues($values));
    }

    // https://github.com/symfony/symfony/issues/3446
    public function testLegacyGetChoicesForValuesEmpty()
    {
        $this->assertSame(array(), $this->list->getChoicesForValues(array()));
    }

    public function testLegacyGetValuesForChoices()
    {
        $choices = array($this->choice1, $this->choice2);
        $this->assertSame(array($this->value1, $this->value2), $this->list->getValuesForChoices($choices));
    }

    public function testLegacyGetValuesForChoicesPreservesKeys()
    {
        $choices = array(5 => $this->choice1, 8 => $this->choice2);
        $this->assertSame(array(5 => $this->value1, 8 => $this->value2), $this->list->getValuesForChoices($choices));
    }

    public function testLegacyGetValuesForChoicesPreservesOrder()
    {
        $choices = array($this->choice2, $this->choice1);
        $this->assertSame(array($this->value2, $this->value1), $this->list->getValuesForChoices($choices));
    }

    public function testLegacyGetValuesForChoicesIgnoresNonExistingChoices()
    {
        $choices = array($this->choice1, $this->choice2, 'foobar');
        $this->assertSame(array($this->value1, $this->value2), $this->list->getValuesForChoices($choices));
    }

    public function testLegacyGetValuesForChoicesEmpty()
    {
        $this->assertSame(array(), $this->list->getValuesForChoices(array()));
    }

    /**
     * @return \Symfony\Component\Form\Extension\Core\ChoiceList\ChoiceListInterface
     */
    abstract protected function createChoiceList();

    abstract protected function getChoices();

    abstract protected function getLabels();

    abstract protected function getValues();

    abstract protected function getIndices();
}
