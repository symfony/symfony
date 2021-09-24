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
 * @author Dany Maillard <danymaillard93b@gmail.com>
 */
abstract class Voter implements VoterInterface
{
    /**
     * {@inheritdoc}
     */
    public function vote(TokenInterface $token, $subject, array $attributes)
    {
        // abstain vote by default in case none of the attributes are supported
        $vote = $this->abstain();

        foreach ($attributes as $attribute) {
            try {
                if (!$this->supports($attribute, $subject)) {
                    continue;
                }
            } catch (\TypeError $e) {
                if (\PHP_VERSION_ID < 80000) {
                    if (0 === strpos($e->getMessage(), 'Argument 1 passed to')
                        && false !== strpos($e->getMessage(), '::supports() must be of the type string')) {
                        continue;
                    }
                } elseif (false !== strpos($e->getMessage(), 'supports(): Argument #1')) {
                    continue;
                }

                throw $e;
            }

            // as soon as at least one attribute is supported, default is to deny access
            $vote = $this->deny();

            $decision = $this->voteOnAttribute($attribute, $subject, $token);
            if (\is_bool($decision)) {
                trigger_deprecation('symfony/security-core', '5.4', 'Returning a boolean in "%s::voteOnAttribute()" is deprecated, return an instance of "%s" instead.', static::class, Vote::class);
                $decision = $decision ? $this->grant() : $this->deny();
            }

            if ($decision->isGranted()) {
                // grant access as soon as at least one attribute returns a positive response
                return $decision;
            }

            $vote->setMessage($vote->getMessage().trim(' '.$decision->getMessage()));
        }

        return $vote;
    }

    /**
     * Creates a granted vote.
     */
    protected function grant(string $message = '', array $context = []): Vote
    {
        return Vote::createGranted($message, $context);
    }

    /**
     * Creates an abstained vote.
     */
    protected function abstain(string $message = '', array $context = []): Vote
    {
        return Vote::createAbstain($message, $context);
    }

    /**
     * Creates a denied vote.
     */
    protected function deny(string $message = '', array $context = []): Vote
    {
        return Vote::createDenied($message, $context);
    }

    /**
     * Determines if the attribute and subject are supported by this voter.
     *
     * @param string $attribute An attribute
     * @param mixed  $subject   The subject to secure, e.g. an object the user wants to access or any other PHP type
     */
    abstract protected function supports(string $attribute, $subject);

    /**
     * Perform a single access check operation on a given attribute, subject and token.
     * It is safe to assume that $attribute and $subject already passed the "supports()" method check.
     *
     * @param mixed $subject
     *
     * @return Vote Returning a boolean is deprecated since Symfony 5.4. Return a Vote object instead.
     */
    abstract protected function voteOnAttribute(string $attribute, $subject, TokenInterface $token);
}
