<?php

namespace Symfony\Component\Validator\Tests\Constraints;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints\L18n;
use Symfony\Component\Validator\Constraints\L18nValidator;
use Symfony\Component\Validator\ConstraintValidator;

class L18nValidatorTest extends AbstractConstraintValidatorTest
{
    /**
     * @param string $locale
     *
     * @return L18nValidator
     */
    protected function createValidator($locale = 'en')
    {
        $request = new Request();
        $request->setLocale($locale);
        $requestStack = new RequestStack();
        $requestStack->push($request);

        $validator = new L18nValidator($requestStack);

        return $validator;
    }

    public function testNoViolationsAreCreated()
    {
        $fakeValidator = new DontAddViolationValidator();

        $mockConstraint = $this->getMock('Symfony\Component\Validator\Constraint');
        $mockConstraint->method('validatedBy')->willReturn(
            get_class($fakeValidator)
        );

        $validator = $this->createValidator();
        $validator->initialize($this->context);

        $validator->validate(
            null,
            new L18n(
                array('locale' => 'en', 'value' => $mockConstraint)
            )
        );

        $this->assertCount(0, $this->context->getViolations());
    }

    public function testNoViolationIsCreatedForDifferentLocale()
    {
        $fakeValidator = new AddViolationValidator();

        $mockConstraint = $this->getMock('Symfony\Component\Validator\Constraint');
        $mockConstraint->method('validatedBy')->willReturn(
            get_class($fakeValidator)
        );

        $validator = $this->createValidator();
        $validator->initialize($this->context);

        $validator->validate(
            null,
            new L18n(
                array('locale' => 'sv', 'value' => $mockConstraint)
            )
        );

        $this->assertCount(0, $this->context->getViolations());
    }

    public function testViolationIsCreated()
    {
        $fakeValidator = new AddViolationValidator();

        $mockConstraint = $this->getMock('Symfony\Component\Validator\Constraint');
        $mockConstraint->method('validatedBy')->willReturn(
            get_class($fakeValidator)
        );

        $validator = $this->createValidator();
        $validator->initialize($this->context);

        $validator->validate(
            null,
            new L18n(
                array('locale' => 'en', 'value' => $mockConstraint)
            )
        );

        $this->assertCount(1, $this->context->getViolations());
    }

}

class AddViolationValidator extends ConstraintValidator
{
    public function validate($value, Constraint $constraint)
    {
        $this->context->addViolation('');
    }
}

class DontAddViolationValidator extends ConstraintValidator
{
    public function validate($value, Constraint $constraint){}
}
