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
 * Voter is an abstract default implementation of a voter.
 *
 * @author Roman Marintšenko <inoryy@gmail.com>
 * @author Grégoire Pineau <lyrixx@lyrixx.info>
 *
 * @template TAttribute of string
 * @template TSubject of mixed
 */
abstract class Voter implements VoterInterface, CacheableVoterInterface
{
    public function getVote(TokenInterface $token, mixed $subject, array $attributes): VoteInterface
    {
        // abstain vote by default in case none of the attributes are supported
        $vote = new Vote(VoterInterface::ACCESS_ABSTAIN);

        foreach ($attributes as $attribute) {
            try {
                if (!$this->supports($attribute, $subject)) {
                    continue;
                }
            } catch (\TypeError $e) {
                if (str_contains($e->getMessage(), 'supports(): Argument #1')) {
                    continue;
                }
                throw $e;
            }

            // as soon as at least one attribute is supported, default is to deny access
            if (!$vote->isDenied()) {
                $vote = new Vote(VoterInterface::ACCESS_DENIED);
            }

            $decision = $this->voteOnAttribute($attribute, $subject, $token);

            if (\is_bool($decision)) {
                $decision = new Vote($decision);
            }

            if ($decision->isGranted()) {
                // grant access as soon as at least one attribute returns a positive response
                return $decision;
            }

            if ('' !== $decisionMessage = $decision->getMessage()) {
                $vote->addMessage($decisionMessage);
            }
        }

        return $vote;
    }

    public function vote(TokenInterface $token, mixed $subject, array $attributes): int
    {
        return $this->getVote($token, $subject, $attributes)->getAccess();
    }

    /**
     * Return false if your voter doesn't support the given attribute. Symfony will cache
     * that decision and won't call your voter again for that attribute.
     */
    public function supportsAttribute(string $attribute): bool
    {
        return true;
    }

    /**
     * Return false if your voter doesn't support the given subject type. Symfony will cache
     * that decision and won't call your voter again for that subject type.
     *
     * @param string $subjectType The type of the subject inferred by `get_class()` or `get_debug_type()`
     */
    public function supportsType(string $subjectType): bool
    {
        return true;
    }

    /**
     * Determines if the attribute and subject are supported by this voter.
     *
     * @param mixed $subject The subject to secure, e.g. an object the user wants to access or any other PHP type
     *
     * @psalm-assert-if-true TSubject $subject
     * @psalm-assert-if-true TAttribute $attribute
     */
    abstract protected function supports(string $attribute, mixed $subject): bool;

    /**
     * Perform a single access check operation on a given attribute, subject and token.
     * It is safe to assume that $attribute and $subject already passed the "supports()" method check.
     *
     * @param TAttribute $attribute
     * @param TSubject   $subject
     */
    abstract protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): VoteInterface|bool;
}
