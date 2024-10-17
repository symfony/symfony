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
    private ?AccessDecision $accessDecision = null;

    public function __construct(string $message = 'Access Denied.', ?\Throwable $previous = null, int $code = 403)
    {
        parent::__construct($message, $code, $previous);
    }

    public function getAttributes(): array
    {
        return $this->attributes;
    }

    public function setAttributes(array|string $attributes): void
    {
        $this->attributes = (array) $attributes;
    }

    public function getSubject(): mixed
    {
        return $this->subject;
    }

    public function setSubject(mixed $subject): void
    {
        $this->subject = $subject;
    }

    /**
     * Sets an access decision and appends the denied reasons to the exception message.
     */
    public function setAccessDecision(AccessDecision $accessDecision): void
    {
        $this->accessDecision = $accessDecision;
        if (!$deniedVotes = $accessDecision->getDeniedVotes()) {
            return;
        }

        $messages = array_map(static fn (Vote $vote): string => \sprintf('%s', $vote->getMessage()), $deniedVotes);

        if (!empty(array_filter($messages))) {
            $this->message .= \sprintf(\PHP_EOL.'Decision message%s "%s"', \count($messages) > 1 ? 's are' : ' is', implode('" and "', $messages));
        }
    }

    public function getAccessDecision(): ?AccessDecision
    {
        return $this->accessDecision;
    }
}
