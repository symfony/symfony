<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\SecurityBundle\Tests\Fixtures;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationSuccessHandlerInterface;

class DecoratingAuthenticationSuccessHandler implements AuthenticationSuccessHandlerInterface
{
    public function __construct(private AuthenticationSuccessHandlerInterface $handler)
    {
    }

    public function setOptions(array $options): void
    {
        if (method_exists($this->handler, 'setOptions')) {
            $this->handler->setOptions($options);
        }
    }

    public function setFirewallName(string $firewallName): void
    {
        if (method_exists($this->handler, 'setFirewallName')) {
            $this->handler->setFirewallName($firewallName);
        }
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token): ?Response
    {
        $response = $this->handler->onAuthenticationSuccess($request, $token);
        $response?->headers->add([
            'X-Decorated-Handler' => $this->handler::class,
            'X-Decorating-Handler' => __CLASS__,
            'X-Firewall-Name' => method_exists($this->handler, 'getFirewallName') ? $this->handler->getFirewallName() : null,
        ]);

        return $response;
    }
}
