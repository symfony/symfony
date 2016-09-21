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
     * @var string
     */
    private $secureNamespace;

    /**
     * @param string $namespace       The namespace under which tokens are stored in the session
     * @param string $secureNamespace The namespace under which tokens are stored in the session for secure connections
     */
    public function __construct($namespace = null, $secureNamespace = null)
    {
        $this->namespace = $namespace === null ? SessionTokenStorage::SESSION_NAMESPACE : (string) $namespace;
        $this->secureNamespace = $secureNamespace === null ? $this->namespace : (string) $secureNamespace;
    }

    /**
     * {@inheritdoc}
     */
    public function createTokenStorage(Request $request)
    {
        $session = $request->getSession();
        if (!$session) {
            throw new RuntimeException('Request has no session');
        }

        $namespace = $request->isSecure() ? $this->secureNamespace : $this->namespace;

        return new SessionTokenStorage($session, $namespace);
    }
}
