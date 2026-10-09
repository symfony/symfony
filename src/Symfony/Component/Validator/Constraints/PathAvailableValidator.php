<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Validator\Constraints;

use Symfony\Component\Routing\Exception\ResourceNotFoundException;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Exception\UnexpectedValueException;

/**
 * Validates whether a path is already used in the app's routes.
 */
class PathAvailableValidator extends ConstraintValidator
{
    public function __construct(private readonly RouterInterface $router)
    {
    }

    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof PathAvailable) {
            throw new UnexpectedTypeException($constraint, PathAvailable::class);
        }

        if (null === $value || '' === $value) {
            return;
        }

        if (!\is_scalar($value) && !$value instanceof \Stringable) {
            throw new UnexpectedValueException($value, 'string');
        }

        $value = (string) $value;
        $path = str_starts_with($value, '/') ? $value : '/'.$value;

        try {
            $this->router->match($path);
        } catch (ResourceNotFoundException) {
            return;
        }

        $this->context->buildViolation($constraint->message)
            ->setParameter('{{ path }}', $this->formatValue($value))
            ->addViolation();
    }
}
