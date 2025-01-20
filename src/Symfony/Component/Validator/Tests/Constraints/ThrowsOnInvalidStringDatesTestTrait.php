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

use Symfony\Component\Validator\Constraints\AbstractComparison;
use Symfony\Component\Validator\Exception\ConstraintDefinitionException;

trait ThrowsOnInvalidStringDatesTestTrait
{
    /**
     * @dataProvider throwsOnInvalidStringDatesProvider
     */
    public function testThrowsOnInvalidStringDates(AbstractComparison $constraint, $expectedMessage, $value)
    {
        $this->expectException(ConstraintDefinitionException::class);
        $this->expectExceptionMessage($expectedMessage);

        $this->validator->validate($value, $constraint);
    }

    public static function throwsOnInvalidStringDatesProvider(): array
    {
        $constraint = static::createConstraint([
            'value' => 'foo',
        ]);

        return [
            [$constraint, \sprintf('The compared value "foo" could not be converted to a "DateTimeImmutable" instance in the "%s" constraint.', $constraint::class), new \DateTimeImmutable()],
            [$constraint, \sprintf('The compared value "foo" could not be converted to a "DateTime" instance in the "%s" constraint.', $constraint::class), new \DateTime()],
        ];
    }
}
