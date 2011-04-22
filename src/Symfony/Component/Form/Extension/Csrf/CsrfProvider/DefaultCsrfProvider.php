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

/**
 * Default implementation of CsrfProviderInterface
 *
 * This provider uses the session ID returned by session_id() as well as a
 * user-defined secret value to secure the CSRF token.
 *
 * @author Bernhard Schussek <bernhard.schussek@symfony.com>
 */
class DefaultCsrfProvider implements CsrfProviderInterface
{
    /**
     * A secret value used for generating the CSRF token
     * @var string
     */
    protected $secret;

    /**
     * Initializes the provider with a secret value
     *
     * A recommended value for the secret is a generated value with at least
     * 32 characters and mixed letters, digits and special characters.
     *
     * @param string $secret  A secret value included in the CSRF token
     */
    public function __construct($secret)
    {
        $this->secret = $secret;
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
        if (!session_id()) {
            session_start();
        }

        return session_id();
    }

    /**
     * {@inheritDoc}
     */
    public function generateCsrfToken($pageId)
    {
        return sha1($this->secret.$pageId.$this->getSessionId());
    }

    /**
     * {@inheritDoc}
     */
    public function isCsrfTokenValid($pageId, $token)
    {
        return $token === $this->generateCsrfToken($pageId);
    }
}