<?php

namespace Symfony\Component\Security\Core\Authentication\Token;

/**
 * @author Wouter de Jong <wouter@wouterj.nl>
 */
class UsernamePasswordRequestToken extends UsernamePasswordToken implements AuthenticationRequestTokenInterface
{
    /**
     * @param string $username    The passed username
     * @param string $credentials The used password to authenticate
     * @param string $providerKey The provider key (usually the firewall context when using Security Http)
     * @param array  $roles       Deprecated since 3.1, to be removed in 4.0.
     */
    public function __construct($username, $credentials, $providerKey, array $roles = array())
    {
        if (0 < count($roles)) {
            @trigger_error('The roles parameter of the constructor of '.__CLASS__.' is deprecated since version 3.1 and will be removed in 4.0.', E_USER_DEPRECATED);
        }

        parent::__construct($username, $credentials, $providerKey, $roles);
    }

    /**
     * {@inheritdoc}
     *
     * @deprecated Since version 3.1, to be removed in 4.0. Unauthenticated tokens cannot have a user object.
     */
    public function getUser()
    {
        @trigger_error('Method '.__METHOD__.' on unauthenticated tokens is deprecated since version 3.1 and will be removed in 4.0. Use getUsername() instead to retrieve the identifier passed with the request.', E_USER_DEPRECATED);

        return parent::getUser();
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
