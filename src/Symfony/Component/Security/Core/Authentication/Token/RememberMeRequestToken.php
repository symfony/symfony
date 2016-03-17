<?php

namespace Symfony\Component\Security\Core\Authentication\Token;

use Symfony\Component\Security\Core\User\UserInterface;

/**
 * @author Wouter de Jong <wouter@wouterj.nl>
 */
class RememberMeRequestToken extends RememberMeToken implements AuthenticationRequestTokenInterface
{
    /**
     * {@inheritdoc}
     */
    public function __construct(UserInterface $user, $providerKey, $secret)
    {
        parent::__construct($user, $providerKey, $secret, false);
    }

    /**
     * {@inheritdoc}
     */
    public function isAuthenticated()
    {
        @trigger_error('Method '.__METHOD__.' is deprecated since version 3.1 and will be removed in 4.0. Use an instance of check with AuthenticatedTokenInterface instead.', E_USER_DEPRECATED);

        return false;
    }
}
