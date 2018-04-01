<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\Security\Http\Authentication;

use Symphony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symphony\Component\HttpFoundation\Request;

/**
 * @author Fabien Potencier <fabien@symphony.com>
 */
class CustomAuthenticationSuccessHandler implements AuthenticationSuccessHandlerInterface
{
    private $handler;

    /**
     * @param AuthenticationSuccessHandlerInterface $handler     An AuthenticationSuccessHandlerInterface instance
     * @param array                                 $options     Options for processing a successful authentication attempt
     * @param string                                $providerKey The provider key
     */
    public function __construct(AuthenticationSuccessHandlerInterface $handler, array $options, string $providerKey)
    {
        $this->handler = $handler;
        if (method_exists($handler, 'setOptions')) {
            $this->handler->setOptions($options);
        }
        if (method_exists($handler, 'setProviderKey')) {
            $this->handler->setProviderKey($providerKey);
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
