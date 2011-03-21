<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\SecurityBundle\DataCollector;

use Symfony\Component\Security\Core\SecurityContextInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\DataCollector\DataCollector;

/**
 * SecurityDataCollector.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class SecurityDataCollector extends DataCollector
{
    private $context;

    public function __construct(SecurityContextInterface $context = null)
    {
        $this->context = $context;
    }

    /**
     * {@inheritdoc}
     */
    public function collect(Request $request, Response $response, \Exception $exception = null)
    {
        if (null === $this->context) {
            $this->data = array(
                'enabled'       => false,
                'authenticated' => false,
                'user'          => '',
                'roles'         => array(),
            );
        } elseif (null === $token = $this->context->getToken()) {
            $this->data = array(
                'enabled'       => true,
                'authenticated' => false,
                'user'          => '',
                'roles'         => array(),
            );
        } else {
            $this->data = array(
                'enabled'       => true,
                'authenticated' => $token->isAuthenticated(),
                'user'          => $token->getUsername(),
                'roles'         => array_map(function ($role){ return $role->getRole();}, $token->getRoles()),
            );
        }
    }

    /**
     * Checks if security is enabled.
     *
     * @return Boolean true if security is enabled, false otherwise
     */
    public function isEnabled()
    {
        return $this->data['enabled'];
    }

    /**
     * Gets the user.
     *
     * @return string The user
     */
    public function getUser()
    {
        return $this->data['user'];
    }

    /**
     * Gets the roles of the user.
     *
     * @return array The roles
     */
    public function getRoles()
    {
        return $this->data['roles'];
    }

    /**
     * Checks if the user is authenticated or not.
     *
     * @return Boolean true if the user is authenticated, false otherwise
     */
    public function isAuthenticated()
    {
        return $this->data['authenticated'];
    }

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return 'security';
    }
}
