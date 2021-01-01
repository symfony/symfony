<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints\Composite;
use Symfony\Component\Validator\Exception\ValidationFailedException;
use Symfony\Component\Validator\Mapping\Loader\AbstractLoader;
use Symfony\Component\Validator\Validation;

/**
 * Validates service arguments using Validator component.
 */
final class ValidateConstructorArgumentsPass extends AbstractRecursivePass
{
    /** @var bool */
    private $throwExceptionOnValidationFailure;

    public function __construct(bool $throwExceptionOnValidationFailure = true)
    {
        $this->throwExceptionOnValidationFailure = $throwExceptionOnValidationFailure;
    }

    /**
     * {@inheritdoc}
     */
    protected function processValue($value, bool $isRoot = false)
    {
        if (!$value instanceof Definition || $value->hasErrors()) {
            return parent::processValue($value, $isRoot);
        }

        if (ServiceLocator::class === $value->getClass()) {
            return parent::processValue($value, $isRoot);
        }

        if (\count($value->getConstraints()) > 0) {
            $this->validate($value);
        }

        return parent::processValue($value, $isRoot);
    }

    private function validate(Definition $value): void
    {
        $serviceConstraints = $value->getConstraints();
        foreach ($serviceConstraints as $argumentName => $argumentConstraints) {
            $argumentValue = $value->getArgument($argumentName);

            $validatorConstraints = $this->getValidatorConstraints($argumentConstraints);
            $validator = Validation::createCallable(null, ...$validatorConstraints);
            try {
                $validator($argumentValue);
            } catch (ValidationFailedException $e) {
                if ($this->throwExceptionOnValidationFailure) {
                    throw $e;
                }

                $value->addError($e);
            }
        }
    }

    /**
     * @param mixed[] $rawConstraints Constraints definition, parsed from config file
     *
     * @return Constraint[]
     */
    private function getValidatorConstraints(array $rawConstraints): array
    {
        $constraintsList = [];
        foreach ($rawConstraints as $constraintName => $constraintValue) {
            $validatorConstraintClass = AbstractLoader::DEFAULT_NAMESPACE.$constraintName;

            if (is_subclass_of($validatorConstraintClass, Composite::class)) {
                $constraintValue = $this->getValidatorConstraints($constraintValue);
            }

            $constraintsList[] = new $validatorConstraintClass($constraintValue);
        }

        return $constraintsList;
    }
}
