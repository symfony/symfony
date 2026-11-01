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
 * Grants access or abstains, never denies, so that voters sharing an attribute don't block each other under the "unanimous" strategy.
 *
 * @template TAttribute of string
 * @template TSubject of mixed
 */
abstract class GrantingVoter implements CacheableVoterInterface
{
    final public function vote(TokenInterface $token, mixed $subject, array $attributes, ?Vote $vote = null): int
    {
        // use `::class` to handle anonymous classes, as the AccessDecisionManager does
        if (!$this->supportsType(\is_object($subject) ? $subject::class : get_debug_type($subject))) {
            return self::ACCESS_ABSTAIN;
        }

        foreach ($attributes as $attribute) {
            if (\is_string($attribute) && $this->supportsAttribute($attribute) && $this->grants($attribute, $subject, $token, $vote)) {
                return self::ACCESS_GRANTED;
            }
        }

        return self::ACCESS_ABSTAIN;
    }

    /**
     * Returns false if the voter doesn't vote on the attribute, Symfony caches that answer.
     *
     * @psalm-assert-if-true TAttribute $attribute
     */
    abstract public function supportsAttribute(string $attribute): bool;

    /**
     * Returns false if the voter doesn't vote on subjects of that type, Symfony caches that answer.
     *
     * @param string $subjectType The type of the subject inferred by `get_class()` or `get_debug_type()`
     */
    abstract public function supportsType(string $subjectType): bool;

    /**
     * Returns whether the token is granted the attribute on the subject, the voter abstains otherwise.
     *
     * @param TAttribute $attribute An attribute accepted by supportsAttribute()
     * @param TSubject   $subject   A subject whose type is accepted by supportsType()
     * @param Vote|null  $vote      Should be used to explain the vote
     */
    abstract protected function grants(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool;
}
