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

use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints\GreaterThanValidator;
use Symfony\Component\Validator\Constraints\Positive;

/**
 * @author Jan Schädlich <jan.schaedlich@sensiolabs.de>
 */
class GreaterThanValidatorWithPositiveConstraintTest extends AbstractComparisonValidatorTestCase
{
    protected function createValidator(): GreaterThanValidator
    {
        return new GreaterThanValidator();
    }

    protected static function createConstraint(?array $options = null): Constraint
    {
        return new Positive($options);
    }

    public static function provideValidComparisons(): array
    {
        return [
            [2, 0],
            [2.5, 0],
            ['333', '0'],
            [null, 0],
        ];
    }

    public static function provideValidComparisonsToPropertyPath(): array
    {
        return [
            [6],
        ];
    }

    public static function provideInvalidComparisons(): array
    {
        return [
            [0, '0', 0, '0', 'int'],
            [-1, '-1', 0, '0', 'int'],
            [-2, '-2', 0, '0', 'int'],
            [-2.5, '-2.5', 0, '0', 'int'],
        ];
    }

    #[DataProvider('provideInvalidConstraintOptions')]
    public function testThrowsConstraintExceptionIfNoValueOrPropertyPath($options)
    {
        $this->markTestSkipped('Value option always set for Positive constraint.');
    }

    public function testThrowsConstraintExceptionIfBothValueAndPropertyPath()
    {
        $this->markTestSkipped('Value option is set for Positive constraint automatically');
    }

    public function testNoViolationOnNullObjectWithPropertyPath()
    {
        $this->markTestSkipped('PropertyPath option is not used in Positive constraint');
    }

    public function testInvalidValuePath()
    {
        $this->markTestSkipped('PropertyPath option is not used in Positive constraint');
    }

    #[DataProvider('provideValidComparisonsToPropertyPath')]
    public function testValidComparisonToPropertyPath($comparedValue)
    {
        $this->markTestSkipped('PropertyPath option is not used in Positive constraint');
    }

    public function testInvalidComparisonToPropertyPathAddsPathAsParameter()
    {
        $this->markTestSkipped('PropertyPath option is not used in Positive constraint');
    }
}
