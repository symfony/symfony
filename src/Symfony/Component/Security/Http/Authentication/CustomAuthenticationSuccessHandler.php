<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Http\Authentication;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;

/**
 * @author Fabien Potencier <fabien@symfony.com>
 */
class CustomAuthenticationSuccessHandler implements AuthenticationSuccessHandlerInterface
{
    /**
     * @param array $options Options for processing a successful authentication attempt
     */
    public function __construct(
        private AuthenticationSuccessHandlerInterface $handler,
        private array $options,
        private string $firewallName,
    ) {
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token): ?Response
    {
        if (method_exists($this->handler, 'setOptions')) {
            $this->handler->setOptions($this->options);
        }

        if (method_exists($this->handler, 'setFirewallName')) {
            $this->handler->setFirewallName($this->firewallName);
        }

        return $this->handler->onAuthenticationSuccess($request, $token);
    }
}
