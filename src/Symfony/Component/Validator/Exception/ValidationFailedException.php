<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Validator\Exception;

use Symfony\Component\Validator\ConstraintViolationListInterface;

/**
 * @author Jan Vernieuwe <jan.vernieuwe@phpro.be>
 */
class ValidationFailedException extends RuntimeException
{
    private ConstraintViolationListInterface $violations;
    private mixed $value;

    public function __construct(mixed $value, ConstraintViolationListInterface $violations)
    {
        $this->violations = $violations;
        $this->value = $value;
        parent::__construct($violations);
    }

    /**
     * @return mixed
     */
    public function getValue()
    {
        return $this->value;
    }

    public function getViolations(): ConstraintViolationListInterface
    {
        return $this->violations;
    }
}
