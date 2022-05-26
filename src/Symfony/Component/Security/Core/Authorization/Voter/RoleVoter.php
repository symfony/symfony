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
    private string $prefix;

    public function __construct(string $prefix = 'ROLE_')
    {
        $this->prefix = $prefix;
    }

    public function getVote(TokenInterface $token, mixed $subject, array $attributes): Vote
    {
        $result = Vote::createAbstain();
        $roles = $this->extractRoles($token);

        foreach ($attributes as $attribute) {
            if (!\is_string($attribute) || !str_starts_with($attribute, $this->prefix)) {
                continue;
            }

            $result = Vote::createDenied();
            foreach ($roles as $role) {
                if ($attribute === $role) {
                    return Vote::createGranted();
                }
            }
        }

        return $result;
    }

    public function vote(TokenInterface $token, mixed $subject, array $attributes): int
    {
        trigger_deprecation('symfony/security-core', '6.2', 'Method "%s::vote()" has been deprecated, use "%s::getVote()" instead.', __CLASS__, __CLASS__);

        return $this->getVote($token, $subject, $attributes)->getAccess();
    }

    public function supportsAttribute(string $attribute): bool
    {
        return str_starts_with($attribute, $this->prefix);
    }

    public function supportsType(string $subjectType): bool
    {
        return true;
    }

    /**
     * @return array
     */
    protected function extractRoles(TokenInterface $token)
    {
        return $token->getRoleNames();
    }
}
