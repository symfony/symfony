<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Core\Validator\Constraints;

use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Encoder\EncoderFactoryInterface;
use Symfony\Component\Security\Core\Encoder\PasswordEncoderInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\PasswordHasher\Hasher\PasswordHasherFactoryInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\ConstraintDefinitionException;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

class UserPasswordValidator extends ConstraintValidator
{
    private $tokenStorage;
    private $hasherFactory;

    /**
     * @param PasswordHasherFactoryInterface $hasherFactory
     */
    public function __construct(TokenStorageInterface $tokenStorage, $hasherFactory)
    {
        if ($hasherFactory instanceof EncoderFactoryInterface) {
           trigger_deprecation('symfony/security-core', '5.3', 'Passing a "%s" instance to the "%s" constructor is deprecated, use "%s" instead.', EncoderFactoryInterface::class, __CLASS__, PasswordHasherFactoryInterface::class);
        }

        $this->tokenStorage = $tokenStorage;
        $this->hasherFactory = $hasherFactory;
    }

    /**
     * {@inheritdoc}
     */
    public function validate($password, Constraint $constraint)
    {
        if (!$constraint instanceof UserPassword) {
            throw new UnexpectedTypeException($constraint, UserPassword::class);
        }

        if (null === $password || '' === $password) {
            $this->context->addViolation($constraint->message);

            return;
        }

        $user = $this->tokenStorage->getToken()->getUser();

        if (!$user instanceof UserInterface) {
            throw new ConstraintDefinitionException('The User object must implement the UserInterface interface.');
        }

        $hasher = $this->hasherFactory instanceof EncoderFactoryInterface ? $this->hasherFactory->getEncoder($user) : $this->hasherFactory->getPasswordHasher($user);

        if (null === $user->getPassword() || !($hasher instanceof PasswordEncoderInterface ? $hasher->isPasswordValid($user->getPassword(), $password, $user->getSalt()) : $hasher->verify($user->getPassword(), $password, $user->getSalt()))) {
            $this->context->addViolation($constraint->message);
        }
    }
}
