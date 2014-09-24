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

use Symfony\Component\Validator\Constraints\False;
use Symfony\Component\Validator\Constraints\FalseValidator;

class FalseValidatorTest extends AbstractConstraintValidatorTest
{
    protected function createValidator()
    {
        return new FalseValidator();
    }

    public function testNullIsValid()
    {
        $this->validator->validate(null, new False());

        $this->assertNoViolation();
    }

    public function testFalseIsValid()
    {
        $this->validator->validate(false, new False());

        $this->assertNoViolation();
    }

    public function testTrueIsInvalid()
    {
        $constraint = new False(array(
            'message' => 'myMessage',
        ));

        $this->validator->validate(true, $constraint);

        $this->buildViolation('myMessage')
            ->setParameter('{{ value }}', 'true')
            ->assertRaised();
    }
}
