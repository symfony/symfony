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
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Security\Http\Attribute\IsGrantedContext;

/**
 * This voter allows using a closure as the attribute being voted on.
 *
 * @see IsGranted doc for the complete closure signature.
 *
 * @author Alexandre Daubois <alex.daubois@gmail.com>
 */
final class ClosureVoter implements CacheableVoterInterface
{
    public function __construct(
        private AuthorizationCheckerInterface $authorizationChecker,
    ) {
    }

    public function supportsAttribute(string $attribute): bool
    {
        return false;
    }

    public function supportsType(string $subjectType): bool
    {
        return true;
    }

    public function vote(TokenInterface $token, mixed $subject, array $attributes, ?Vote $vote = null): int
    {
        $context = new IsGrantedContext($token, $token->getUser(), $this->authorizationChecker);
        $failingClosures = [];
        $result = VoterInterface::ACCESS_ABSTAIN;
        foreach ($attributes as $attribute) {
            if (!$attribute instanceof \Closure) {
                continue;
            }

            $name = (new \ReflectionFunction($attribute))->name;
            $result = VoterInterface::ACCESS_DENIED;
            if ($attribute($context, $subject)) {
                $vote?->addReason(\sprintf('Closure %s returned true.', $name));

                return VoterInterface::ACCESS_GRANTED;
            }

            $failingClosures[] = $name;
        }

        if ($failingClosures) {
            $vote?->addReason(\sprintf('Closure%s %s returned false.', 1 < \count($failingClosures) ? 's' : '', implode(', ', $failingClosures)));
        }

        return $result;
    }
}
