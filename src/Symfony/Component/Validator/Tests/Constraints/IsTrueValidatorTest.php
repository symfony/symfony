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

use Symfony\Component\Validator\Constraints\IsTrue;
use Symfony\Component\Validator\Constraints\IsTrueValidator;
use Symfony\Component\Validator\Test\ConstraintValidatorTestCase;

class IsTrueValidatorTest extends ConstraintValidatorTestCase
{
    protected function createValidator(): IsTrueValidator
    {
        return new IsTrueValidator();
    }

    public function testNullIsValid()
    {
        $this->validator->validate(null, new IsTrue());

        $this->assertNoViolation();
    }

    public function testTrueIsValid()
    {
        $this->validator->validate(true, new IsTrue());

        $this->assertNoViolation();
    }

    public function testFalseIsInvalid()
    {
        $this->validator->validate(false, new IsTrue(message: 'myMessage'));

        $this->buildViolation('myMessage')
            ->setParameter('{{ value }}', 'false')
            ->setCode(IsTrue::NOT_TRUE_ERROR)
            ->assertRaised();
    }

    /**
     * @group legacy
     */
    public function testFalseIsInvalidDoctrineStyle()
    {
        $this->validator->validate(false, new IsTrue([
            'message' => 'myMessage',
        ]));

        $this->buildViolation('myMessage')
            ->setParameter('{{ value }}', 'false')
            ->setCode(IsTrue::NOT_TRUE_ERROR)
            ->assertRaised();
    }
}
