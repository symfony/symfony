<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Extension\Csrf\CsrfProvider;

use Symfony\Component\HttpFoundation\Session\Session;

/**
 * This provider uses a Symfony Session object to retrieve the user's
 * session ID.
 *
 * @see DefaultCsrfProvider
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 *
 * @deprecated Deprecated since version 2.4, to be removed in Symfony 3.0. Use
 *             {@link \Symfony\Component\Security\Csrf\CsrfTokenManager} in
 *             combination with {@link \Symfony\Component\Security\Csrf\TokenStorage\SessionTokenStorage}
 *             instead.
 */
class SessionCsrfProvider extends DefaultCsrfProvider
{
    /**
     * The user session from which the session ID is returned
     * @var Session
     */
    protected $session;

    /**
     * Initializes the provider with a Session object and a secret value.
     *
     * A recommended value for the secret is a generated value with at least
     * 32 characters and mixed letters, digits and special characters.
     *
     * @param Session $session The user session
     * @param string  $secret  A secret value included in the CSRF token
     */
    public function __construct(Session $session, $secret)
    {
        parent::__construct($secret);

        $this->session = $session;
    }

    /**
     * {@inheritdoc}
     */
    protected function getSessionId()
    {
        $this->session->start();

        return $this->session->getId();
    }
}
