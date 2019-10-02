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

use Symfony\Component\ExpressionLanguage\ExpressionLanguage;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\ConstraintDefinitionException;
use Symfony\Component\Validator\Exception\LogicException;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

/**
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Bernhard Schussek <bschussek@symfony.com>
 */
class ExpressionValidator extends ConstraintValidator
{
    private $expressionLanguage;

    public function __construct(/*ExpressionLanguage */$expressionLanguage = null)
    {
        if (\func_num_args() > 1) {
            @trigger_error(sprintf('The "%s" instance should be passed as "%s" first argument instead of second argument since 4.4.', ExpressionLanguage::class, __METHOD__), E_USER_DEPRECATED);

            $expressionLanguage = func_get_arg(1);

            if (null !== $expressionLanguage && !$expressionLanguage instanceof ExpressionLanguage) {
                throw new \TypeError(sprintf('Argument 2 passed to %s() must be an instance of %s or null, %s given. Since 4.4, passing it as the second argument is deprecated and will trigger a deprecation. Pass it as the first argument instead.', __METHOD__, ExpressionLanguage::class, \is_object($expressionLanguage) ? \get_class($expressionLanguage) : \gettype($expressionLanguage)));
            }
        } elseif (null !== $expressionLanguage && !$expressionLanguage instanceof ExpressionLanguage) {
            @trigger_error(sprintf('The "%s" first argument must be an instance of "%s" or null since 4.4. "%s" given', __METHOD__, ExpressionLanguage::class, \is_object($expressionLanguage) ? \get_class($expressionLanguage) : \gettype($expressionLanguage)), E_USER_DEPRECATED);
        }

        if (($this->expressionLanguage = $expressionLanguage) instanceof ExpressionLanguage) {
            $this->addIsValidFunction();
        }
    }

    /**
     * {@inheritdoc}
     */
    public function validate($value, Constraint $constraint)
    {
        if (!$constraint instanceof Expression) {
            throw new UnexpectedTypeException($constraint, __NAMESPACE__.'\Expression');
        }

        $variables = $constraint->values;
        $variables['value'] = $value;
        $variables['this'] = $this->context->getObject();

        if (!$this->getExpressionLanguage()->evaluate($constraint->expression, $variables)) {
            $this->context->buildViolation($constraint->message)
                ->setParameter('{{ value }}', $this->formatValue($value, self::OBJECT_TO_STRING))
                ->setCode(Expression::EXPRESSION_FAILED_ERROR)
                ->addViolation();
        }
    }

    private function getExpressionLanguage()
    {
        if (!$this->expressionLanguage instanceof ExpressionLanguage) {
            if (!class_exists('Symfony\Component\ExpressionLanguage\ExpressionLanguage')) {
                throw new LogicException('Unable to use expressions as the Symfony ExpressionLanguage component is not installed.');
            }
            $this->expressionLanguage = new ExpressionLanguage();

            $this->addIsValidFunction();
        }

        return $this->expressionLanguage;
    }

    private function addIsValidFunction(): void
    {
        if ($this->expressionLanguage->hasFunction('is_valid')) {
            return;
        }

        $this->expressionLanguage->register('is_valid', function () {
            throw new LogicException('The "is_valid" function cannot be compiled.');
        }, function (array $variables, ...$arguments): bool {
            if (!$arguments) {
                throw new ConstraintDefinitionException('The "is_valid" function requires at least one argument.');
            }

            $isObject = \is_object($object = $this->context->getObject());

            $constraints = [];
            $properties = [];

            foreach ($arguments as $argument) {
                if ($argument instanceof Constraint) {
                    $constraints[] = $argument;

                    continue;
                }

                if (\is_array($argument)) {
                    foreach ($argument as $constraint) {
                        if (!$constraint instanceof Constraint) {
                            throw new ConstraintDefinitionException(sprintf('The "is_valid" function only accepts arrays that contain instances of "%s" exclusively, "%s" given.', Constraint::class, \is_object($constraint) ? \get_class($constraint) : \gettype($constraint)));
                        }

                        $constraints[] = $constraint;
                    }

                    continue;
                }

                if (\is_string($argument)) {
                    if (!$isObject) {
                        throw new ConstraintDefinitionException('The "is_valid" function only accepts strings that represent properties paths when validating an object.');
                    }

                    $properties[] = $argument;

                    continue;
                }

                throw new ConstraintDefinitionException(sprintf('The "is_valid" function only accepts instances of "%s", arrays of "%s", or strings that represent properties paths (when validating an object), "%s" given.', Constraint::class, Constraint::class, \is_object($argument) ? \get_class($argument) : \gettype($argument)));
            }

            if (!$constraints && !$properties) {
                return true;
            }

            $validator = $this->context->getValidator();

            if ($constraints) {
                if ($validator->validate($variables['value'], $constraints, $this->context->getGroup())->count()) {
                    return false;
                }
            }

            foreach ($properties as $property) {
                if ($validator->validateProperty($object, $property, $this->context->getGroup())->count()) {
                    return false;
                }
            }

            return true;
        });
    }
}
