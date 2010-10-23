<?php

namespace Symfony\Component\Security\Authorization\Voter;

use Symfony\Component\Security\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Authentication\Token\AnonymousToken;

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * AuthenticatedVoter votes if an attribute like IS_AUTHENTICATED_FULLY or
 * IS_AUTHENTICATED_ANONYMOUSLY is present.
 *
 * @author Fabien Potencier <fabien.potencier@symfony-project.com>
 */
class AuthenticatedVoter implements VoterInterface
{
    const IS_AUTHENTICATED_FULLY = 'IS_AUTHENTICATED_FULLY';
    const IS_AUTHENTICATED_ANONYMOUSLY = 'IS_AUTHENTICATED_ANONYMOUSLY';

    /**
     * {@inheritdoc}
     */
    public function supportsAttribute($attribute)
    {
        return null !== $attribute && (self::IS_AUTHENTICATED_FULLY === $attribute || self::IS_AUTHENTICATED_ANONYMOUSLY === $attribute);
    }

    /**
     * {@inheritdoc}
     */
    public function supportsClass($class)
    {
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function vote(TokenInterface $token, $object, array $attributes)
    {
        $result = VoterInterface::ACCESS_ABSTAIN;
        foreach ($attributes as $attribute) {
            if (!$this->supportsAttribute($attribute)) {
                continue;
            }

            $result = VoterInterface::ACCESS_DENIED;

            if (self::IS_AUTHENTICATED_FULLY === $attribute && !$token instanceof AnonymousToken) {
                return VoterInterface::ACCESS_GRANTED;
            }

            if (self::IS_AUTHENTICATED_ANONYMOUSLY === $attribute) {
                return VoterInterface::ACCESS_GRANTED;
            }
        }

        return $result;
    }
}
