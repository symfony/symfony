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
 */
abstract class Voter implements VoterInterface
{
    /**
     * {@inheritdoc}
     */
    public function vote(TokenInterface $token, $subject, array $attributes)
    {
        // abstain vote by default in case none of the attributes are supported
        $vote = self::ACCESS_ABSTAIN;

        foreach ($attributes as $attribute) {
            if (!$this->supports($attribute, $subject)) {
                continue;
            }

            // as soon as at least one attribute is supported, default is to deny access
            $vote = self::ACCESS_DENIED;

            if ($this->voteOnAttribute($attribute, $subject, $token)) {
                // grant access as soon as at least one attribute returns a positive response
                return self::ACCESS_GRANTED;
            }
        }

        return $vote;
    }

    /**
     * Determines if the attribute and subject are supported by this voter.
     *
     * @param string $attribute An attribute
     * @param mixed  $subject   The subject to secure, e.g. an object the user wants to access or any other PHP type
     *
     * @return bool True if the attribute and subject are supported, false otherwise
     */
    abstract protected function supports($attribute, $subject);

    /**
     * Perform a single access check operation on a given attribute, subject and token.
     * It is safe to assume that $attribute and $subject already passed the "supports()" method check.
     *
     * @param string $attribute
     * @param mixed  $subject
     *
     * @return bool
     */
    abstract protected function voteOnAttribute($attribute, $subject, TokenInterface $token);
}
