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

use Symfony\Component\Security\Core\Authentication\Token\SwitchUserToken;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\AccessDecisionManager;

/**
 * SwitchRoleVoter votes if we check the impersonator roles.
 *
 * @author Fabien Papet <fabien.papet@gmail.com>
 */
class SwitchRoleVoter implements VoterInterface
{
    /**
     * @var AccessDecisionManager
     */
    private $accessDecisionManager;
    private $prefix;

    public function __construct(AccessDecisionManager $accessDecisionManager, string $prefix = 'IMPERSONATOR_')
    {
        $this->accessDecisionManager = $accessDecisionManager;
        $this->prefix = $prefix;
    }

    /**
     * {@inheritdoc}
     */
    public function vote(TokenInterface $token, $subject, array $attributes)
    {
        $result = VoterInterface::ACCESS_ABSTAIN;

        if (!$token instanceof SwitchUserToken) {
            return VoterInterface::ACCESS_DENIED;
        }

        foreach ($attributes as $attribute) {
            if (!\is_string($attribute) || 0 !== strpos($attribute, $this->prefix)) {
                continue;
            }

            return $this->accessDecisionManager->decide($token->getOriginalToken(), [substr($attribute, \strlen($this->prefix))], $subject);
        }

        return $result;
    }
}
