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

trait ValidComparisonToValueTrait
{
    /**
     * @dataProvider provideAllValidComparisons
     */
    public function testValidComparisonToValue($dirtyValue, $comparisonValue)
    {
        $constraint = $this->createConstraint(['value' => $comparisonValue]);

        $this->validator->validate($dirtyValue, $constraint);

        $this->assertNoViolation();
    }

    public static function provideAllValidComparisons(): array
    {
        // The provider runs before setUp(), so we need to manually fix
        // the default timezone
        $timezone = date_default_timezone_get();
        date_default_timezone_set('UTC');

        $comparisons = self::addPhp5Dot5Comparisons(static::provideValidComparisons());

        date_default_timezone_set($timezone);

        return $comparisons;
    }
}
