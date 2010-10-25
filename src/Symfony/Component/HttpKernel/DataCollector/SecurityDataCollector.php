<?php

namespace Symfony\Component\HttpKernel\DataCollector;

use Symfony\Component\Security\SecurityContext;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/*
 * This file is part of the Symfony framework.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

/**
 * SecurityDataCollector.
 *
 * @author Fabien Potencier <fabien.potencier@symfony-project.com>
 */
class SecurityDataCollector extends DataCollector
{
    protected $context;

    public function __construct(SecurityContext $context = null)
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
            );
        } elseif (null === $token = $this->context->getToken()) {
            $this->data = array(
                'enabled'       => true,
                'authenticated' => false,
                'user'          => '',
            );
        } else {
            $this->data = array(
                'enabled'       => true,
                'authenticated' => $token->isAuthenticated(),
                'user'          => (string) $token->getUser(),
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
