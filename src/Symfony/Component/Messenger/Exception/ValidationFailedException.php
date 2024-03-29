<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Exception;

use Symfony\Component\Validator\ConstraintViolationListInterface;

/**
 * @author Tobias Nyholm <tobias.nyholm@gmail.com>
 */
class ValidationFailedException extends RuntimeException
{
    public function __construct(
        private object $violatingMessage,
        private ConstraintViolationListInterface $violations,
    ) {
        parent::__construct(sprintf('Message of type "%s" failed validation.', $this->violatingMessage::class));
    }

    public function getViolatingMessage(): object
    {
        return $this->violatingMessage;
    }

    public function getViolations(): ConstraintViolationListInterface
    {
        return $this->violations;
    }
}
