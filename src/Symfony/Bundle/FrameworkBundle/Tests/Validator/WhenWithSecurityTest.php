<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Tests\Validator;

use PHPUnit\Framework\TestCase;
use Symfony\Bundle\FrameworkBundle\Validator\ValidatorSecurityExpressionLanguageProvider;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\When;
use Symfony\Component\Validator\Constraints\WhenValidator;
use Symfony\Component\Validator\ConstraintValidatorFactory;
use Symfony\Component\Validator\ConstraintValidatorInterface;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Integration test: When constraint with security expression functions.
 */
class WhenWithSecurityTest extends TestCase
{
    public function testConstraintIsAppliedWhenGranted()
    {
        $authorizationChecker = $this->createStub(AuthorizationCheckerInterface::class);
        $authorizationChecker->method('isGranted')->willReturn(true);

        $violations = $this->createValidator($authorizationChecker)->validate('', new When(expression: 'is_granted("ROLE_ADMIN")', constraints: [new NotBlank()]));

        $this->assertCount(1, $violations);
    }

    public function testConstraintIsSkippedWhenNotGranted()
    {
        $authorizationChecker = $this->createStub(AuthorizationCheckerInterface::class);
        $authorizationChecker->method('isGranted')->willReturn(false);

        $violations = $this->createValidator($authorizationChecker)->validate('', new When(expression: 'is_granted("ROLE_ADMIN")', constraints: [new NotBlank()]));

        $this->assertCount(0, $violations);
    }

    private function createValidator(AuthorizationCheckerInterface $authorizationChecker): ValidatorInterface
    {
        $requestStack = new RequestStack();
        $requestStack->push(new Request());

        $expressionLanguage = new ExpressionLanguage();
        $expressionLanguage->registerProvider(new ValidatorSecurityExpressionLanguageProvider($authorizationChecker, $this->createStub(TokenStorageInterface::class), $requestStack));

        $whenValidator = new WhenValidator($expressionLanguage);

        $factory = new class($whenValidator) extends ConstraintValidatorFactory {
            public function __construct(private WhenValidator $whenValidator)
            {
            }

            public function getInstance(Constraint $constraint): ConstraintValidatorInterface
            {
                if ($constraint instanceof When) {
                    return $this->whenValidator;
                }

                return parent::getInstance($constraint);
            }
        };

        return Validation::createValidatorBuilder()
            ->setConstraintValidatorFactory($factory)
            ->getValidator();
    }
}
