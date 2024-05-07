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

use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Validator\ConstraintViolationListInterface;

/**
 * @author Tobias Nyholm <tobias.nyholm@gmail.com>
 */
class ValidationFailedException extends RuntimeException implements EnvelopeAwareExceptionInterface
{
    use EnvelopeAwareExceptionTrait;

    private ConstraintViolationListInterface $violations;
    private object $violatingMessage;

    public function __construct(object $violatingMessage, ConstraintViolationListInterface $violations, ?Envelope $envelope = null)
    {
        $this->violatingMessage = $violatingMessage;
        $this->violations = $violations;
        $this->envelope = $envelope;

        parent::__construct(sprintf('Message of type "%s" failed validation.', $this->violatingMessage::class));
    }

    /**
     * @return object
     */
    public function getViolatingMessage()
    {
        return $this->violatingMessage;
    }

    public function getViolations(): ConstraintViolationListInterface
    {
        return $this->violations;
    }
}
