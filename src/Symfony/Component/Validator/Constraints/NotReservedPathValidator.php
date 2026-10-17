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

use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\LogicException;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Exception\UnexpectedValueException;

/**
 * Validates whether a path is already used by one of the app's static routes.
 */
class NotReservedPathValidator extends ConstraintValidator
{
    public function __construct(private readonly ?RouterInterface $router = null)
    {
    }

    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof NotReservedPath) {
            throw new UnexpectedTypeException($constraint, NotReservedPath::class);
        }

        if (null === $value || '' === $value) {
            return;
        }

        if (!\is_scalar($value) && !$value instanceof \Stringable) {
            throw new UnexpectedValueException($value, 'string');
        }

        if (!$this->router) {
            throw new LogicException(\sprintf('The "%s" constraint requires a router.', NotReservedPath::class));
        }

        $value = (string) $value;
        $path = str_starts_with($value, '/') ? $value : '/'.$value;

        foreach ($this->router->getRouteCollection() as $route) {
            // routes with placeholders are skipped: a "/{slug}" route would match any value
            if ($path !== $route->getPath() || $route->compile()->getPathVariables()) {
                continue;
            }

            $this->context->buildViolation($constraint->message)
                ->setParameter('{{ path }}', $this->formatValue($value))
                ->addViolation();

            return;
        }
    }
}
