<?php

namespace Symfony\Component\Security\Core\Authentication\Token;

/**
 * Requests anonymous authentication.
 *
 * @author Wouter de Jong <wouter@wouterj.nl>
 * @author Fabien Potencier <fabien@symfony.com>
 */
class AnonymousRequestToken extends AnonymousToken implements AuthenticationRequestTokenInterface
{
    /**
     * @param string $secret     A secret used to make sure the token is created by the app and not by a malicious client
     * @param string $identifier The name of the user (probably "anon.")
     * @param array  $roles      Deprecated, auth request tokens cannot have roles
     */
    public function __construct($secret, $identifier, array $roles = [])
    {
        if (0 < count($roles)) {
            @trigger_error('The roles parameter of the constructor of '.__CLASS__.' is deprecated since vesion 3.1 and will be removed in 4.0.', E_USER_DEPRECATED);
        }

        parent::__construct($secret, $identifier, $roles, false);
    }

    /**
     * {@inheritdoc}
     *
     * @deprecated Since version 3.1, to be removed in 4.0. Unauthenticated tokens cannot have roles.
     */
    public function getRoles()
    {
        @trigger_error('Method '.__METHOD__.' on unauthenticated tokens is deprecated since version 3.1 and will be removed in 4.0.', E_USER_DEPRECATED);

        return parent::getRoles();
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
