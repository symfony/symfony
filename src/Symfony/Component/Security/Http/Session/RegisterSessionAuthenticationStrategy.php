<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Http\Session;

use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Strategy used to register a user with the SessionRegistry after
 * successful authentication.
 *
 * @author Antonio J. García Lagar <aj@garcialagar.es>
 */
class RegisterSessionAuthenticationStrategy implements SessionAuthenticationStrategyInterface
{
    /**
     * @var SessionRegistry
     */
    private $registry;

    public function __construct(SessionRegistry $registry)
    {
        $this->registry = $registry;
    }

    /**
     * {@inheritdoc}
     */
    public function onAuthentication(Request $request, TokenInterface $token)
    {
        $this->registry->registerNewSession($request->getSession()->getId(), $token->getUsername());
    }
}
