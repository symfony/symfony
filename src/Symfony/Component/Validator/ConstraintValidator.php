<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Validator;

use Symfony\Component\Validator\Context\ExecutionContextInterface as NewExecutionContextInterface;

/**
 * Base class for constraint validators
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 *
 * @api
 */
abstract class ConstraintValidator implements ConstraintValidatorInterface
{
    /**
     * @var ExecutionContextInterface|NewExecutionContextInterface
     */
    protected $context;

    /**
     * {@inheritdoc}
     */
    public function initialize($context)
    {
        if (!$context instanceof NewExecutionContextInterface && !$context instanceof ExecutionContextInterface) {
            throw new \InvalidArgumentException('Context must be instance of Symfony\Component\Validator\Context\ExecutionContextInterface or Symfony\Component\Validator\ExecutionContextInterface');
        }
        $this->context = $context;
    }
}
