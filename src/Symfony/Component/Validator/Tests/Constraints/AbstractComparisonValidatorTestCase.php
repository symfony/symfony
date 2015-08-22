<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Validator\Tests\Constraints;

use Symfony\Component\Intl\Util\IntlTestHelper;
use Symfony\Component\Validator\Constraint;

class ComparisonTest_Class
{
    protected $value;

    public function __construct($value)
    {
        $this->value = $value;
    }

    public function __toString()
    {
        return (string) $this->value;
    }
}

/**
 * @author Daniel Holmes <daniel@danielholmes.org>
 */
abstract class AbstractComparisonValidatorTestCase extends AbstractConstraintValidatorTest
{
    /**
     * @expectedException \Symfony\Component\Validator\Exception\ConstraintDefinitionException
     */
    public function testThrowsConstraintExceptionIfNoValueOrProperty()
    {
        $comparison = $this->createConstraint(array());

        $this->validator->validate('some value', $comparison);
    }

    /**
     * @dataProvider provideValidComparisons
     *
     * @param mixed $dirtyValue
     * @param mixed $comparisonValue
     */
    public function testValidComparisonToValue($dirtyValue, $comparisonValue)
    {
        $constraint = $this->createConstraint(array('value' => $comparisonValue));

        $this->validator->validate($dirtyValue, $constraint);

        $this->assertNoViolation();
    }

    /**
     * @return array
     */
    abstract public function provideValidComparisons();

    /**
     * @dataProvider provideInvalidComparisons
     *
     * @param mixed  $dirtyValue
     * @param mixed  $dirtyValueAsString
     * @param mixed  $comparedValue
     * @param mixed  $comparedValueString
     * @param string $comparedValueType
     */
    public function testInvalidComparisonToValue($dirtyValue, $dirtyValueAsString, $comparedValue, $comparedValueString, $comparedValueType)
    {
        // Conversion of dates to string differs between ICU versions
        // Make sure we have the correct version loaded
        if ($dirtyValue instanceof \DateTime) {
            IntlTestHelper::requireFullIntl($this);
        }

        $constraint = $this->createConstraint(array('value' => $comparedValue));
        $constraint->message = 'Constraint Message';

        $this->validator->validate($dirtyValue, $constraint);

        $this->buildViolation('Constraint Message')
            ->setParameter('{{ value }}', $dirtyValueAsString)
            ->setParameter('{{ compared_value }}', $comparedValueString)
            ->setParameter('{{ compared_value_type }}', $comparedValueType)
            ->assertRaised();
    }

    /**
     * @return array
     */
    abstract public function provideInvalidComparisons();

    /**
     * @param array $options Options for the constraint
     *
     * @return Constraint
     */
    abstract protected function createConstraint(array $options);
}
