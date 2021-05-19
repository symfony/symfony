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

class ValidationFailedException extends RuntimeException
{
    private $violations;
    private $value;

    public function __construct($value, ConstraintViolationListInterface $violations)
    {
        $this->violations = $violations;
        $this->value = $value;
        parent::__construct($violations);
    }

    public function getValue()
    {
        return $this->value;
    }

    public function getViolations(): ConstraintViolationListInterface
    {
        return $this->violations;
    }
}
