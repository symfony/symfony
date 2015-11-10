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

use Symfony\Component\Security\Core\Util\StringUtils;

/**
 * Default implementation of CsrfProviderInterface.
 *
 * This provider uses the session ID returned by session_id() as well as a
 * user-defined secret value to secure the CSRF token.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 *
 * @deprecated Deprecated since version 2.4, to be removed in Symfony 3.0. Use
 *             {@link \Symfony\Component\Security\Csrf\CsrfTokenManager} in
 *             combination with {@link \Symfony\Component\Security\Csrf\TokenStorage\NativeSessionTokenStorage}
 *             instead.
 */
class DefaultCsrfProvider implements CsrfProviderInterface
{
    /**
     * A secret value used for generating the CSRF token.
     *
     * @var string
     */
    protected $secret;

    /**
     * Initializes the provider with a secret value.
     *
     * A recommended value for the secret is a generated value with at least
     * 32 characters and mixed letters, digits and special characters.
     *
     * @param string $secret A secret value included in the CSRF token
     */
    public function __construct($secret)
    {
        $this->secret = $secret;
    }

    /**
     * {@inheritdoc}
     */
    public function generateCsrfToken($intention)
    {
        return sha1($this->secret.$intention.$this->getSessionId());
    }

    /**
     * {@inheritdoc}
     */
    public function isCsrfTokenValid($intention, $token)
    {
        $expectedToken = $this->generateCsrfToken($intention);

        if (function_exists('hash_equals')) {
            return hash_equals($expectedToken, $token);
        }

        if (class_exists('Symfony\Component\Security\Core\Util\StringUtils')) {
            return StringUtils::equals($expectedToken, $token);
        }

        return $token === $expectedToken;
    }

    /**
     * Returns the ID of the user session.
     *
     * Automatically starts the session if necessary.
     *
     * @return string The session ID
     */
    protected function getSessionId()
    {
        if (PHP_VERSION_ID >= 50400) {
            if (PHP_SESSION_NONE === session_status()) {
                session_start();
            }
        } elseif (!session_id()) {
            session_start();
        }

        return session_id();
    }
}
