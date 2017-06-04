<?php

declare (strict_types = 1);

namespace Symfony\Component\Validator\Tests\Constraints;

use Egulias\EmailValidator\EmailValidator;
use Egulias\EmailValidator\Validation\DNSCheckValidation;
use Egulias\EmailValidator\Validation\EmailValidation;
use Egulias\EmailValidator\Validation\MultipleValidationWithAnd;
use Egulias\EmailValidator\Validation\NoRFCWarningsValidation;
use Egulias\EmailValidator\Validation\RFCValidation;
use Egulias\EmailValidator\Validation\SpoofCheckValidation;
use Symfony\Component\Validator\Constraints\EguliasEmail;
use Symfony\Component\Validator\Constraints\EguliasEmailValidator;
use Symfony\Component\Validator\Test\ConstraintValidatorTestCase;

class EguliasEmailValidatorTest extends ConstraintValidatorTestCase
{
    /**
     * @var EmailValidator|\PHPUnit_Framework_MockObject_MockObject
     */
    private $egulias;

    public static function setUpBeforeClass()
    {
        if (!interface_exists(EmailValidation::class)) {
            self::markTestSkipped('Package egulias/email-validator ^2.0 is not installed.');
        }
    }

    protected function createValidator()
    {
        $this->egulias = $this->createMock(EmailValidator::class);

        return new EguliasEmailValidator($this->egulias);
    }

    public function testNullIsValid()
    {
        $this->validator->validate(null, new EguliasEmail());

        $this->assertNoViolation();
    }

    public function testEmptyStringIsValid()
    {
        $this->validator->validate('', new EguliasEmail());

        $this->assertNoViolation();
    }

    public function testValidEvaluation()
    {
        $email = 'foo@example.com';

        $this->egulias
            ->expects($this->once())
            ->method('isValid')
            ->with(self::equalTo($email), self::isInstanceOf(MultipleValidationWithAnd::class))
            ->willReturn(true)
        ;

        $this->validator->validate($email, new EguliasEmail());

        $this->assertNoViolation();
    }

    public function testInvalidEvaluation()
    {
        $email = 'foo@example';

        $this->egulias
            ->expects($this->once())
            ->method('isValid')
            ->with(self::equalTo($email), self::isInstanceOf(MultipleValidationWithAnd::class))
            ->willReturn(false)
        ;

        $this->validator->validate($email, new EguliasEmail(['message' => 'error-message']));

        $this->buildViolation('error-message')
            ->setParameter('{{ value }}', '"'.$email.'"')
            ->setInvalidValue($email)
            ->setCode(EguliasEmail::INVALID_EMAIL)
            ->assertRaised()
        ;
    }

    public function constraintsOptionsProvider()
    {
        return [
            'default_options' => [[], [NoRFCWarningsValidation::class, DNSCheckValidation::class]],
            'suppress_rfc_warnings' => [['suppressRFCWarnings' => true], [RFCValidation::class, DNSCheckValidation::class]],
            'check_literal_spoof' => [['checkSpoof' => true], [NoRFCWarningsValidation::class, DNSCheckValidation::class, SpoofCheckValidation::class]],
            'concrete_validations' => [['validations' => [new DNSCheckValidation()]], [DNSCheckValidation::class]],
        ];
    }

    /**
     * @depends testValidEvaluation
     * @dataProvider constraintsOptionsProvider
     */
    public function testOptionsForValidations($options, $expected)
    {
        $email = 'foo@example.com';

        $reflection = new \ReflectionProperty(MultipleValidationWithAnd::class, 'validations');
        $reflection->setAccessible(true);

        $this->egulias
            ->expects($this->once())
            ->method('isValid')
            ->with(self::equalTo($email), self::callback(function (MultipleValidationWithAnd $value) use ($reflection, $expected) {
                $validations = array_map(function ($validation) {
                    return get_class($validation);
                }, $reflection->getValue($value));

                return 0 === count(array_diff($validations, $expected));
            }))
            ->willReturn(true)
        ;

        $this->validator->validate($email, new EguliasEmail($options));

        $this->assertNoViolation();
    }

    /**
     * @depends testValidEvaluation
     */
    public function testDefaultValidationMode()
    {
        $email = 'foo@example.com';

        $reflection = new \ReflectionProperty(MultipleValidationWithAnd::class, 'mode');
        $reflection->setAccessible(true);

        $this->egulias
            ->expects($this->once())
            ->method('isValid')
            ->with(self::equalTo($email), self::callback(function (MultipleValidationWithAnd $value) use ($reflection) {
                $mode = $reflection->getValue($value);

                return MultipleValidationWithAnd::STOP_ON_ERROR === $mode;
            }))
            ->willReturn(true)
        ;

        $this->validator->validate($email, new EguliasEmail());

        $this->assertNoViolation();
    }

    /**
     * @depends testValidEvaluation
     */
    public function testAlteringValidationMode()
    {
        $email = 'foo@example.com';

        $reflection = new \ReflectionProperty(MultipleValidationWithAnd::class, 'mode');
        $reflection->setAccessible(true);

        $this->egulias
            ->expects($this->once())
            ->method('isValid')
            ->with(self::equalTo($email), self::callback(function (MultipleValidationWithAnd $value) use ($reflection) {
                $mode = $reflection->getValue($value);

                return MultipleValidationWithAnd::ALLOW_ALL_ERRORS === $mode;
            }))
            ->willReturn(true)
        ;

        $this->validator->validate($email, new EguliasEmail([
            'validationMode' => MultipleValidationWithAnd::ALLOW_ALL_ERRORS,
        ]));

        $this->assertNoViolation();
    }
}
