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
use Symfony\Component\Security\Core\Event\VoteEvent;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Decorates voter classes to send result events.
 *
 * @author Laurent VOULLEMIER <laurent.voullemier@gmail.com>
 *
 * @internal
 */
class TraceableVoter implements VoterInterface
{
    private $voter;
    private $eventDispatcher;

    public function __construct(VoterInterface $voter, EventDispatcherInterface $eventDispatcher)
    {
        $this->voter = $voter;
        $this->eventDispatcher = $eventDispatcher;
    }

    public function vote(TokenInterface $token, $subject, array $attributes): int
    {
        $result = $this->voter->vote($token, $subject, $attributes);

        if (false === \in_array($result, [self::ACCESS_DENIED, self::ACCESS_GRANTED, self::ACCESS_ABSTAIN], true)) {
            @trigger_error(
                sprintf('Return value of "%s::vote()" should always be of the type int since Symfony 5.0, %s returned.', \get_class($this->voter), \gettype($result)),
                \E_USER_DEPRECATED
            );
        }

        $this->eventDispatcher->dispatch(new VoteEvent($this->voter, $subject, $attributes, $result), 'debug.security.authorization.vote');

        return $result;
    }

    public function getDecoratedVoter(): VoterInterface
    {
        return $this->voter;
    }
}
