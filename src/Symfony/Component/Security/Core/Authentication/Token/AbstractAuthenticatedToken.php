<?php

namespace Symfony\Component\Security\Core\Authentication\Token;

/**
 * @author Wouter de Jong <wouter@wouterj.nl>
 */
abstract class AbstractAuthenticatedToken extends AbstractToken implements AuthenticatedTokenInterface
{
    private $identifier;

    /**
     * @param string $identifier An identifier for the authenticated user
     * @param object $user       The current user
     * @param array  $roles      The roles of the authenticated user
     */
    public function __construct($identifier, $user, array $roles)
    {
        parent::__construct($roles);

        $this->setUser($user);
    }

    public function getIdentifier()
    {
        return $this->identifier;
    }

    /**
     * {@inheritdoc}
     */
    public function getCredentials()
    {
        @trigger_error(__METHOD__.' is deprecated since version 3.1 and will be removed in 4.0.', E_USER_DEPRECATED);
    }

    /**
     * {@inheritdoc}
     */
    public function eraseCredentials()
    {
        @trigger_error(__METHOD__.' is deprecated since version 3.1 and will be removed in 4.0.', E_USER_DEPRECATED);

        parent::eraseCredentials();
    }

    /**
     * {@inheritdoc}
     */
    public function isAuthenticated()
    {
        @trigger_error(__METHOD__.' is deprecated since version 3.1 and will be removed in 4.0. Use an instance of check with AuthenticatedTokenInterface instead.', E_USER_DEPRECATED);

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function setAuthenticated($authenticated)
    {
        @trigger_error(__METHOD__.' is deprecated since version 3.1 and will be removed in 4.0.', E_USER_DEPRECATED);
    }

    /**
     * {@inheritdoc}
     */
    public function serialize()
    {
        return serialize(array($this->identifier, $this->roles, $this->attributes));
    }

    /**
     * {@inheritdoc}
     */
    public function unserialize($serialized)
    {
        list($this->identifier, $this->roles, $this->attributes) = unserialize($serialized);
    }
}
