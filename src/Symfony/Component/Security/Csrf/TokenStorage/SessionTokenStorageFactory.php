<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Csrf\TokenStorage;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Exception\RuntimeException;

/**
 * Creates CSRF token storages based on the requests session.
 *
 * @author Oliver Hoff <oliver@hofff.com>
 */
class SessionTokenStorageFactory implements TokenStorageFactoryInterface
{
    /**
     * @var string
     */
    private $namespace;

    /**
     * @param string $namespace The namespace under which the token is stored in the session
     */
    public function __construct($namespace = null)
    {
        $this->namespace = $namespace === null ? SessionTokenStorage::SESSION_NAMESPACE : (string) $namespace;
    }

    /**
     * {@inheritdoc}
     *
     * @see \Symfony\Component\Security\Csrf\TokenStorage\TokenStorageFactoryInterface::createTokenStorage()
     */
    public function createTokenStorage(Request $request)
    {
        $session = $request->getSession();
        if (!$session) {
            throw new RuntimeException('Request has no session');
        }

        return new SessionTokenStorage($session, $this->namespace);
    }
}
