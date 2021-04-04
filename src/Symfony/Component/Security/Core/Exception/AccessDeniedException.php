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

use Symfony\Component\Security\Core\Authorization\AccessDecision;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;

/**
 * AccessDeniedException is thrown when the account has not the required role.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class AccessDeniedException extends RuntimeException
{
    private $attributes = [];
    private $subject;
    /** @var AccessDecision */
    private $accessDecision;

    public function __construct(string $message = 'Access Denied.', \Throwable $previous = null)
    {
        parent::__construct($message, 403, $previous);
    }

    /**
     * @return array
     */
    public function getAttributes()
    {
        return $this->attributes;
    }

    /**
     * @param array|string $attributes
     */
    public function setAttributes($attributes)
    {
        $this->attributes = (array) $attributes;
    }

    /**
     * @return mixed
     */
    public function getSubject()
    {
        return $this->subject;
    }

    /**
     * @param mixed $subject
     */
    public function setSubject($subject)
    {
        $this->subject = $subject;
    }

    /**
     * Sets an access decision and appends the denied reasons to the exception message.
     */
    public function setAccessDecision(AccessDecision $accessDecision)
    {
        $this->accessDecision = $accessDecision;
        $reasons = array_map(function (Vote $vote) { return $vote->getReason(); }, $this->accessDecision->getDeniedVotes());
        $this->message .= rtrim(' '.implode(' ', $reasons));
    }

    public function getAccessDecision(): AccessDecision
    {
        return $this->accessDecision;
    }
}
