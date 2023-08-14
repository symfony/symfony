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

/**
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
abstract class AbstractChoiceListTestCase extends TestCase
{
    protected ChoiceListInterface $list;
    protected array $choices;
    protected array $values;
    protected array $structuredValues;
    protected array $keys;
    protected mixed $choice1;
    protected mixed $choice2;
    protected mixed $choice3;
    protected mixed $choice4;
    protected string $value1;
    protected string $value2;
    protected string $value3;
    protected string $value4;
    protected string $key1;
    protected string $key2;
    protected string $key3;
    protected string $key4;

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

    public function testGetChoices()
    {
        $this->assertSame($this->choices, $this->list->getChoices());
    }

    public function testGetValues()
    {
        $this->assertSame($this->values, $this->list->getValues());
    }

    public function testGetStructuredValues()
    {
        $this->assertSame($this->values, $this->list->getStructuredValues());
    }

    public function testGetOriginalKeys()
    {
        $this->assertSame($this->keys, $this->list->getOriginalKeys());
    }

    public function testGetChoicesForValues()
    {
        $values = [$this->value1, $this->value2];
        $this->assertSame([$this->choice1, $this->choice2], $this->list->getChoicesForValues($values));
    }

    public function testGetChoicesForValuesPreservesKeys()
    {
        $values = [5 => $this->value1, 8 => $this->value2];
        $this->assertSame([5 => $this->choice1, 8 => $this->choice2], $this->list->getChoicesForValues($values));
    }

    public function testGetChoicesForValuesPreservesOrder()
    {
        $values = [$this->value2, $this->value1];
        $this->assertSame([$this->choice2, $this->choice1], $this->list->getChoicesForValues($values));
    }

    public function testGetChoicesForValuesIgnoresNonExistingValues()
    {
        $values = [$this->value1, $this->value2, 'foobar'];
        $this->assertSame([$this->choice1, $this->choice2], $this->list->getChoicesForValues($values));
    }

    // https://github.com/symfony/symfony/issues/3446
    public function testGetChoicesForValuesEmpty()
    {
        $this->assertSame([], $this->list->getChoicesForValues([]));
    }

    public function testGetValuesForChoices()
    {
        $choices = [$this->choice1, $this->choice2];
        $this->assertSame([$this->value1, $this->value2], $this->list->getValuesForChoices($choices));
    }

    public function testGetValuesForChoicesPreservesKeys()
    {
        $choices = [5 => $this->choice1, 8 => $this->choice2];
        $this->assertSame([5 => $this->value1, 8 => $this->value2], $this->list->getValuesForChoices($choices));
    }

    public function testGetValuesForChoicesPreservesOrder()
    {
        $choices = [$this->choice2, $this->choice1];
        $this->assertSame([$this->value2, $this->value1], $this->list->getValuesForChoices($choices));
    }

    public function testGetValuesForChoicesIgnoresNonExistingChoices()
    {
        $choices = [$this->choice1, $this->choice2, 'foobar'];
        $this->assertSame([$this->value1, $this->value2], $this->list->getValuesForChoices($choices));
    }

    public function testGetValuesForChoicesEmpty()
    {
        $this->assertSame([], $this->list->getValuesForChoices([]));
    }

    public function testGetChoicesForValuesWithNull()
    {
        $values = $this->list->getValuesForChoices([null]);

        $this->assertNotEmpty($this->list->getChoicesForValues($values));
    }

    abstract protected function createChoiceList(): ChoiceListInterface;

    abstract protected function getChoices();

    abstract protected function getValues();
}
