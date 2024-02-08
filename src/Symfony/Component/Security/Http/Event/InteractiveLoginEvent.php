<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Http\Event;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * @author Fabien Potencier <fabien@symfony.com>
 */
final class InteractiveLoginEvent extends Event
{
    private Request $request;
    private TokenInterface $authenticationToken;

    public function __construct(Request $request, TokenInterface $authenticationToken)
    {
        $this->request = $request;
        $this->authenticationToken = $authenticationToken;
    }

    public function getRequest(): Request
    {
        return $this->request;
    }

    public function getAuthenticationToken(): TokenInterface
    {
        return $this->authenticationToken;
    }
}
