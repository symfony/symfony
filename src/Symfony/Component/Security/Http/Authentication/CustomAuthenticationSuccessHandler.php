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
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;

/**
 * @author Fabien Potencier <fabien@symfony.com>
 */
class CustomAuthenticationSuccessHandler implements AuthenticationSuccessHandlerInterface
{
    private $handler;

    /**
     * @param array $options Options for processing a successful authentication attempt
     */
    public function __construct(AuthenticationSuccessHandlerInterface $handler, array $options, string $firewallName)
    {
        $this->handler = $handler;
        if (method_exists($handler, 'setOptions')) {
            $this->handler->setOptions($options);
        }

        if (method_exists($handler, 'setFirewallName')) {
            $this->handler->setFirewallName($firewallName);
        } elseif (method_exists($handler, 'setProviderKey')) {
            trigger_deprecation('symfony/security-http', '5.2', 'Method "%s::setProviderKey()" is deprecated, rename the method to "setFirewallName()" instead.', \get_class($handler));

            $this->handler->setProviderKey($firewallName);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function onAuthenticationSuccess(Request $request, TokenInterface $token)
    {
        return $this->handler->onAuthenticationSuccess($request, $token);
    }
}
