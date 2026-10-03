<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Validator\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\EmailValidator;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\ConstraintValidatorFactory;
use Symfony\Component\Validator\ConstraintValidatorFactoryInterface;
use Symfony\Component\Validator\ConstraintValidatorInterface;
use Symfony\Component\Validator\Exception\ValidationFailedException;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class ValidationPipeTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (\PHP_VERSION_ID < 80500) {
            self::markTestSkipped('Pipe operator requires PHP 8.5.');
        }
    }

    public function testPipedValidationPasses()
    {
        $email = ('em..il@t.com'
                |> new NotBlank()
                |> new Length(min: 5, max: 15)
                |> new Email() // passes - non-strict by default
        )->validOrFail();

        $this->assertEquals('em..il@t.com', $email);
    }

    public function testPipedValidationThrowsExceptionOfTheFirstFailedConstraint()
    {
        $strictValidator = $this->strictValidator();

        try {
            ('em..il@t.com'
                    |> new NotBlank() // passes
                    |> new Email() // fails - strict
                    |> new Length(min: 2000) // isn't reached
            )->validOrFail($strictValidator);

            $this->fail('A ValidationFailedException should have been thrown.');
        } catch (ValidationFailedException $e) {
            $violations = $e->getViolations();

            $this->assertCount(1, $violations);
            $emailViolation = $violations->get(0);

            self::assertSame('This value is not a valid email address.', $emailViolation->getMessage());
        }
    }

    private function strictValidator(): ValidatorInterface
    {
        return Validation::createValidatorBuilder()
            ->setConstraintValidatorFactory($this->strictConstraintValidatorFactory())
            ->getValidator();
    }

    private function strictConstraintValidatorFactory(): ConstraintValidatorFactoryInterface
    {
        return new class implements ConstraintValidatorFactoryInterface {
            public function getInstance(Constraint $constraint): ConstraintValidatorInterface
            {
                if ($constraint instanceof Email) {
                    return new EmailValidator(Email::VALIDATION_MODE_STRICT);
                }

                return new ConstraintValidatorFactory()->getInstance($constraint);
            }
        };
    }
}
