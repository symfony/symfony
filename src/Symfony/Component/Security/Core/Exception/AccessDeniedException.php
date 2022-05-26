<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Core\Exception;

use Symfony\Component\HttpKernel\Attribute\WithHttpStatus;
use Symfony\Component\Security\Core\Authorization\AccessDecision;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;

/**
 * AccessDeniedException is thrown when the account has not the required role.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
#[WithHttpStatus(403)]
class AccessDeniedException extends RuntimeException
{
    private array $attributes = [];
    private mixed $subject = null;
    private ?AccessDecision $accessDecision;

    public function __construct(string $message = 'Access Denied.', \Throwable $previous = null, int $code = 403)
    {
        parent::__construct($message, $code, $previous);
    }

    public function getAttributes(): array
    {
        return $this->attributes;
    }

    /**
     * @return void
     */
    public function setAttributes(array|string $attributes)
    {
        $this->attributes = (array) $attributes;
    }

    public function getSubject(): mixed
    {
        return $this->subject;
    }

    /**
     * @return void
     */
    public function setSubject(mixed $subject)
    {
        $this->subject = $subject;
    }

    /**
     * Sets an access decision and appends the denied reasons to the exception message.
     *
     * @return void
     */
    public function setAccessDecision(AccessDecision $accessDecision)
    {
        $this->accessDecision = $accessDecision;
        if (!$accessDecision->getDeniedVotes()) {
            return;
        }

        $messages = array_map(static function (Vote $vote) {
            return $vote->getMessage();
        }, $accessDecision->getDeniedVotes());

        if ($messages) {
            $this->message .= ' '.implode(' ', $messages);
        }
    }

    /**
     * Gets the access decision.
     */
    public function getAccessDecision(): ?AccessDecision
    {
        return $this->accessDecision;
    }
}
