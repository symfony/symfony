<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\CsrfProvider;

use Symfony\Component\HttpFoundation\Session;

/**
 * This provider uses a Symfony2 Session object to retrieve the user's
 * session ID
 *
 * @author Bernhard Schussek <bernhard.schussek@symfony-project.com>
 * @see DefaultCsrfProvider
 */
class SessionCsrfProvider extends DefaultCsrfProvider
{
    /**
     * The user session from which the session ID is returned
     * @var Session
     */
    protected $session;

    /**
     * Initializes the provider with a Session object and a secret value
     *
     * @param Session $session  The user session
     * @param string $secret    A secret value included in the CSRF token
     */
    public function __construct(Session $session, $secret)
    {
        parent::__construct($secret);

        $this->session = $session;
    }

    /**
     * Returns the ID of the user session
     *
     * Automatically starts the session if necessary.
     *
     * @return string  The session ID
     */
    protected function getSessionId()
    {
        $this->session->start();

        return $this->session->getId();
    }
}