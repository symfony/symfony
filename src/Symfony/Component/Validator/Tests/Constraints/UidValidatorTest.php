<?php

namespace Symfony\Component\Validator\Tests\Constraints;

use Symfony\Component\Validator\Constraints\Uid;
use Symfony\Component\Validator\Constraints\UidValidator;
use Symfony\Component\Validator\Test\ConstraintValidatorTestCase;

class UidValidatorTest extends ConstraintValidatorTestCase
{
    protected function createValidator()
    {
        return new UidValidator();
    }

    public function testNullIsValid()
    {
        $this->validator->validate(null, new Uid());

        $this->assertNoViolation();
    }

    public function testEmptyStringIsValid()
    {
        $this->validator->validate('', new Uid());

        $this->assertNoViolation();
    }

    public function testValidUids()
    {
        $this->validator->validate('9b7541de-6f87-11ea-ab3c-9da9a81562fc', new Uid());
        $this->validator->validate('e576629b-ff34-3642-9c08-1f5219f0d45b', new Uid());
        $this->validator->validate('4126dbc1-488e-4f6e-aadd-775dcbac482e', new Uid());
        $this->validator->validate('18cdf3d3-ea1b-5b23-a9c5-40abd0e2df22', new Uid());
        $this->validator->validate('1ea6ecef-eb9a-66fe-b62b-957b45f17e43', new Uid());
        $this->validator->validate('01E4BYF64YZ97MDV6RH0HAMN6X', new Uid());

        $this->assertNoViolation();
    }

    public function testInvalidUid()
    {
        $value = 'foo';

        $constraint = new Uid([
            'message' => 'myMessage',
        ]);

        $this->validator->validate($value, $constraint);

        $this->buildViolation('myMessage')
            ->setParameter('{{ value }}', '"'.$value.'"')
            ->setCode(Uid::INVALID_UID_ERROR)
            ->assertRaised();
    }

    public function testInvalidUidForTypes()
    {
        $value = '9b7541de-6f87-11ea-ab3c-9da9a81562fc';

        $constraint = new Uid([
            'message' => 'myMessage',
            'types' => [Uid::UUID_V3]
        ]);

        $this->validator->validate($value, $constraint);

        $this->buildViolation('myMessage')
            ->setParameter('{{ value }}', '"'.$value.'"')
            ->setCode(Uid::INVALID_UID_ERROR)
            ->assertRaised();
    }
}
