<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Core\Authorization\Voter;

use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;

/**
 * CallableVoter delegate vote to the callable.
 *
 * Callable received the token, the subject and the attributes
 * This callable must return true, false, mixed to respectively
 * ACCESS_GRANTED, ACCESS_DENIED, or ACCESS_ABSTAIN.
 *
 * @author Anthony Moutte <instabledesign@gmail.com>
 */
class CallableVoter implements VoterInterface
{
    private $callable;

    public function __construct(callable $callable)
    {
        $this->callable = $callable;
    }

    /**
     * {@inheritdoc}
     */
    public function vote(TokenInterface $token, $subject, array $attributes)
    {
        $result = ($this->callable)($token, $subject, $attributes);

        if (false === $result) {
            return Voter::ACCESS_DENIED;
        }

        if (true === $result) {
            return Voter::ACCESS_GRANTED;
        }

        return Voter::ACCESS_ABSTAIN;
    }
}
