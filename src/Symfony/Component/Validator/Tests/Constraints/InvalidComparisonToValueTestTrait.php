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

trait InvalidComparisonToValueTestTrait
{
    /**
     * @dataProvider provideAllInvalidComparisons
     */
    public function testInvalidComparisonToValue($dirtyValue, $dirtyValueAsString, $comparedValue, $comparedValueString, $comparedValueType)
    {
        // Conversion of dates to string differs between ICU versions
        // Make sure we have the correct version loaded
        if ($dirtyValue instanceof \DateTimeInterface) {
            IntlTestHelper::requireIntl($this, '57.1');
        }

        $constraint = $this->createConstraint(['value' => $comparedValue]);
        $constraint->message = 'Constraint Message';

        $this->validator->validate($dirtyValue, $constraint);

        $this->buildViolation('Constraint Message')
            ->setParameter('{{ value }}', $dirtyValueAsString)
            ->setParameter('{{ compared_value }}', $comparedValueString)
            ->setParameter('{{ compared_value_type }}', $comparedValueType)
            ->setCode($this->getErrorCode())
            ->assertRaised();
    }

    public function testInvalidComparisonToPropertyPathAddsPathAsParameter()
    {
        [$dirtyValue, $dirtyValueAsString, $comparedValue, $comparedValueString, $comparedValueType] = current($this->provideAllInvalidComparisons());

        $constraint = $this->createConstraint(['propertyPath' => 'value']);
        $constraint->message = 'Constraint Message';

        $object = new ComparisonTest_Class($comparedValue);

        $this->setObject($object);

        $this->validator->validate($dirtyValue, $constraint);

        $this->buildViolation('Constraint Message')
            ->setParameter('{{ value }}', $dirtyValueAsString)
            ->setParameter('{{ compared_value }}', $comparedValueString)
            ->setParameter('{{ compared_value_path }}', 'value')
            ->setParameter('{{ compared_value_type }}', $comparedValueType)
            ->setCode($this->getErrorCode())
            ->assertRaised();
    }

    public static function provideAllInvalidComparisons(): array
    {
        // The provider runs before setUp(), so we need to manually fix
        // the default timezone
        $timezone = date_default_timezone_get();
        date_default_timezone_set('UTC');

        $comparisons = self::addPhp5Dot5Comparisons(static::provideInvalidComparisons());

        date_default_timezone_set($timezone);

        return $comparisons;
    }
}
