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
     * @var ExecutionContextInterface
     */
    protected $context;

    /**
     * {@inheritdoc}
     */
    public function initialize(ExecutionContextInterface $context)
    {
        $this->context = $context;
    }
}
