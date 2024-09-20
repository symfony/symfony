<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Validator\Tests\Test;

use PHPUnit\Framework\ExpectationFailedException;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints\DateTime;
use Symfony\Component\Validator\Constraints\NotNull;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Test\ConstraintValidatorTestCase;

class ConstraintValidatorTestCaseTest extends ConstraintValidatorTestCase
{
    protected function createValidator(): TestCustomValidator
    {
        return new TestCustomValidator();
    }

    public function testAssertingContextualValidatorRemainingExpectationsThrow()
    {
        $this->expectValidateValueAt(0, 'k1', 'ccc', [
            new NotNull(),
        ]);
        $this->expectValidateValueAt(1, 'k2', 'ccc', [
            new DateTime(),
        ]);

        $this->validator->validate('ccc', $this->constraint);

        $contextualValidator = $this->context->getValidator()->inContext($this->context);
        // Simulate __destruct to assert it throws
        try {
            $contextualValidator->__destruct();
            $this->fail();
        } catch (ExpectationFailedException $e) {
        }

        // Actually fulfill expectations so real __destruct doesn't throw
        $contextualValidator
            ->atPath('k2')
            ->validate('ccc', [
                new DateTime(),
            ]);
    }
}

class TestCustomValidator extends ConstraintValidator
{
    public function validate($value, Constraint $constraint): void
    {
        $validator = $this->context
            ->getValidator()
            ->inContext($this->context);

        $validator
            ->atPath('k1')
            ->validate($value, [
                new NotNull(),
            ]);
    }
}
