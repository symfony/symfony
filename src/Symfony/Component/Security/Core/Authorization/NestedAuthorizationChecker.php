<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Core\Authorization;

use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Decides each check an expression or a closure makes in a decision of its own.
 *
 * The voter of the expression or the closure then knows which checks a re-authentication could grant, and reports the reasons they gave.
 *
 * @internal
 */
final class NestedAuthorizationChecker implements AuthorizationCheckerInterface, GuestAuthorizationCheckerInterface
{
    /**
     * The first checked attribute whose denial requested a re-authentication.
     */
    public private(set) ?string $reAuthenticationAttribute = null;

    /**
     * @var list<Vote>
     */
    private array $votes = [];

    public function __construct(
        private readonly AuthorizationCheckerInterface $authorizationChecker,
    ) {
    }

    public function isGranted(mixed $attribute, mixed $subject = null, ?AccessDecision $accessDecision = null): bool
    {
        if (null !== $accessDecision) {
            return $this->authorizationChecker->isGranted($attribute, $subject, $accessDecision);
        }

        $accessDecision = new AccessDecision();
        $isGranted = $this->authorizationChecker->isGranted($attribute, $subject, $accessDecision);
        array_push($this->votes, ...$accessDecision->votes);

        if ($isGranted) {
            return true;
        }

        // like the firewall does, only the voter of the checked attribute can request a re-authentication for it
        foreach ($accessDecision->votes as $vote) {
            if (VoterInterface::ACCESS_DENIED === $vote->result && $attribute === $vote->reAuthenticationAttribute) {
                $this->reAuthenticationAttribute ??= $attribute;
            }
        }

        return false;
    }

    public function isGrantedForUser(?UserInterface $user, mixed $attribute, mixed $subject = null, ?AccessDecision $accessDecision = null): bool
    {
        if (!$this->authorizationChecker instanceof GuestAuthorizationCheckerInterface) {
            throw new \LogicException(\sprintf('"%s" cannot check the access of another user.', get_debug_type($this->authorizationChecker)));
        }

        return $this->authorizationChecker->isGrantedForUser($user, $attribute, $subject, $accessDecision);
    }

    /**
     * Adds to the given vote the reasons the checks gave for the same outcome, as the decision reports them.
     */
    public function addReasons(Vote $vote, bool $isGranted): void
    {
        $result = $isGranted ? VoterInterface::ACCESS_GRANTED : VoterInterface::ACCESS_DENIED;

        foreach ($this->votes as $checkVote) {
            if ($result !== $checkVote->result) {
                continue;
            }

            foreach ($checkVote->reasons as $reason) {
                $vote->addReason($reason);
            }
        }
    }
}
