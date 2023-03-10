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
    public function getVote(TokenInterface $token, mixed $subject, array $attributes): Vote
    {
        // abstain vote by default in case none of the attributes are supported
        $vote = $this->abstain();

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
                $vote = $this->deny();
            }

            $decision = $this->voteOnAttribute($attribute, $subject, $token);
            if (\is_bool($decision)) {
                trigger_deprecation('symfony/security-core', '6.3', 'Returning a boolean in "%s::voteOnAttribute()" is deprecated, return an instance of "%s" instead.', static::class, Vote::class);
                $decision = $decision ? $this->grant() : $this->deny();
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
        trigger_deprecation('symfony/security-core', '6.3', 'Method "%s::vote()" has been deprecated, use "%s::getVote()" instead.', __CLASS__, __CLASS__);

        return $this->getVote($token, $subject, $attributes)->getAccess();
    }

    /**
     * Creates a granted vote.
     *
     * @param string|string[] $messages
     */
    protected function grant(string|array $messages = [], array $context = []): Vote
    {
        return Vote::createGranted($messages, $context);
    }

    /**
     * Creates an abstained vote.
     *
     * @param string|string[] $messages
     */
    protected function abstain(string|array $messages = [], array $context = []): Vote
    {
        return Vote::createAbstain($messages, $context);
    }

    /**
     * Creates a denied vote.
     *
     * @param string|string[] $messages
     */
    protected function deny(string|array $messages = [], array $context = []): Vote
    {
        return Vote::createDenied($messages, $context);
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
     *
     * @return Vote|bool Returning a boolean is deprecated since Symfony 6.3. Return a Vote object instead.
     */
    abstract protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): Vote|bool;
}
