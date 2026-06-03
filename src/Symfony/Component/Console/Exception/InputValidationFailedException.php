<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Console\Exception;

use Symfony\Component\Validator\ConstraintViolationListInterface;

/**
 * @author Robin Chalas <robin.chalas@gmail.com>
 */
final class InputValidationFailedException extends RuntimeException
{
    public function __construct(
        string $message,
        private readonly ConstraintViolationListInterface $violations,
    ) {
        parent::__construct($message);
    }

    public function getViolations(): ConstraintViolationListInterface
    {
        return $this->violations;
    }
}
