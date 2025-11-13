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
 * RoleVoter votes if any attribute starts with a given prefix.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class RoleVoter implements CacheableVoterInterface
{
    public function __construct(
        private string $prefix = 'ROLE_',
    ) {
    }

    /**
     * @param Vote|null $vote Should be used to explain the vote
     */
    public function vote(TokenInterface $token, mixed $subject, array $attributes/* , ?Vote $vote = null */): int
    {
        $vote = 3 < \func_num_args() ? func_get_arg(3) : null;
        $result = VoterInterface::ACCESS_ABSTAIN;
        $roles = $this->extractRoles($token);
        $missingRoles = [];

        foreach ($attributes as $attribute) {
            if (!\is_string($attribute) || !str_starts_with($attribute, $this->prefix)) {
                continue;
            }

            $result = VoterInterface::ACCESS_DENIED;

            if (\in_array($attribute, $roles, true)) {
                $vote?->addReason(\sprintf('The user has %s.', $attribute));

                return VoterInterface::ACCESS_GRANTED;
            }

            $missingRoles[] = $attribute;
        }

        if (VoterInterface::ACCESS_DENIED === $result) {
            $vote?->addReason(\sprintf('The user doesn\'t have%s %s.', 1 < \count($missingRoles) ? ' any of' : '', implode(', ', $missingRoles)));
        }

        return $result;
    }

    public function supportsAttribute(string $attribute): bool
    {
        return str_starts_with($attribute, $this->prefix);
    }

    public function supportsType(string $subjectType): bool
    {
        return true;
    }

    protected function extractRoles(TokenInterface $token): array
    {
        return $token->getRoleNames();
    }
}
