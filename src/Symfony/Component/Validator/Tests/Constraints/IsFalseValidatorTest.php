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

use Symfony\Component\Validator\Constraints\IsFalse;
use Symfony\Component\Validator\Constraints\IsFalseValidator;
use Symfony\Component\Validator\Test\ConstraintValidatorTestCase;

class IsFalseValidatorTest extends ConstraintValidatorTestCase
{
    protected function createValidator(): IsFalseValidator
    {
        return new IsFalseValidator();
    }

    public function testNullIsValid()
    {
        $this->validator->validate(null, new IsFalse());

        $this->assertNoViolation();
    }

    public function testFalseIsValid()
    {
        $this->validator->validate(false, new IsFalse());

        $this->assertNoViolation();
    }

    public function testTrueIsInvalid()
    {
        $this->validator->validate(true, new IsFalse(message: 'myMessage'));

        $this->buildViolation('myMessage')
            ->setParameter('{{ value }}', 'true')
            ->setCode(IsFalse::NOT_FALSE_ERROR)
            ->assertRaised();
    }
}
