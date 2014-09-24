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

use Symfony\Component\Validator\Constraints\True;
use Symfony\Component\Validator\Constraints\TrueValidator;

class TrueValidatorTest extends AbstractConstraintValidatorTest
{
    protected function createValidator()
    {
        return new TrueValidator();
    }

    public function testNullIsValid()
    {
        $this->validator->validate(null, new True());

        $this->assertNoViolation();
    }

    public function testTrueIsValid()
    {
        $this->validator->validate(true, new True());

        $this->assertNoViolation();
    }

    public function testFalseIsInvalid()
    {
        $constraint = new True(array(
            'message' => 'myMessage',
        ));

        $this->validator->validate(false, $constraint);

        $this->buildViolation('myMessage')
            ->setParameter('{{ value }}', 'false')
            ->assertRaised();
    }
}
